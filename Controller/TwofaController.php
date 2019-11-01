<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Entity\Token;
use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Entity\User;
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
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        CodeToTokenTransformer $codeToTokenTransformer
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
    }

    public function page(string $tokenCode)
    {
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
            'twofaActionId' => null,
            'isStoredTwofaCode' => false
        ];
        try {
            $token = $this->tokenProvider->getByCode($tokenCode, AuthToken::TYPE_ID);
            /** @var User $user */
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
            if (!$user->hasTwofa()) {
                throw new LogicException('Expected twofa here');
            }
            $jsConfig['twofaTypeId'] = $user->twofaTypeId;
            $twofa = $this->twofaHandler->getTwofaServiceByTypeId($user->twofaTypeId);
            $jsConfig['isStoredTwofaCode'] = $twofa instanceof StoredKeyTwofaInterface;
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
            ])
        ;
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
                    $this->twofaHandler->provideCode($user, $twofaService, $contact, TwofaCode::ACTION_ID_ENROLL);;

                    return new JsonResponse([]);
                } catch (CannotProvideCodeException $e) {
                    switch ($e->getCode()) {
                        case CannotProvideCodeException::CODE_RECIPIENT_NOT_EXISTS:
                            $error = 'twofa.message.recipient-not-exists';
                            break;
                        case CannotProvideCodeException::CODE_HAVE_ACTIVE:
                            $error = 'twofa.message.have-active';
                            break;
                        default:
                            $error = 'twofa.message.cannot-send';
                    }
                    //@todo непонятно почему не переводится автоматически...
                    $form->addError(new FormError($this->translator->trans($error, [], 'validators')));
                }
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
            $this->provideCodeByUserAndForm($user, $form);
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
                    $this->twofaHandler->setTwofa($user, $twofaService, $code, TwofaCode::ACTION_ID_ENROLL, $context);
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

    private function provideCodeByUserAndForm(User $user, FormInterface $form): FormInterface
    {
        try {
            if ($user->hasTwofa()) {
                /** @var StoredKeyTwofaInterface $twofa */
                $twofa = $this->twofaHandler->getTwofaServiceByTypeId($user->twofaTypeId, true);
                if (!$twofa instanceof StoredKeyTwofaInterface) {
                    throw new RuntimeException('Only StoredKeyTwofaInterface must be here');
                }
                $this->twofaHandler->provideCode($user, $twofa, $user->twofaData['contact'], TwofaCode::ACTION_ID_LOGIN);

                return $form;
            } else {
                $form->addError(new FormError($this->translator->trans('twofa.has-no', [], 'validators')));
            }
        } catch (CannotProvideCodeException $e) {
            switch ($e->getCode()) {
                case CannotProvideCodeException::CODE_RECIPIENT_NOT_EXISTS:
                    $error = 'twofa.message.recipient-not-exists';
                    break;
                case CannotProvideCodeException::CODE_HAVE_ACTIVE:
                    $error = 'twofa.message.have-active';
                    break;
                default:
                    $error = 'twofa.message.cannot-send';
            }
            $form->addError(new FormError($this->translator->trans($error, [], 'validators')));
        }

        return $form;
    }
}
