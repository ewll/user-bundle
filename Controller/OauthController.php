<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Entity\Token;
use App\Entity\User;
use Ewll\UserBundle\Form\FormErrorResponse;
use Ewll\UserBundle\Oauth\Exception\EmailNotReceivedException;
use Ewll\UserBundle\Oauth\Exception\WrongCodeException;
use Ewll\UserBundle\Oauth\OauthInterface;
use Ewll\UserBundle\PageDataCompiler;
use Ewll\UserBundle\Token\Item as TokenItem;
use Ewll\UserBundle\Token\Item\OAuthRegToken;
use Ewll\UserBundle\Token\TokenProvider;
use Ewll\UserBundle\Twofa\TwofaHandler;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class OauthController extends AbstractController
{
    const SESSION_COOKIE_NAME = 'os';
    const ROUTE_NAME_OAUTH = 'oauth';

    /** @var OauthInterface[] */
    private $oauths;
    private $pageDataCompiler;
    private $repositoryProvider;
    private $authenticator;
    private $twofaHandler;
    private $tokenProvider;
    private $translator;
    private $logger;
    private $domain;

    public function __construct(
        iterable $oauths,
        PageDataCompiler $pageDataCompiler,
        RepositoryProvider $repositoryProvider,
        Authenticator $authenticator,
        TwofaHandler $twofaHandler,
        TokenProvider $tokenProvider,
        TranslatorInterface $translator,
        Logger $logger,
        string $domain
    ) {
        $this->oauths = $oauths;
        $this->pageDataCompiler = $pageDataCompiler;
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->twofaHandler = $twofaHandler;
        $this->tokenProvider = $tokenProvider;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->domain = $domain;
    }

    public function oauth(Request $request, $name)
    {
        $code = $request->query->get('code');
        if (null === $code) {
            throw new NotFoundHttpException();
        }
        $jsConfig = [
            'isCodeWrong' => false,
            'isEmailNotReceived' => false,
            'tokenCode' => null,
        ];
        $oauth = $this->getOauthServiceByName($name);
        try {
            $email = $oauth->getEmailByCode($code);
            /** @var User|null $user */
            $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $email]);
            if (null === $user) {
                $tokenData = ['email' => $email];
                $token = $this->tokenProvider->generate(OAuthRegToken::class, $tokenData, $request->getClientIp());
                $jsConfig['tokenCode'] = $this->tokenProvider->compileTokenCode($token);
            } else {
                $tokenData = ['userId' => $user->id];
                $tokenItemClass = $user->hasTwofa() ? TokenItem\AuthToken::class : TokenItem\TwofaSetToken::class;
                $token = $this->tokenProvider->generate($tokenItemClass, $tokenData, $request->getClientIp());
                $redirectUrl = $this->tokenProvider->compileTokenPageUrl($token);

                return $this->redirect($redirectUrl);
            }
        } catch (WrongCodeException $e) {
            $jsConfig['isCodeWrong'] = true;
        } catch (EmailNotReceivedException $e) {
            $jsConfig['isEmailNotReceived'] = true;
        }

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_OAUTH, $jsConfig);
    }

    public function signup(Request $request)
    {
        $form = $this->pageDataCompiler
            ->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_OAUTH_SIGNUP);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var Token $token */
            $token = $data['token'];
            $pass = $data['pass'];
            $user = $this->authenticator->signupByOauth($token, $pass, $request->getClientIp());
            $tokenData = ['userId' => $user->id];
            $tokenItemClass = $user->hasTwofa() ? TokenItem\AuthToken::class : TokenItem\TwofaSetToken::class;
            $token = $this->tokenProvider->generate($tokenItemClass, $tokenData, $request->getClientIp());
            $redirectUrl = $this->tokenProvider->compileTokenPageUrl($token);

            return new JsonResponse(['redirect' => $redirectUrl]);
        }

        return new FormErrorResponse($form);
    }

    private function getOauthServiceByName(string $name): OauthInterface
    {
        foreach ($this->oauths as $oauth) {
            if ($oauth->getType() === $name) {
                return $oauth;
            }
        }

        throw new RuntimeException('Oauth service not found');
    }
}
