<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CsrfTokenValidator extends ConstraintValidator
{
    private $authenticator;
    private $requestStack;

    public function __construct(Authenticator $authenticator, RequestStack $requestStack)
    {
        $this->authenticator = $authenticator;
        $this->requestStack = $requestStack;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof CsrfToken) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\CsrfToken');
        }

        try {
            $user = $this->authenticator->getUser();
            $request = $this->requestStack->getCurrentRequest();
            if ($request->request->get('_token') !== $user->session->token) {
                $message = $constraint->messages[CsrfToken::CODE_CSRF_NOT_VALID];
                $parameters = [CsrfToken::MESSAGE_PARAMETER_KEY_CODE => CsrfToken::CODE_CSRF_NOT_VALID];
                $this->context->buildViolation($message, $parameters)->addViolation();
            }
        } catch (NotAuthorizedException $e) {
            $message = $constraint->messages[CsrfToken::CODE_NOT_AUTHORIZED];
            $parameters = ['code' => CsrfToken::CODE_NOT_AUTHORIZED];
            $this->context->buildViolation($message, $parameters)->addViolation();
        }
    }
}
