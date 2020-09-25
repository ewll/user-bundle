<?php namespace Ewll\UserBundle\Twofa;

use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Entity\TwofaCode;
use App\Entity\User;
use Ewll\UserBundle\Token\Item\TelegramToken;
use Ewll\UserBundle\Token\TokenProvider;
use Ewll\UserBundle\EwllUserBundle;
use Ewll\UserBundle\Repository\TwofaCodeRepository;
use Ewll\UserBundle\Token\Exception\ActiveTokenExistsException;
use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
use Ewll\UserBundle\Twofa\Exception\CannotProvideTokenException;
use Ewll\UserBundle\Twofa\Exception\CannotSendMessageException;
use Ewll\UserBundle\Twofa\Exception\EmptyTwofaCodeException;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\Item\TelegramTwofa;
use Exception;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwofaHandler
{
    private $authenticator;
    private $repositoryProvider;
    private $defaultDbClient;
    private $telegramTwofa;
    private $tokenProvider;
    private $translator;
    private $logger;
    /** @var TwofaInterface[] */
    private $twofas;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        DbClient $defaultDbClient,
        TelegramTwofa $telegramTwofa,
        TokenProvider $tokenProvider,
        TranslatorInterface $translator,
        Logger $logger,
        iterable $twofas
    ) {
        $this->authenticator = $authenticator;
        $this->repositoryProvider = $repositoryProvider;
        $this->defaultDbClient = $defaultDbClient;
        $this->telegramTwofa = $telegramTwofa;
        $this->tokenProvider = $tokenProvider;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->twofas = $twofas;
    }

    /** @throws CannotProvideCodeException */
    public function provideCodeToContact(User $user, StoredKeyTwofaInterface $twofa, string $contact, int $actionId)
    {
        /** @var TwofaCodeRepository $twofaCodeRepository */
        $twofaCodeRepository = $this->repositoryProvider->get(TwofaCode::class);
        $activeTwofaCode = $twofaCodeRepository->findActive($user->id, $actionId);
        if (null !== $activeTwofaCode) {
            $code = CannotProvideCodeException::CODE_HAVE_ACTIVE;
            $error = $this->transProvideError($code);

            throw new CannotProvideCodeException($error, $code);
        }

        $code = random_int(100000, 999999);
        $twofaCode = TwofaCode::create($user->id, $twofa->getId(), $actionId, $contact, $code);
        $message = $this->translator
            ->trans('twofa.code-message', ['%code%' => $code], EwllUserBundle::TRANSLATION_DOMAIN);
        $this->defaultDbClient->beginTransaction();
        try {
            $twofaCodeRepository->create($twofaCode);
            $twofa->sendMessage($contact, $message);
            $this->defaultDbClient->commit();
            $this->logger->info(
                "TwofaCode #{$twofaCode->id} provided",
                ['type' => $twofa->getType(), 'contact' => $contact, 'userId' => $twofaCode->userId,]
            );
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();
            $code = CannotProvideCodeException::CODE_CANNOT_SEND;
            if ($e instanceof CannotSendMessageException) {
                $this->logger->critical(
                    "CannotSendMessageException: {$e->getMessage()}",
                    ['type' => $twofa->getType(), 'contact' => $contact, 'userId' => $twofaCode->userId,]
                );
                if ($e->getCode() === CannotSendMessageException::CODE_RECIPIENT_NOT_EXISTS) {
                    $code = CannotProvideCodeException::CODE_RECIPIENT_NOT_EXISTS;
                }
            }
            $error = $this->transProvideError($code);

            throw new CannotProvideCodeException($error, $code, $e);
        }
    }

    public function provideTokenToContact(string $contact, string $ip) {
        $tokenData = ['contact' => $contact];
        $i = 0;
        while($i < 99) {
            try {
                $token = $this->tokenProvider->generate(TelegramToken::class, $tokenData, $ip);
                break;
            } catch (ActiveTokenExistsException $exception) {
                $i++;
            }
        }
        $provideTokenToContact = $this->tokenProvider->compileTokenCode($token);
        $message = $this->translator
            ->trans('twofa.token-message', ['%provideTokenToContact%' => $provideTokenToContact], EwllUserBundle::TRANSLATION_DOMAIN);
        try {
            $this->telegramTwofa->sendMessage($contact, $message);
            $this->logger->info(
                "TwofaToken #{$provideTokenToContact} provided",
                ['type' => TelegramToken::TYPE_ID, 'contact' => $contact]
            );
        } catch (Exception $e) {
            $code = CannotProvideTokenException::CODE_CANNOT_SEND;
            if ($e instanceof CannotSendMessageException) {
                $this->logger->critical(
                    "CannotSendMessageException: {$e->getMessage()}",
                    ['type' => TelegramToken::TYPE_ID, 'contact' => $contact]
                );
            }
            $error = $this->transProvideError($code);

            throw new CannotProvideTokenException($error, $code, $e);
        }
    }

    /** @throws CannotProvideCodeException */
    public function provideCodeToUser(User $user, int $actionId)
    {
        if (!$user->hasTwofa()) {
            $code = CannotProvideCodeException::CODE_USER_HAS_NO_TWOFA;
            $error = $this->transProvideError($code);

            throw new CannotProvideCodeException($error, $code);
        }
        $twofa = $this->getTwofaServiceByTypeId($user->twofaTypeId, true);
        if (!$twofa instanceof StoredKeyTwofaInterface) {
            throw new RuntimeException('Only StoredKeyTwofaInterface must be here');
        }
        $this->provideCodeToContact($user, $twofa, $user->twofaData['contact'], $actionId);
    }

    /**
     * @throws EmptyTwofaCodeException
     * @throws IncorrectTwofaCodeException
     */
    public function setTwofa(
        User $user,
        TwofaInterface $twofa,
        string $code,
        string $context = null
    ): void {
        $actionId = TwofaCode::ACTION_ID_ENROLL;
        $this->defaultDbClient->beginTransaction();
        try {
            $isStoredKey = $twofa instanceof StoredKeyTwofaInterface;
            if (empty($code)) {
                throw new EmptyTwofaCodeException($isStoredKey, $actionId);
            }
            if ($twofa instanceof StoredKeyTwofaInterface) {
                $activeToken = $this->tokenProvider->getByCode($code, TelegramToken::TYPE_ID);
                if (null === $activeToken) {
                    throw new IncorrectTwofaCodeException($isStoredKey, $actionId);
                }
                $data = ['contact' => $activeToken->data['contact']];
            } elseif ($twofa instanceof CheckKeyOnTheFlyTwofaInterface) {
                $data = $twofa->compileDataFromContext($context);
                if (!$twofa->isCodeCorrect($data, $code)) {
                    throw new IncorrectTwofaCodeException($isStoredKey, $actionId);
                }
            } else {
                throw new RuntimeException('Unknown twofa type');
            }

            $user->twofaTypeId = $twofa->getId();
            $user->twofaData = $data;
            $this->repositoryProvider->get(User::class)->update($user, ['twofaTypeId', 'twofaData']);
            $this->defaultDbClient->commit();
            $this->logger->info(
                "Twofa has set",
                ['type' => $twofa->getType(), 'userId' => $user->id,]
            );
        } catch (Exception|EmptyTwofaCodeException|IncorrectTwofaCodeException $e) {
            $this->defaultDbClient->rollback();
            $this->logger->warning(
                "Cannot set twofa: {$e->getMessage()}",
                ['type' => $twofa->getType(), 'userId' => $user->id,]
            );

            throw $e;
        }
    }

    /** @throws IncorrectTwofaCodeException */
    public function checkAndDeactivateCode(TwofaInterface $twofa, User $user, string $code, int $actionId): void
    {
        try {
            /** @var TwofaCodeRepository $twofaCodeRepository */
            $twofaCodeRepository = $this->repositoryProvider->get(TwofaCode::class);
            if ($twofa instanceof StoredKeyTwofaInterface) {
                $activeTwofaCode = $twofaCodeRepository->findActive($user->id, $actionId, true);
                if (null === $activeTwofaCode || $activeTwofaCode->code !== $code) {
                    throw new IncorrectTwofaCodeException();
                }
                $twofaCodeRepository->delete($activeTwofaCode, true);
                $this->logger->info(
                    "Successful TwofaCode checking",
                    ['type' => $twofa->getType(), 'userId' => $user->id,]
                );
            } elseif ($twofa instanceof CheckKeyOnTheFlyTwofaInterface) {
                if (!$twofa->isCodeCorrect($user->twofaData, $code)) {
                    throw new IncorrectTwofaCodeException();
                }
                $this->logger->info(
                    "Successful TwofaCode checking",
                    ['type' => $twofa->getType(), 'userId' => $user->id,]
                );
            } else {
                throw new RuntimeException('Unknown twofa type');
            }
        } catch (IncorrectTwofaCodeException $e) {
            $this->logger->warning(
                "IncorrectTwofaCodeException: {$e->getMessage()}",
                ['type' => $twofa->getType(), 'userId' => $user->id,]
            );

            throw $e;
        }
    }

    public function getTwofaServiceByTypeId(int $typeId, $isStoredCode = false)
    {
        foreach ($this->twofas as $twofa) {
            if ($twofa->getId() === $typeId) {
                if ($isStoredCode && !$twofa instanceof StoredKeyTwofaInterface) {
                    throw new RuntimeException('Expect only stored key type');
                }
                return $twofa;
            }
        }

        throw new RuntimeException('Twofa service not found');
    }

    private function transProvideError(int $code)
    {
        return $this->translator->trans("twofa.message.$code", [], 'validators');
    }
}
