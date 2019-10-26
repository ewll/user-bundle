<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Entity\OauthToken;
use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Form\Constraints\CsrfToken;
use Ewll\UserBundle\Form\Constraints\UserHasNoTwofa;
use Ewll\UserBundle\Form\DataTransformer\TwofaTypeToServiceTransformer;
use Ewll\UserBundle\Form\FormErrorResponse;
use Ewll\UserBundle\PageDataCompiler;
use Ewll\UserBundle\Twofa\Exception\EmptyTwofaCodeException;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\Item\GoogleTwofa;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
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
    private $authenticator;
    private $pageDataCompiler;
    private $twofaHandler;
    private $twofaTypeToServiceTransformer;
    private $translator;
    private $googleTwofa;
    private $repositoryProvider;
    private $telegramBotName;
    private $domain;

    public function __construct(
        Authenticator $authenticator,
        PageDataCompiler $pageDataCompiler,
        TwofaHandler $twofaHandler,
        TwofaTypeToServiceTransformer $twofaTypeToServiceTransformer,
        TranslatorInterface $translator,
        GoogleTwofa $googleTwofa,
        RepositoryProvider $repositoryProvider,
        string $telegramBotName,
        string $domain
    ) {
        $this->authenticator = $authenticator;
        $this->pageDataCompiler = $pageDataCompiler;
        $this->twofaHandler = $twofaHandler;
        $this->twofaTypeToServiceTransformer = $twofaTypeToServiceTransformer;
        $this->translator = $translator;
        $this->googleTwofa = $googleTwofa;
        $this->repositoryProvider = $repositoryProvider;
        $this->telegramBotName = $telegramBotName;
        $this->domain = $domain;
    }

    public function page()
    {
        try {
            $user = $this->authenticator->getUser();
        } catch (NotAuthorizedException $e) {
            return $this->redirect('/login');
        }
        if ($user->hasTwofa()) {
            return $this->redirect('/private');
        }
        $googleSecret = $this->googleTwofa->generateSecret();
        $googleSecretUrl = $this->googleTwofa->getSecretUrl($user->email, $this->domain, $googleSecret);

        $jsConfig['telegramBotName'] = $this->telegramBotName;
        $jsConfig['googleSecret'] = $googleSecret;
        $jsConfig['googleSecretUrl'] = $googleSecretUrl;

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_TWOFA, $jsConfig, $user);
    }

    public function enrollCode(Request $request)
    {
        $formBuilder = $this->createFormBuilder(null, ['constraints' => [new CsrfToken(), new UserHasNoTwofa()]])
            ->add('contact', IntegerType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('type', TextType::class, [
                'constraints' => [new NotBlank()],
            ]);
        $formBuilder->get('type')->addModelTransformer($this->twofaTypeToServiceTransformer);
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));
        if ($form->isValid()) {
            $data = $form->getData();
            $contact = $data['contact'];
            $twofaService = $data['type'];
            if (!$twofaService instanceof StoredKeyTwofaInterface) {
                throw new RuntimeException('Only StoredKeyTwofaInterface must be here');
            }
            $user = $this->authenticator->getUser();
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

        return new FormErrorResponse($form);
    }

    public function loginCode(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_LOGIN_CODE);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var User $user */
            $user = $data['email'];
            $this->provideCodeByUserAndForm($user, $form);
            if ($form->isValid()) {
                return new JsonResponse([]);
            }
        }

        return new FormErrorResponse($form);
    }

    public function oauthCode(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_OAUTH_CODE);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var OauthToken $oauthToken */
            $oauthToken = $data['token'];
            /** @var User $user */
            $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $oauthToken->email]);
            $this->provideCodeByUserAndForm($user, $form);
            if ($form->isValid()) {
                return new JsonResponse([]);
            }
        }

        return new FormErrorResponse($form);
    }

    public function enroll(Request $request)
    {
        $formBuilder = $this->createFormBuilder(null, ['constraints' => [new CsrfToken(), new UserHasNoTwofa()]])
            ->add('code', IntegerType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('type', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('context', TextType::class);
        $formBuilder->get('type')->addModelTransformer($this->twofaTypeToServiceTransformer);
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));

        if ($form->isValid()) {
            $data = $form->getData();
            $user = $this->authenticator->getUser();
            $code = $data['code'];
            $context = $data['context'];
            $twofaService = $data['type'];
            try {
                $this->twofaHandler->setTwofa($user, $twofaService, $code, TwofaCode::ACTION_ID_ENROLL, $context);

                return new JsonResponse([]);
            } catch (EmptyTwofaCodeException $e) {
                $form->get('code')
                    ->addError(new FormError($this->translator->trans('twofa.code.empty', [], 'validators')));
            } catch (IncorrectTwofaCodeException $e) {
                $form->get('code')
                    ->addError(new FormError($this->translator->trans('twofa.code.incorrect', [], 'validators')));
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
