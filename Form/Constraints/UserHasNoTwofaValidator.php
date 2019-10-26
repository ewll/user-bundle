<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UserHasNoTwofaValidator extends ConstraintValidator
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof UserHasNoTwofa) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\UserHasNoTwofa');
        }

        try {
            $user = $this->authenticator->getUser();
            if ($user->hasTwofa()) {
                $this->context->buildViolation($constraint->message)->addViolation();
            }
        } catch (NotAuthorizedException $e) {
        }
    }
}
