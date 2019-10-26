<?php namespace Ewll\UserBundle;

use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Form\Constraints\ConfirmedEmail;
use Ewll\UserBundle\Form\Constraints\OauthTokenUserExists;
use Ewll\UserBundle\Form\Constraints\PassMatch;
use Ewll\UserBundle\Form\Constraints\UniqueEmail;
use Ewll\UserBundle\Form\DataTransformer\OauthTokenToEntityTransformer;
use Ewll\UserBundle\Form\DataTransformer\UserToEmailTransformer;
use Ewll\UserBundle\Oauth\OauthInterface;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    const FORM_AUTH_TYPE_OAUTH_CODE = 6;
    const FORM_AUTH_TYPE_OAUTH_LOGIN = 7;

    const PAGE_NAME_LOGIN = 'login';
    const PAGE_NAME_SIGNUP = 'signup';
    const PAGE_NAME_INIT_RECOVERING = 'initRecovering';
    const PAGE_NAME_FINISH_RECOVERING = 'finishRecovering';
    const PAGE_NAME_TWOFA = 'twofa';
    const PAGE_NAME_OAUTH = 'oauth';

    private $translator;
    private $twig;
    private $formFactory;
    private $oauthTokenToEntityTransformer;
    private $userToEmailTransformer;
    /** @var OauthInterface[] */
    private $oauths;

    public function __construct(
        TranslatorInterface $translator,
        TwigEnvironment $twig,
        FormFactoryInterface $formFactory,
        UserToEmailTransformer $userToEmailTransformer,
        OauthTokenToEntityTransformer $oauthTokenToEntityTransformer,
        iterable $oauths
    ) {
        $this->translator = $translator;
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->userToEmailTransformer = $userToEmailTransformer;
        $this->oauthTokenToEntityTransformer = $oauthTokenToEntityTransformer;
        $this->oauths = $oauths;
    }

    public function getPage(string $pageName, array $jsConfig = [], User $user = null)
    {
        $token = null === $user ? '~' : $user->session->token;
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
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new Constraints\Email(), new UniqueEmail()],
                ],
                'pass' => [
                    'type' => PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new Constraints\Length(['min' => 6])],
                ],
            ],
            self::FORM_AUTH_TYPE_LOGIN => [
                'email' => [
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new ConfirmedEmail()],
                    'transformer' => $this->userToEmailTransformer,
                ],
                'pass' => [
                    'type' => PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new PassMatch()],
                ],
                'twofaCode' => [
                    'type' => TextType::class,
                ],
            ],
            self::FORM_AUTH_TYPE_RECOVERING_INIT => [
                'email' => [
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new ConfirmedEmail()],
                    'transformer' => $this->userToEmailTransformer,
                ],
            ],
            self::FORM_AUTH_TYPE_LOGIN_CODE => [
                'email' => [
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new ConfirmedEmail()],
                    'transformer' => $this->userToEmailTransformer,
                ],
                'pass' => [
                    'type' => PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new PassMatch()],
                ],
            ],
            self::FORM_AUTH_TYPE_OAUTH_SIGNUP => [
                'pass' => [
                    'type' => PasswordType::class,
                    'constraints' => [new Constraints\NotBlank(), new Constraints\Length(['min' => 6])],
                ],
                'token' => [
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new OauthTokenUserExists(false)],
                    'transformer' => $this->oauthTokenToEntityTransformer,
                ],
            ],
            self::FORM_AUTH_TYPE_OAUTH_CODE => [
                'token' => [
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new OauthTokenUserExists(true)],
                    'transformer' => $this->oauthTokenToEntityTransformer,
                ],
            ],
            self::FORM_AUTH_TYPE_OAUTH_LOGIN => [
                'token' => [
                    'type' => TextType::class,
                    'constraints' => [new Constraints\NotBlank(), new OauthTokenUserExists(true)],
                    'transformer' => $this->oauthTokenToEntityTransformer,
                ],
                'twofaCode' => [
                    'type' => TextType::class,
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
            if (isset($parameters['transformer'])) {
                $formBuilder->get($fieldName)->addModelTransformer($parameters['transformer']);
            }
        }
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));

        return $form;
    }
}
