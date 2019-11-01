<?php namespace Ewll\UserBundle\Token;

use DateTime;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Entity\Token;
use Ewll\UserBundle\Token\Exception\ActiveTokenExistsException;
use Ewll\UserBundle\Token\Exception\TokenNotFoundException;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class TokenProvider
{
    const CODE_KEY_NAME = 'key';

    private $repositoryProvider;
    /** @var TokenInterface[] */
    private $tokenItems;
    private $router;
    private $logger;
    private $salt;
    private $domain;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        iterable $tokenItems,
        Router $router,
        Logger $logger,
        string $salt,
        string $domain
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->tokenItems = $tokenItems;
        $this->router = $router;
        $this->logger = $logger;
        $this->salt = $salt;
        $this->domain = $domain;
    }

    /** @throws ActiveTokenExistsException */
    public function generate(string $class, array $data, string $ip, bool $isCheckExists = false): Token
    {
        $tokenRepository = $this->repositoryProvider->get(Token::class);
        $tokenItem = $this->getTokenItemByClass($class);
        $actionHash = md5("{$tokenItem->getTypeId()}{$data[$tokenItem->getIdDataKey()]}");
        if ($isCheckExists) {
            $activeToken = $tokenRepository->findOneBy(['actionHash' => $actionHash]);
            if (null !== $activeToken) {
                throw new ActiveTokenExistsException();
            }
        }
        $expirationTs = new DateTime("+{$tokenItem->getLifeTimeMinutes()} minutes");
        $data[self::CODE_KEY_NAME] = hash('sha256', uniqid() . $this->salt . time() . json_encode($data));
        $token = Token::create($tokenItem->getTypeId(), $actionHash, $data, $ip, $expirationTs);
        $tokenRepository->create($token);

        $logData = [
            'typeId' => $token->typeId,
            'ip' => $ip,
            'userId' => $data['userId'] ?? null,
            'email' => $data['email'] ?? null
        ];
        $this->logger->info("Token $class #{$token->id} generated", $logData);

        return $token;
    }

    /** @throws TokenNotFoundException */
    public function getByCode(string $tokenCode, int $typeId = null): Token
    {
        try {
            if (!preg_match('/^(\d+)\.([a-z0-9]{64})$/', $tokenCode, $matches)) {
                throw new TokenNotFoundException("Incorrect tokenCode: '$tokenCode'");
            }
            $tokenId = $matches[1];
            $tokenKey = $matches[2];
            $conditions = ['id' => $tokenId];
            if (null !== $typeId) {
                $conditions['typeId'] = $typeId;
            }
            /** @var Token|null $token */
            $token = $this->repositoryProvider->get(Token::class)->findOneBy($conditions);
            if (null === $token) {
                throw new TokenNotFoundException("Not found by id #$tokenId");
            }
            if ($token->data[self::CODE_KEY_NAME] !== $tokenKey) {
                throw new TokenNotFoundException('Keys does not match');
            }

            return $token;
        } catch (TokenNotFoundException $e) {
            $this->logger->warning("TokenNotFoundException {$e->getMessage()}", ['typeId' => $typeId]);

            throw $e;
        }
    }

    public function toUse(Token $token): void
    {
        $this->repositoryProvider->get(Token::class)->delete($token, true);
        $this->logger->info("Token #{$token->id} used", ['typeId' => $token->typeId]);
    }

    public function compileTokenCode(Token $token): string
    {
        return "{$token->id}.{$token->data[self::CODE_KEY_NAME]}";
    }

    public function compileTokenPageUrl(Token $token): string
    {
        $tokenItem = $this->getTokenItemByTokenTypeId($token->typeId);
        $url = 'https:' . $this->router->generate(
                $tokenItem->getRoute(),
                ['tokenCode' => $this->compileTokenCode($token)],
                UrlGeneratorInterface::NETWORK_PATH
            );

        return $url;
    }

    private function getTokenItemByClass(string $class)
    {
        foreach ($this->tokenItems as $item) {
            if ($item instanceof $class) {
                return $item;
            }
        }

        throw new RuntimeException("Token item '$class' not found");
    }

    private function getTokenItemByTokenTypeId(int $typeId)
    {
        foreach ($this->tokenItems as $item) {
            if ($typeId === $item->getTypeId()) {
                return $item;
            }
        }

        throw new RuntimeException("Token item #$typeId not found");
    }
}
