<?php namespace Ewll\UserBundle\Controller;

use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\CannotConfirmEmailException;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Constraints\ConfirmedEmail;
use Ewll\UserBundle\Constraints\PassMatch;
use Ewll\UserBundle\Constraints\UniqueEmail;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Form\DataTransformer\UserToEmailTransformer;
use Ewll\UserBundle\Form\Exception\FormValidationException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserController extends AbstractController
{
    const ROUTE_NAME_LOGIN = 'loginPage';

    private $authenticator;
    private $userToEmailTransformer;

    public function __construct(Authenticator $authenticator, UserToEmailTransformer $userToEmailTransformer)
    {
        $this->authenticator = $authenticator;
        $this->userToEmailTransformer = $userToEmailTransformer;
    }

    public function loginPage(Request $request, string $code = null)
    {
        try {
            $this->authenticator->getUser();

            return $this->redirect('/private');
        } catch (NotAuthorizedException $e) {
        }

        $jsConfig = ['emailConfirmed' => false];
        if (!empty($code)) {
            try {
                $this->authenticator->confirmEmail($code);
                $jsConfig['emailConfirmed'] = true;
            } catch (CannotConfirmEmailException $e) {
            }
        }

        return $this->getAuthPage('login', $jsConfig);
    }

    public function login(Request $request)
    {
        $form = $this->makeAndHandleAuthForm($request);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $data = $form->getData();
            /** @var User $user */
            $user = $data['email'];
            $this->authenticator->login($user);

            return new JsonResponse([]);
        } catch (FormValidationException $e) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errors], 400);
        }
    }

    public function signupPage()
    {
        return $this->getAuthPage('signup');
    }

    public function signup(Request $request)
    {
        $form = $this->makeAndHandleAuthForm($request, true);
        if (!$form->isValid()) {
            $errors = $this->compileFormErrors($form->getErrors(true));

            return new JsonResponse(['errors' => $errors], 400);
        }

        $data = $form->getData();
        $this->authenticator->signUp($data['email'], $data['pass']);

        return new JsonResponse([]);
    }

    public function exit()
    {
        $this->authenticator->exit();

        return $this->redirect('/login');
    }

    private function getAuthPage(string $pageName, array $jsConfig = [])
    {
        $token = '~';
        $jsConfig['token'] = $token;
        $jsConfig['pageName'] = $pageName;
        $data = [
            'jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT | JSON_HEX_APOS)),
            'year' => date('Y'),
            'token' => $token,
            'appName' => 'auth',
            'pageName' => $pageName,
        ];

        return $this->render('@EwllUser/index.html.twig', $data);
    }

    private function makeAndHandleAuthForm(Request $request, bool $isSignup = false): FormInterface
    {
        $formConstraints = [];
        $emailConstraints = [];
        $passConstraints = [new NotBlank()];
        if ($isSignup) {
            $emailConstraints[] = new NotBlank();
            $emailConstraints[] = new Email();
            $emailConstraints[] = new UniqueEmail();
        } else {
            $passConstraints[] = new PassMatch();
            $emailConstraints[] = new ConfirmedEmail();
        }
        $formBuilder = $this->createFormBuilder(null, ['csrf_protection' => false, 'constraints' => $formConstraints])
            ->add('email', TextType::class, [
                'constraints' => $emailConstraints,
            ])
            ->add('pass', PasswordType::class, [
                'constraints' => $passConstraints
            ]);
        if (!$isSignup) {
            $formBuilder->get('email')->addModelTransformer($this->userToEmailTransformer);
        }
        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new RuntimeException('Form is not submitted');
        }

        return $form;
    }

    public function compileFormErrors(FormErrorIterator $errors): array
    {
        $view = [];
        foreach ($errors as $error) {
            $view[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $view;
    }
}
