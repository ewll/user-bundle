<?php namespace Ewll\UserBundle\Authenticator;

use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\MailerBundle\Mailer;
use Ewll\MailerBundle\Template;
use Ewll\UserBundle\Authenticator\Exception\CannotConfirmEmailException;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Controller\UserController;
use Ewll\UserBundle\Entity\OauthToken;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Entity\UserRecovery;
use Ewll\UserBundle\Entity\UserSession;
use Ewll\UserBundle\EwllUserBundle;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class Authenticator
{
    const SESSION_COOKIE_NAME = 's';
    const LETTER_NAME_EMAIL_CONFIRMATION = 'letterEmailConfirmation';
    const LETTER_NAME_RECOVERING = 'letterRecoveringPassword';

    private $repositoryProvider;
    private $domain;
    private $router;
    private $mailer;
    private $requestStack;
    private $defaultDbClient;
    private $salt;

    /** @var User */
    private $user;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        string $domain,
        Router $router,
        DbClient $defaultDbClient,
        Mailer $mailer,
        RequestStack $requestStack,
        string $salt
    ) {
        $this->salt = $salt;
        $this->repositoryProvider = $repositoryProvider;
        $this->domain = $domain;
        $this->router = $router;
        $this->defaultDbClient = $defaultDbClient;
        $this->mailer = $mailer;
        $this->requestStack = $requestStack;
    }

    public function signup(string $email, string $pass, string $ip)
    {
        $hash = $this->encodePassword($pass);
        $emailConfirmationCode = hash('sha256', $email . microtime());
        $user = User::create($email, $hash, $ip, false, $emailConfirmationCode);
        $emailConfirmationLink = 'https:' . $this->router->generate(
                UserController::ROUTE_NAME_LOGIN_PAGE,
                ['code' => $user->emailConfirmationCode],
                UrlGeneratorInterface::NETWORK_PATH
            );
        $template = new Template(
            self::LETTER_NAME_EMAIL_CONFIRMATION,
            EwllUserBundle::BUNDLE_NAME,
            ['link' => $emailConfirmationLink]
        );
        try {
            $this->defaultDbClient->beginTransaction();
            $this->repositoryProvider->get(User::class)->create($user);
            $this->mailer->createForUser($user, $template, false);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function signupByOauth(OauthToken $oauthToken, string $pass, string $ip): User
    {
        $hash = $this->encodePassword($pass);
        $user = User::create($oauthToken->email, $hash, $ip, true);
        try {
            $this->defaultDbClient->beginTransaction();
            $this->repositoryProvider->get(User::class)->create($user);
            $this->repositoryProvider->get(OauthToken::class)->delete($user);
            $this->defaultDbClient->commit();

            return $user;
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function login(User $user, string $ip): void
    {
        $time = microtime();
        $crypt = hash('sha256', uniqid() . $this->salt . $time . $user->email);
        $token = hash('sha256', $user->email . $time . $this->salt . uniqid());
        $userSession = UserSession::create($user->id, $crypt, $token, $ip);
        $this->repositoryProvider->get(UserSession::class)->create($userSession);

        $this->setSessionCookie($crypt, 86400 * 10);
    }

    public function recoveringInit(User $user, string $ip): void
    {
        $time = microtime();
        $code = hash('sha256', $time . uniqid() . $this->salt . $user->email);
        $userRecovery = UserRecovery::create($user->id, $code, $ip);
        $recoveringLink = 'https:' . $this->router->generate(
                UserController::ROUTE_NAME_RECOVERING_FINISH_PAGE,
                ['code' => $code],
                UrlGeneratorInterface::NETWORK_PATH
            );
        $template = new Template(
            self::LETTER_NAME_RECOVERING,
            EwllUserBundle::BUNDLE_NAME,
            ['link' => $recoveringLink]
        );
        try {
            $this->defaultDbClient->beginTransaction();
            $this->repositoryProvider->get(UserRecovery::class)->create($userRecovery);
            $this->mailer->createForUser($user, $template);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function exit()
    {
        //@TODO drop db session
        $this->setSessionCookie('', -3600);
    }

    /** @throws NotAuthorizedException */
    public function getUser(): User
    {
        if (null !== $this->user) {
            return $this->user;
        }

        $sessionKey = $this->requestStack->getCurrentRequest()->cookies->get(self::SESSION_COOKIE_NAME);
        if (null === $sessionKey) {
            throw new NotAuthorizedException();
        }

        /** @var UserSession|null $userSession */
        $userSession = $this->repositoryProvider->get(UserSession::class)->findOneBy(['crypt' => $sessionKey]);
        if ($userSession === null) {
            throw new NotAuthorizedException();
        }

        $this->user = $this->repositoryProvider->get(User::class)->findById($userSession->userId);
        $this->user->session = $userSession;

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

    public function recover(UserRecovery $userRecovery, string $pass)
    {
        $userRepository = $this->repositoryProvider->get(User::class);
        /** @var User $user */
        $user = $userRepository->findById($userRecovery->userId);
        $user->pass = $this->encodePassword($pass);
        $userRecovery->isUsed = true;
        try {
            $this->defaultDbClient->beginTransaction();
            $userRepository->update($user, ['pass']);
            $this->repositoryProvider->get(UserRecovery::class)->update($userRecovery, ['isUsed']);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function getRedirectUrlAfterLogin(User $user)
    {
        return $user->hasTwofa() ? '/private' : '/2fa';
    }

    private function setSessionCookie($value, $duration)
    {
        SetCookie(self::SESSION_COOKIE_NAME, $value, time() + $duration, '/', $this->domain, true, true);
    }
}
