<?php namespace Ewll\UserBundle\Controller;

use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Constraints\UniqueEmail;
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
    const ROUTE_NAME_LOGIN = 'login';

    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function loginPage()
    {
        return $this->getAuthPage('login');
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

    private function getAuthPage(string $pageName)
    {
        $token = '~';
        $jsConfig = [
            'token' => $token,
            'pageName' => $pageName,
        ];
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
        $emailConstraints = [new NotBlank(), new Email()];
        if ($isSignup) {
            $emailConstraints[] = new UniqueEmail();
        }
        $formBuilder = $this->createFormBuilder(null, ['csrf_protection'   => false])
            ->add('email', TextType::class, [
                'constraints' => $emailConstraints,
            ])
            ->add('pass', PasswordType::class, [
                'constraints' => [new NotBlank()]
            ]);
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
