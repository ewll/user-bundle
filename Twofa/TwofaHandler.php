<?php namespace Ewll\UserBundle\Twofa;

use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\EwllUserBundle;
use Ewll\UserBundle\Repository\TwofaCodeRepository;
use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
use Ewll\UserBundle\Twofa\Exception\CannotSendMessageException;
use Ewll\UserBundle\Twofa\Exception\EmptyTwofaCodeException;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Exception;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwofaHandler
{
    private $authenticator;
    private $repositoryProvider;
    private $defaultDbClient;
    private $translator;
    private $logger;
    /** @var TwofaInterface[] */
    private $twofas;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        DbClient $defaultDbClient,
        TranslatorInterface $translator,
        Logger $logger,
        iterable $twofas
    ) {
        $this->authenticator = $authenticator;
        $this->repositoryProvider = $repositoryProvider;
        $this->defaultDbClient = $defaultDbClient;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->twofas = $twofas;
    }

    /** @throws CannotProvideCodeException */
    public function provideCode(User $user, StoredKeyTwofaInterface $twofa, string $contact, int $actionId)
    {
        /** @var TwofaCodeRepository $twofaCodeRepository */
        $twofaCodeRepository = $this->repositoryProvider->get(TwofaCode::class);
        $activeTwofaCode = $twofaCodeRepository->findActive($user->id, $actionId);
        //@todo race condition
        if (null !== $activeTwofaCode) {
            throw new CannotProvideCodeException('Have active code', CannotProvideCodeException::CODE_HAVE_ACTIVE);
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
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();
            $code = 0;
            if ($e instanceof CannotSendMessageException) {
                $this->logger->crit(
                    'Cannot send twofa code.',
                    ['type' => $twofa->getType(), 'contact' => $contact, 'message' => $e->getMessage()]
                );
                if ($e->getCode() === CannotSendMessageException::CODE_RECIPIENT_NOT_EXISTS) {
                    $code = CannotProvideCodeException::CODE_RECIPIENT_NOT_EXISTS;
                }
            }

            throw new CannotProvideCodeException($e->getMessage(), $code, $e);
        }
    }

    /**
     * @throws EmptyTwofaCodeException
     * @throws IncorrectTwofaCodeException
     */
    public function setTwofa(
        User $user,
        TwofaInterface $twofa,
        string $code,
        int $actionId,
        string $context = null
    ): void {
        /** @var TwofaCodeRepository $twofaCodeRepository */
        $twofaCodeRepository = $this->repositoryProvider->get(TwofaCode::class);
        $this->defaultDbClient->beginTransaction();
        try {
            $isStoredKey = $twofa instanceof StoredKeyTwofaInterface;
            if (empty($code)) {
                throw new EmptyTwofaCodeException($isStoredKey, $actionId);
            }
            if ($twofa instanceof StoredKeyTwofaInterface) {
                $activeTwofaCode = $twofaCodeRepository->findActive($user->id, $actionId, true);
                if (null === $activeTwofaCode || $activeTwofaCode->code !== $code) {
                    throw new IncorrectTwofaCodeException($isStoredKey, $actionId);
                }
                $activeTwofaCode->isUsed = true;
                $twofaCodeRepository->update($activeTwofaCode, ['isUsed']);
                $data = ['contact' => $activeTwofaCode->contact];
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
        } catch (Exception|EmptyTwofaCodeException|IncorrectTwofaCodeException $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    /**
     * @throws EmptyTwofaCodeException
     * @throws IncorrectTwofaCodeException
     */
    public function checkCode(User $user, FormInterface $form, int $actionId): void
    {
        $code = $form->getData()['twofaCode'] ?? null;
        $twofa = $this->getTwofaServiceByTypeId($user->twofaTypeId);
        $isStoredKey = $twofa instanceof StoredKeyTwofaInterface;
        if (empty($code)) {
            throw new EmptyTwofaCodeException($isStoredKey, $actionId);
        }
        /** @var TwofaCodeRepository $twofaCodeRepository */
        $twofaCodeRepository = $this->repositoryProvider->get(TwofaCode::class);
        if ($twofa instanceof StoredKeyTwofaInterface) {
            $activeTwofaCode = $twofaCodeRepository->findActive($user->id, $actionId, true);
            if (null === $activeTwofaCode || $activeTwofaCode->code !== $code) {
                throw new IncorrectTwofaCodeException($isStoredKey, $actionId);
            }
            $activeTwofaCode->isUsed = true;
            $twofaCodeRepository->update($activeTwofaCode, ['isUsed']);
        } elseif ($twofa instanceof CheckKeyOnTheFlyTwofaInterface) {
            if (!$twofa->isCodeCorrect($user->twofaData, $code)) {
                throw new IncorrectTwofaCodeException($isStoredKey, $actionId);
            }
        } else {
            throw new RuntimeException('Unknown twofa type');
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
}
