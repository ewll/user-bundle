<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\CannotConfirmEmailException;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Captcha\CaptchaProvider;
use Ewll\UserBundle\Entity\Token;
use App\Entity\User;
use Ewll\UserBundle\Form\FormErrorResponse;
use Ewll\UserBundle\PageDataCompiler;
use Ewll\UserBundle\Token\Exception\ActiveTokenExistsException;
use Ewll\UserBundle\Token\Exception\TokenNotFoundException;
use Ewll\UserBundle\Token\Item as TokenItem;
use Ewll\UserBundle\Token\Item\RecoverPassToken;
use Ewll\UserBundle\Token\TokenProvider;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    const ROUTE_NAME_LOGIN_PAGE = 'login.page';
    const ROUTE_NAME_RECOVERING_FINISH_PAGE = 'passwordRecovering.finishPage';

    private $authenticator;
    private $repositoryProvider;
    private $pageDataCompiler;
    private $twofaHandler;
    private $captchaProvider;
    private $tokenProvider;
    private $translator;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        PageDataCompiler $pageDataCompiler,
        TwofaHandler $twofaHandler,
        CaptchaProvider $captchaProvider,
        TokenProvider $tokenProvider,
        TranslatorInterface $translator
    ) {
        $this->authenticator = $authenticator;
        $this->repositoryProvider = $repositoryProvider;
        $this->pageDataCompiler = $pageDataCompiler;
        $this->twofaHandler = $twofaHandler;
        $this->captchaProvider = $captchaProvider;
        $this->tokenProvider = $tokenProvider;
        $this->translator = $translator;
    }

    public function loginPage(string $tokenCode = null)
    {
        try {
            $this->authenticator->getUser();

            return $this->redirect('/private');
        } catch (NotAuthorizedException $e) {
        }

        $jsConfig = ['emailConfirmed' => false];
        if (!empty($tokenCode)) {
            try {
                $this->authenticator->confirmEmail($tokenCode);
                $jsConfig['emailConfirmed'] = true;
            } catch (CannotConfirmEmailException $e) {
            }
        }

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_LOGIN, $jsConfig);
    }

    public function login(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_LOGIN);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var User $user */
            $user = $data['email'];
            $tokenData = ['userId' => $user->id];
            $tokenItemClass = $user->hasTwofa() ? TokenItem\AuthToken::class : TokenItem\TwofaSetToken::class;
            $token = $this->tokenProvider->generate($tokenItemClass, $tokenData, $request->getClientIp());
            $redirectUrl = $this->tokenProvider->compileTokenPageUrl($token);

            return new JsonResponse(['redirect' => $redirectUrl]);
        }

        return new FormErrorResponse($form);
    }

    public function signupPage()
    {
        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_SIGNUP);
    }

    public function passwordRecoveringPage()
    {
        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_INIT_RECOVERING);
    }

    public function passwordRecoveringInit(Request $request)
    {
        $form = $this->pageDataCompiler
            ->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_RECOVERING_INIT);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var User $user */
            $user = $data['email'];
            try {
                $this->authenticator->recoveringInit($user, $request->getClientIp());

                return new JsonResponse([]);
            } catch (ActiveTokenExistsException $e) {
                $error = $this->translator->trans('recovering.old-key-is-active', [], 'validators');
                $form->addError(new FormError($error));
            }
        }

        return new FormErrorResponse($form);
    }

    public function passwordRecoveringFinishPage(string $tokenCode)
    {
        try {
            $this->tokenProvider->getByCode($tokenCode, RecoverPassToken::TYPE_ID);
            $isUserRecoveryFound = true;
        } catch (TokenNotFoundException $e) {
            $isUserRecoveryFound = false;
        }
        $jsConfig = ['isUserRecoveryFound' => $isUserRecoveryFound, 'tokenCode' => $tokenCode];

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_FINISH_RECOVERING, $jsConfig);
    }

    public function passwordRecoveringRecover(Request $request)
    {
        $form = $this->pageDataCompiler
            ->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_RECOVERING_FINISH);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var Token $token */
            $token = $data['token'];
            $this->authenticator->recover($token, $data['pass'], $request->getClientIp());

            return new JsonResponse([]);
        }

        return new FormErrorResponse($form);
    }

    public function signup(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_SIGNUP);
        if ($form->isValid()) {
            $data = $form->getData();
            $this->authenticator->signup($data['email'], $data['pass'], $request->getClientIp());

            return new JsonResponse([]);
        }

        return new FormErrorResponse($form);
    }

    public function exit(Request $request)
    {
        $this->authenticator->exit($request->getClientIp());

        return $this->redirect('/login');
    }
}
