<?php namespace Ewll\UserBundle;

use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Form\Constraints as CustomConstraints;
use Ewll\UserBundle\Form\DataTransformer\CodeToTokenTransformer;
use Ewll\UserBundle\Form\DataTransformer\UserToEmailTransformer;
use Ewll\UserBundle\Oauth\OauthInterface;
use Ewll\UserBundle\Token\Item\AuthToken;
use Ewll\UserBundle\Token\Item\OAuthRegToken;
use Ewll\UserBundle\Token\Item\RecoverPassToken;
use Ewll\UserBundle\Token\Item\TwofaToken;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type as FieldType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints;
use Twig\Environment as TwigEnvironment;

class PageDataCompiler
{
    const FORM_AUTH_TYPE_LOGIN = 1;
    const FORM_AUTH_TYPE_SIGNUP = 2;
    const FORM_AUTH_TYPE_RECOVERING_INIT = 3;
    const FORM_AUTH_TYPE_LOGIN_CODE = 4;
    const FORM_AUTH_TYPE_OAUTH_SIGNUP = 5;
    const FORM_AUTH_TYPE_TWOFA_LOGIN = 8;
    const FORM_AUTH_TYPE_RECOVERING_FINISH = 9;

    const PAGE_NAME_LOGIN = 'login';
    const PAGE_NAME_SIGNUP = 'signup';
    const PAGE_NAME_INIT_RECOVERING = 'initRecovering';
    const PAGE_NAME_FINISH_RECOVERING = 'finishRecovering';
    const PAGE_NAME_TWOFA = 'twofa';
    const PAGE_NAME_OAUTH = 'oauth';
    const PAGE_NAME_TWOFA_LOGIN_CONFIRMATION = 'twofaLoginConfirmation';

    private $translator;
    private $twig;
    private $formFactory;
    private $userToEmailTransformer;
    private $codeToTokenTransformer;
    /** @var OauthInterface[] */
    private $oauths;

    public function __construct(
        TranslatorInterface $translator,
        TwigEnvironment $twig,
        FormFactoryInterface $formFactory,
        UserToEmailTransformer $userToEmailTransformer,
        CodeToTokenTransformer $codeToTokenTransformer,
        iterable $oauths
    ) {
        $this->translator = $translator;
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->userToEmailTransformer = $userToEmailTransformer;
        $this->codeToTokenTransformer = $codeToTokenTransformer;
        $this->oauths = $oauths;
    }

    public function getPage(string $pageName, array $jsConfig = [], User $user = null)
    {
        $token = null === $user ? '~' : $user->token->data['csrf'];
        $jsConfig['token'] = $token;
        $jsConfig['pageName'] = $pageName;
        $jsConfig['oauths'] = [];
        foreach ($this->oauths as $oauth) {
            $jsConfig['oauths'][] = ['name' => $oauth->getType(), 'url' => $oauth->getUrl()];
        }
        $data = [
            'jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT | JSON_HEX_APOS)),
            'year' => date('Y'),
            'token' => $token,
            'appName' => 'auth',
            'pageName' => $this->translator->trans("title.$pageName", [], EwllUserBundle::TRANSLATION_DOMAIN),
        ];
        $response = new Response($this->twig->render('@EwllUser/index.html.twig', $data));

        return $response;
    }

    public function makeAndSubmitAuthForm(Request $request, int $formType): FormInterface
    {
        $map = [
            self::FORM_AUTH_TYPE_SIGNUP => [
                'email' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [
                        new Constraints\NotBlank(),
                        new Constraints\Email(),
                        new CustomConstraints\UniqueEmail()
                    ],
                ],
                'pass' => [
                    'type' => FieldType\PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new Constraints\Length(['min' => 6])],
                ],
                'captcha' => [
                    'type' => FieldType\IntegerType::class,
                    'constraints' => [new CustomConstraints\Captcha(['email', 'pass'])],
                ],
            ],
            self::FORM_AUTH_TYPE_LOGIN => [
                'email' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new CustomConstraints\ConfirmedEmail()],
                    'modelTransformer' => $this->userToEmailTransformer,
                ],
                'pass' => [
                    'type' => FieldType\PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new CustomConstraints\PassMatch()],
                ],
                'captcha' => [
                    'type' => FieldType\IntegerType::class,
                    'constraints' => [new CustomConstraints\Captcha(['email', 'pass'])],
                ],
            ],
            self::FORM_AUTH_TYPE_RECOVERING_INIT => [
                'email' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new CustomConstraints\ConfirmedEmail()],
                    'modelTransformer' => $this->userToEmailTransformer,
                ],
                'captcha' => [
                    'type' => FieldType\IntegerType::class,
                    'constraints' => [new CustomConstraints\Captcha(['email'])],
                ],
            ],
            self::FORM_AUTH_TYPE_RECOVERING_FINISH => [
                'pass' => [
                    'type' => FieldType\PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new Constraints\Length(['min' => 6])],
                ],
                'token' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [
                        new Constraints\NotBlank(),
                        new CustomConstraints\TokenType(RecoverPassToken::TYPE_ID)
                    ],
                    'viewTransformer' => $this->codeToTokenTransformer,
                ],
            ],
            self::FORM_AUTH_TYPE_LOGIN_CODE => [
                'token' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new CustomConstraints\TokenType(AuthToken::TYPE_ID)],
                    'viewTransformer' => $this->codeToTokenTransformer,
                ],
            ],
            self::FORM_AUTH_TYPE_TWOFA_LOGIN => [
                'token' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new CustomConstraints\TokenType(AuthToken::TYPE_ID)],
                    'viewTransformer' => $this->codeToTokenTransformer,
                ],
                'twofaCode' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [new CustomConstraints\Twofa(TwofaCode::ACTION_ID_LOGIN)],
                ],
            ],
            self::FORM_AUTH_TYPE_OAUTH_SIGNUP => [
                'pass' => [
                    'type' => FieldType\PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new Constraints\Length(['min' => 6])],
                ],
                'token' => [
                    'type' => FieldType\TextType::class,
                    'constraints' => [
                        new Constraints\NotBlank(),
                        new CustomConstraints\TokenType(OAuthRegToken::TYPE_ID)
                    ],
                    'viewTransformer' => $this->codeToTokenTransformer,
                ],
            ],
        ];
        if (!isset($map[$formType])) {
            throw new RuntimeException('Unknown form type');
        }

        $formBuilder = $this->formFactory->createBuilder();
        foreach ($map[$formType] as $fieldName => $parameters) {
            $constraints = $parameters['constraints'] ?? [];
            $formBuilder->add($fieldName, $parameters['type'], ['constraints' => $constraints]);
            if (isset($parameters['modelTransformer'])) {
                $formBuilder->get($fieldName)->addModelTransformer($parameters['modelTransformer']);
            }
            if (isset($parameters['viewTransformer'])) {
                $formBuilder->get($fieldName)->addViewTransformer($parameters['viewTransformer']);
            }
        }
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));

        return $form;
    }
}
