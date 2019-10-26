<?php namespace Ewll\UserBundle\Controller;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\CannotConfirmEmailException;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Entity\User;
use Ewll\UserBundle\Entity\UserRecovery;
use Ewll\UserBundle\Form\FormErrorResponse;
use Ewll\UserBundle\PageDataCompiler;
use Ewll\UserBundle\Repository\UserRecoveryRepository;
use Ewll\UserBundle\Twofa\Exception\EmptyTwofaCodeException;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    const ROUTE_NAME_LOGIN_PAGE = 'login.page';
    const ROUTE_NAME_RECOVERING_FINISH_PAGE = 'passwordRecovering.finishPage';

    private $authenticator;
    private $repositoryProvider;
    private $pageDataCompiler;
    private $twofaHandler;
    private $translator;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        PageDataCompiler $pageDataCompiler,
        TwofaHandler $twofaHandler,
        TranslatorInterface $translator
    ) {
        $this->authenticator = $authenticator;
        $this->repositoryProvider = $repositoryProvider;
        $this->pageDataCompiler = $pageDataCompiler;
        $this->twofaHandler = $twofaHandler;
        $this->translator = $translator;
    }

    public function loginPage(string $code = null)
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

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_LOGIN, $jsConfig);
    }

    public function login(Request $request)
    {
        $form = $this->pageDataCompiler->makeAndSubmitAuthForm($request, PageDataCompiler::FORM_AUTH_TYPE_LOGIN);
        if ($form->isValid()) {
            $data = $form->getData();
            /** @var User $user */
            $user = $data['email'];
            try {
                if ($user->hasTwofa()) {
                    $this->twofaHandler->checkCode($user, $form, TwofaCode::ACTION_ID_LOGIN);
                }
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
            $this->authenticator->recoveringInit($user, $request->getClientIp());

            return new JsonResponse([]);
        }

        return new FormErrorResponse($form);
    }

    public function passwordRecoveringFinishPage(string $code)
    {
        $userRecovery = $this->findUserRecoveryByCode($code);
        $jsConfig = ['isUserRecoveryFound' => null !== $userRecovery, 'recoveryCode' => $code];

        return $this->pageDataCompiler->getPage(PageDataCompiler::PAGE_NAME_FINISH_RECOVERING, $jsConfig);
    }

    public function passwordRecoveringRecover(Request $request, string $code)
    {
        $formBuilder = $this
            ->createFormBuilder()
            ->add('pass', PasswordType::class, [
                'constraints' => [new Constraints\NotBlank(), new Constraints\Length(['min' => 6])]
            ]);
        $form = $formBuilder->getForm();
        $form->submit($request->request->get('form', []));
        $userRecovery = $this->findUserRecoveryByCode($code);
        if (null === $userRecovery) {
            $form->addError(new FormError('Код восстановления не найден или устарел'));
        }
        if ($form->isValid()) {
            $data = $form->getData();
            $this->authenticator->recover($userRecovery, $data['pass']);

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

    public function exit()
    {
        $this->authenticator->exit();

        return $this->redirect('/login');
    }

    private function findUserRecoveryByCode(string $code): ?UserRecovery
    {
        /** @var UserRecoveryRepository $userRecoveryRepository */
        $userRecoveryRepository = $this->repositoryProvider->get(UserRecovery::class);
        $userRecovery = $userRecoveryRepository->findValidByCode($code);

        return $userRecovery;
    }
}
