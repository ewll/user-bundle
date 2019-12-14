<?php namespace Ewll\UserBundle\Authenticator;

use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\MailerBundle\Mailer;
use Ewll\MailerBundle\Template;
use Ewll\UserBundle\Authenticator\Exception\CannotConfirmEmailException;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Entity\Token;
use App\Entity\User;
use Ewll\UserBundle\EwllUserBundle;
use Ewll\UserBundle\Token\Exception\ActiveTokenExistsException;
use Ewll\UserBundle\Token\Exception\TokenNotFoundException;
use Ewll\UserBundle\Token\Item\ConfirmEmailToken;
use Ewll\UserBundle\Token\Item\RecoverPassToken;
use Ewll\UserBundle\Token\Item\UserSessionToken;
use Ewll\UserBundle\Token\TokenProvider;
use Exception;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\RequestStack;
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
    private $tokenProvider;
    private $logger;
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
        TokenProvider $tokenProvider,
        Logger $logger,
        string $salt
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->domain = $domain;
        $this->router = $router;
        $this->defaultDbClient = $defaultDbClient;
        $this->mailer = $mailer;
        $this->requestStack = $requestStack;
        $this->tokenProvider = $tokenProvider;
        $this->logger = $logger;
        $this->salt = $salt;
    }

    public function signup(string $email, string $pass, string $ip)
    {
        $this->defaultDbClient->beginTransaction();
        try {
            $hash = $this->encodePassword($pass);
            $user = User::create($email, $hash, $ip, false);
            $this->repositoryProvider->get(User::class)->create($user);
            $tokenData = ['userId' => $user->id];
            $token = $this->tokenProvider->generate(ConfirmEmailToken::class, $tokenData, $ip);
            $emailConfirmationLink = $this->tokenProvider->compileTokenPageUrl($token);
            $template = new Template(
                self::LETTER_NAME_EMAIL_CONFIRMATION,
                EwllUserBundle::BUNDLE_NAME,
                ['link' => $emailConfirmationLink]
            );
            $this->mailer->createForUser($user, $template, false);
            $this->logger->info('Success signup', ['userId' => $user->id, 'ip' => $ip]);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function signupByOauth(Token $token, string $pass, string $ip): User
    {
        $hash = $this->encodePassword($pass);
        $user = User::create($token->data['email'], $hash, $ip, true);
        $this->defaultDbClient->beginTransaction();
        try {
            $this->repositoryProvider->get(User::class)->create($user);
            $this->tokenProvider->toUse($token);
            $this->defaultDbClient->commit();
            $this->logger->info('Success signup by oauth', ['userId' => $user->id, 'ip' => $ip]);
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
        return $user;
    }

    public function login(User $user, string $ip): void
    {
        $tokenData = [
            'userId' => $user->id,
            'csrf' => hash('sha256', $user->email . time() . $this->salt . uniqid()),
        ];
        $token = $this->tokenProvider->generate(UserSessionToken::class, $tokenData, $ip);
        $this->setSessionCookie($this->tokenProvider->compileTokenCode($token), UserSessionToken::LIFE_TIME * 60);
        $this->logger->info('Success login', ['userId' => $user->id, 'ip' => $ip]);
    }

    /** @throws ActiveTokenExistsException */
    public function recoveringInit(User $user, string $ip): void
    {
        $this->defaultDbClient->beginTransaction();
        try {
            $tokenData = ['userId' => $user->id];
            $token = $this->tokenProvider->generate(RecoverPassToken::class, $tokenData, $ip, true);
            $recoveringLink = $this->tokenProvider->compileTokenPageUrl($token);
            $template = new Template(
                self::LETTER_NAME_RECOVERING,
                EwllUserBundle::BUNDLE_NAME,
                ['link' => $recoveringLink]
            );
            $this->mailer->createForUser($user, $template);
            $this->defaultDbClient->commit();
            $this->logger->info('Recovering has initialised', ['userId' => $user->id, 'ip' => $ip]);
        } catch (ActiveTokenExistsException|Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function recover(Token $token, string $pass, string $ip)
    {
        $userRepository = $this->repositoryProvider->get(User::class);
        /** @var User $user */
        $user = $userRepository->findById($token->data['userId']);
        $user->pass = $this->encodePassword($pass);
        try {
            $this->defaultDbClient->beginTransaction();
            $userRepository->update($user, ['pass']);
            $this->tokenProvider->toUse($token);
            $this->defaultDbClient->commit();
            $this->logger->info('Pass recovered', ['userId' => $user->id, 'ip' => $ip]);
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function exit(string $ip)
    {
        $this->setSessionCookie('', -3600);
        try {
            $user = $this->getUser();
            $this->tokenProvider->toUse($user->token);
            $this->logger->info('Success exit', ['userId' => $user->id, 'ip' => $ip]);
        } catch (NotAuthorizedException $e) {
        }
    }

    /** @throws NotAuthorizedException */
    public function getUser(): User
    {
        if (null !== $this->user) {
            return $this->user;
        }

        try {
            $tokenCode = $this->requestStack->getCurrentRequest()->cookies->get(self::SESSION_COOKIE_NAME);
            if (null === $tokenCode) {
                throw new NotAuthorizedException('Token cookie missed');
            }

            try {
                $token = $this->tokenProvider->getByCode($tokenCode, UserSessionToken::TYPE_ID);
            } catch (TokenNotFoundException $e) {
                throw new NotAuthorizedException('Token not found');
            }
        } catch (NotAuthorizedException $e) {
            $this->logger->warning("NotAuthorizedException: {$e->getMessage()}");

            throw $e;
        }
        $this->user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
        $this->user->token = $token;

        return $this->user;
    }

    public function encodePassword(string $password): string
    {
        $encodedPass = hash('sha256', $password . $this->salt);

        return $encodedPass;
    }

    /** @throws CannotConfirmEmailException */
    public function confirmEmail(string $tokenCode)
    {
        try {
            $token = $this->tokenProvider->getByCode($tokenCode, ConfirmEmailToken::TYPE_ID);
        } catch (TokenNotFoundException $e) {
            throw new CannotConfirmEmailException();
        }
        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
        $user->isEmailConfirmed = true;
        $this->defaultDbClient->beginTransaction();
        try {
            $this->repositoryProvider->get(User::class)->update($user, ['isEmailConfirmed']);
            $this->tokenProvider->toUse($token);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    /** @deprecated */
    public function getRedirectUrlAfterLogin(User $user)
    {
        return $user->hasTwofa() ? '/private' : '/2fa';
    }

    private function setSessionCookie($value, $duration)
    {
        SetCookie(self::SESSION_COOKIE_NAME, $value, time() + $duration, '/', $this->domain, true, true);
    }
}
