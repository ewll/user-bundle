<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Entity\OauthToken;
use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Form\FormErrorResponse;
use Ewll\UserBundle\Oauth\Exception\WrongCodeException;
use Ewll\UserBundle\Oauth\OauthInterface;
use Ewll\UserBundle\PageDataCompiler;
use Ewll\UserBundle\Twofa\Exception\EmptyTwofaCodeException;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaHandler;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
    private $translator;
    private $domain;

    public function __construct(
        iterable $oauths,
        PageDataCompiler $pageDataCompiler,
        RepositoryProvider $repositoryProvider,
        Authenticator $authenticator,
        TwofaHandler $twofaHandler,
        TranslatorInterface $translator,
        string $domain
    ) {
        $this->oauths = $oauths;
        $this->pageDataCompiler = $pageDataCompiler;
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->twofaHandler = $twofaHandler;
        $this->translator = $translator;
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
            'isUserExists' => false,
            'hasTwofa' => false,
            'twofaActionId' => null,
            'isStoredTwofaCode' => false
        ];
        $oauth = $this->getOauthServiceByName($name);
        try {
            $email = $oauth->getEmailByCode($code);
            $token = hash('sha256', microtime() . $email . uniqid());
            $oauthToken = OauthToken::create($email, $token, $request->getClientIp());
            $this->repositoryProvider->get(OauthToken::class)->create($oauthToken);
            $jsConfig['oauthToken'] = $oauthToken->token;
            /** @var User|null $user */
            $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $email]);
            if (null !== $user) {
                $jsConfig['isUserExists'] = true;
                if ($user->hasTwofa()) {
                    $jsConfig['hasTwofa'] = true;
                    $jsConfig['twofaTypeId'] = $user->twofaTypeId;
                    $twofa = $this->twofaHandler->getTwofaServiceByTypeId($user->twofaTypeId);
                    $jsConfig['isStoredTwofaCode'] = $twofa instanceof StoredKeyTwofaInterface;
                } else {
                    $this->authenticator->login($user, $request->getClientIp());
                    $redirect = $this->authenticator->getRedirectUrlAfterLogin($user);

                    return $this->redirect($redirect);
                }
            }
        } catch (WrongCodeException $e) {
            $jsConfig['isCodeWrong'] = true;
        }

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_OAUTH, $jsConfig);
    }

    public function signup(Request $request)
    {
        $form = $this->pageDataCompiler
            ->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_OAUTH_SIGNUP);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var OauthToken $oauthToken */
            $oauthToken = $data['token'];
            $pass = $data['pass'];
            $user = $this->authenticator->signupByOauth($oauthToken, $pass, $request->getClientIp());
            $this->authenticator->login($user, $request->getClientIp());
            $redirect = $this->authenticator->getRedirectUrlAfterLogin($user);

            return new JsonResponse(['redirect' => $redirect]);
        }

        return new FormErrorResponse($form);
    }

    public function login(Request $request)
    {
        $form = $this->pageDataCompiler
            ->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_OAUTH_LOGIN);

        if ($form->isValid()) {
            $data = $form->getData();
            /** @var OauthToken $oauthToken */
            $oauthToken = $data['token'];
            /** @var User|null $user */
            $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $oauthToken->email]);
            if (!$user->hasTwofa()) {
                throw new RuntimeException('Twofa must be here');
            }
            try {
                $this->twofaHandler->checkCode($user, $form, TwofaCode::ACTION_ID_LOGIN);
                $this->authenticator->login($user, $request->getClientIp());
                $redirect = $this->authenticator->getRedirectUrlAfterLogin($user);

                return new JsonResponse(['redirect' => $redirect]);
            } catch (EmptyTwofaCodeException $e) {
                $message = $this->translator->trans('twofa.code.empty', [], 'validators');
                $form->get('twofaCode')->addError(new FormError($message, null, [], null, $e));
            } catch (IncorrectTwofaCodeException $e) {
                $message = $this->translator->trans('twofa.code.incorrect', [], 'validators');
                $form->get('twofaCode')->addError(new FormError($message, null, [], null, $e));
            }
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
