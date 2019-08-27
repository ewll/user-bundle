<?php namespace Ewll\UserBundle\Authenticator;

use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\MailerBundle\Mailer;
use Ewll\UserBundle\Controller\UserController;
use Ewll\UserBundle\Entity\User;
use Exception;
use RuntimeException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class Authenticator
{
    private $repositoryProvider;
    private $domain;
    private $router;
    private $mailer;
    private $defaultDbClient;
    private $salt;

    private $user;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        string $domain,
        Router $router,
        DbClient $defaultDbClient,
        Mailer $mailer,
        string $salt
    ) {
        $this->salt = $salt;
        $this->repositoryProvider = $repositoryProvider;
        $this->domain = $domain;
        $this->router = $router;
        $this->defaultDbClient = $defaultDbClient;
        $this->mailer = $mailer;
    }

    public function signUp($email, $pass)
    {
        $hash = $this->encodePassword($pass);
        $emailConfirmationCode = hash('sha256', $email . microtime());
        $user = User::create($email, $hash, $emailConfirmationCode);
        $emailConfirmationLink = 'https:'.$this->router->generate(
                UserController::ROUTE_NAME_LOGIN,
                ['code' => $user->emailConfirmationCode],
                UrlGeneratorInterface::NETWORK_PATH
            );

        try {
            $this->defaultDbClient->beginTransaction();
            $this->repositoryProvider->get(User::class)->create($user);
            $this->mailer->createForUser(
                $user->id,
                Mailer::LETTER_NAME_CONFIRMATION,
                ['link' => $emailConfirmationLink]
            );
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function getUser(): ?User
    {
        if (null === $this->user) {
            throw new RuntimeException('No user');
        }

        return $this->user;
    }

    public function isPasswordCorrect(string $password): bool
    {
        $user = $this->getUser();
        $hash = $this->encodePassword($password);

        return $hash === $user->pass;
    }

    private function encodePassword(string $password): string
    {
        $encodedPass = hash('sha256', $password . $this->salt);

        return $encodedPass;
    }
}
