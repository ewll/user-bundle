<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Entity\Token;
use Ewll\UserBundle\Entity\TwofaCode;
use App\Entity\User;
use Ewll\UserBundle\Form\Constraints\CsrfToken;
use Ewll\UserBundle\Form\Constraints\TokenType;
use Ewll\UserBundle\Form\DataTransformer\CodeToTokenTransformer;
use Ewll\UserBundle\Form\DataTransformer\TwofaTypeToServiceTransformer;
use Ewll\UserBundle\Form\FormErrorResponse;
use Ewll\UserBundle\PageDataCompiler;
use Ewll\UserBundle\Token\Exception\TokenNotFoundException;
use Ewll\UserBundle\Token\Item\AuthToken;
use Ewll\UserBundle\Token\Item\TwofaSetToken;
use Ewll\UserBundle\Token\TokenProvider;
use Ewll\UserBundle\Twofa\Exception\EmptyTwofaCodeException;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\Item\GoogleTwofa;
use Ewll\UserBundle\Twofa\JsConfigCompiler;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwofaController extends AbstractController
{
    const ROUTE_NAME_PAGE_SET = 'twofa.page.set';
    const ROUTE_NAME_PAGE_LOGIN_CONFIRMATION = 'twofa.page.login-confirm';

    private $authenticator;
    private $pageDataCompiler;
    private $twofaHandler;
    private $twofaTypeToServiceTransformer;
    private $translator;
    private $googleTwofa;
    private $repositoryProvider;
    private $tokenProvider;
    private $telegramBotName;
    private $domain;
    private $codeToTokenTransformer;
    private $jsConfigCompiler;
    private $actions;
    private $redirect;

    public function __construct(
        Authenticator $authenticator,
        PageDataCompiler $pageDataCompiler,
        TwofaHandler $twofaHandler,
        TwofaTypeToServiceTransformer $twofaTypeToServiceTransformer,
        TranslatorInterface $translator,
        GoogleTwofa $googleTwofa,
        RepositoryProvider $repositoryProvider,
        TokenProvider $tokenProvider,
        string $telegramBotName,
        string $domain,
        CodeToTokenTransformer $codeToTokenTransformer,
        JsConfigCompiler $jsConfigCompiler,
        array $actions,
        string $redirect
    ) {
        $this->authenticator = $authenticator;
        $this->pageDataCompiler = $pageDataCompiler;
        $this->twofaHandler = $twofaHandler;
        $this->twofaTypeToServiceTransformer = $twofaTypeToServiceTransformer;
        $this->translator = $translator;
        $this->googleTwofa = $googleTwofa;
        $this->repositoryProvider = $repositoryProvider;
        $this->tokenProvider = $tokenProvider;
        $this->telegramBotName = $telegramBotName;
        $this->domain = $domain;
        $this->codeToTokenTransformer = $codeToTokenTransformer;
        $this->jsConfigCompiler = $jsConfigCompiler;
        $this->actions = $actions;
        $this->redirect = $redirect;
    }

    public function code(Request $request, int $actionId)
    {
        $formBuilder = $this->createFormBuilder(null, ['constraints' => [new CsrfToken()]]);
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));

        if ($form->isValid()) {
            $isActionExists = false;
            foreach ($this->actions as $action) {
                if ($action['id'] === $actionId) {
                    $isActionExists = true;
                }
            }
            if ($isActionExists) {
                try {
                    $user = $this->authenticator->getUser();
                    $this->twofaHandler->provideCodeToUser($user, $actionId);

                    return new JsonResponse([]);
                } catch (NotAuthorizedException $e) {
                    throw new LogicException('User must be here');
                } catch (CannotProvideCodeException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            } else {
                $form->addError(new FormError($this->translator->trans('twofa.action.not-exists', [], 'validators')));
            }
        }

        return new FormErrorResponse($form);
    }

    public function page(string $tokenCode)
    {
        $jsConfig = ['redirect' => $this->redirect];
        try {
            $token = $this->tokenProvider->getByCode($tokenCode, TwofaSetToken::TYPE_ID);
        } catch (TokenNotFoundException $e) {
            return $this->redirect('/login');
        }
        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
        if ($user->hasTwofa()) {
            throw new RuntimeException('User already have twofa');
        }
        $googleSecret = $this->googleTwofa->generateSecret();
        $googleSecretUrl = $this->googleTwofa->getSecretUrl($user->email, $this->domain, $googleSecret);

        $jsConfig['tokenCode'] = $tokenCode;
        $jsConfig['telegramBotName'] = $this->telegramBotName;
        $jsConfig['googleSecret'] = $googleSecret;
        $jsConfig['googleSecretUrl'] = $googleSecretUrl;

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_TWOFA, $jsConfig);
    }

    public function loginConfirmPage(string $tokenCode)
    {
        $jsConfig = [
            'tokenCode' => $tokenCode,
            'isCodeWrong' => false,
            'isStoredTwofaCode' => false,
            'redirect' => $this->redirect,
        ];
        try {
            $token = $this->tokenProvider->getByCode($tokenCode, AuthToken::TYPE_ID);
            /** @var User $user */
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
            $jsConfig['isStoredTwofaCode'] = $this->jsConfigCompiler->isStoredTwofaCode($user);
            $jsConfig['twofaTypeId'] = $user->twofaTypeId;
        } catch (TokenNotFoundException $e) {
            $jsConfig['isCodeWrong'] = true;
        }

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_TWOFA_LOGIN_CONFIRMATION, $jsConfig);
    }

    public function enrollCode(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('contact', IntegerType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('type', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('token', TextType::class, [
                'constraints' => [new NotBlank(), new TokenType(TwofaSetToken::TYPE_ID)],
            ]);
        $formBuilder->get('type')->addModelTransformer($this->twofaTypeToServiceTransformer);
        $formBuilder->get('token')->addViewTransformer($this->codeToTokenTransformer);
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));
        if ($form->isValid()) {
            $data = $form->getData();
            $contact = $data['contact'];
            $twofaService = $data['type'];
            /** @var Token $token */
            $token = $data['token'];
            if (!$twofaService instanceof StoredKeyTwofaInterface) {
                throw new RuntimeException('Only StoredKeyTwofaInterface must be here');
            }
            /** @var User $user */
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
            if ($user->hasTwofa()) {
                $form->addError(new FormError($this->translator->trans('twofa.already-has', [], 'validators')));
            } else {
                try {
                    $this->twofaHandler->provideCodeToContact($user, $twofaService, $contact,
                        TwofaCode::ACTION_ID_ENROLL);;
                } catch (CannotProvideCodeException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }
            if ($form->isValid()) {
                return new JsonResponse([]);
            }
        }

        return new FormErrorResponse($form);
    }

    public function loginCode(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_LOGIN_CODE);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var Token $token */
            $token = $data['token'];
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
            try {
                $this->twofaHandler->provideCodeToUser($user, TwofaCode::ACTION_ID_LOGIN);
            } catch (CannotProvideCodeException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
            if ($form->isValid()) {
                return new JsonResponse([]);
            }
        }

        return new FormErrorResponse($form);
    }

    public function login(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_TWOFA_LOGIN);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var Token $token */
            $token = $data['token'];
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
            $this->authenticator->login($user, $request->getClientIp());
            $this->tokenProvider->toUse($token);

            return new JsonResponse([]);
        }

        return new FormErrorResponse($form);
    }

    public function enroll(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('code', IntegerType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('type', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('context', TextType::class)
            ->add('token', TextType::class, [
                'constraints' => [new NotBlank(), new TokenType(TwofaSetToken::TYPE_ID)],
            ]);
        $formBuilder->get('type')->addModelTransformer($this->twofaTypeToServiceTransformer);
        $formBuilder->get('token')->addViewTransformer($this->codeToTokenTransformer);
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));

        if ($form->isValid()) {
            $data = $form->getData();
            $code = $data['code'];
            $context = $data['context'];
            $twofaService = $data['type'];
            /** @var Token $token */
            $token = $data['token'];
            /** @var User $user */
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
            if ($user->hasTwofa()) {
                $form->addError(new FormError($this->translator->trans('twofa.already-has', [], 'validators')));
            } else {
                try {
                    $this->twofaHandler->setTwofa($user, $twofaService, $code, $context);
                    $this->tokenProvider->toUse($token);
                    $this->authenticator->login($user, $request->getClientIp());

                    return new JsonResponse([]);
                } catch (EmptyTwofaCodeException $e) {
                    $form->get('code')
                        ->addError(new FormError($this->translator->trans('twofa.code.empty', [], 'validators')));
                } catch (IncorrectTwofaCodeException $e) {
                    $form->get('code')
                        ->addError(new FormError($this->translator->trans('twofa.code.incorrect', [], 'validators')));
                }
            }
        }

        return new FormErrorResponse($form);
    }
}
