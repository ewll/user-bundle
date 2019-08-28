<?php namespace Ewll\UserBundle\Authenticator;

use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\MailerBundle\Mailer;
use Ewll\UserBundle\Authenticator\Exception\CannotConfirmEmailException;
use Ewll\UserBundle\Controller\UserController;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Entity\UserSession;
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
        $emailConfirmationLink = 'https:' . $this->router->generate(
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

    public function login(User $user): void
    {
        $time = microtime();
        $crypt = hash('sha256', uniqid() . $this->salt . $time . $user->email);
        $token = hash('sha256', $user->email . $time . $this->salt . uniqid());
        $userSession = UserSession::create($user->id, $crypt, $token);
        $this->repositoryProvider->get(UserSession::class)->create($userSession);

        $this->setSessionCookie($crypt, 86400 * 10);
    }

    public function exit()
    {
        //@TODO drop db session
        $this->setSessionCookie('', -3600);
    }

    public function isSigned(string $cookie = null)
    {
        if (null === $cookie) {
            return false;
        }

        /** @var UserSession|null $userSession */
        $userSession = $this->repositoryProvider->get(UserSession::class)->findOneBy(['crypt' => $cookie]);
        if ($userSession !== null) {
            $this->user = $this->repositoryProvider->get(User::class)->findById($userSession->userId);
            $this->user->token = $userSession->token;
        }

        return null !== $this->user;
    }

    public function getUser(): ?User
    {
        if (null === $this->user) {
            throw new RuntimeException('No user');
        }

        return $this->user;
    }

    public function encodePassword(string $password): string
    {
        $encodedPass = hash('sha256', $password . $this->salt);

        return $encodedPass;
    }

    /** @throws CannotConfirmEmailException */
    public function confirmEmail(string $code)
    {
        /** @var User|null $user */
        $user = $this->repositoryProvider->get(User::class)->findOneBy(['emailConfirmationCode' => $code]);
        if ($user === null) {
            throw new CannotConfirmEmailException();
        }
        $user->isEmailConfirmed = true;
        $user->emailConfirmationCode = null;
        $this->repositoryProvider->get(User::class)->update($user, ['isEmailConfirmed', 'emailConfirmationCode']);
    }

    private function setSessionCookie($value, $duration)
    {
        SetCookie('s', $value, time() + $duration, '/', $this->domain, true, true);
    }
}
