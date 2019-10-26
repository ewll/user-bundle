<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Entity\User;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PassMatchValidator extends ConstraintValidator
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof PassMatch) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\PassMatch');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var User|null $user */
        $user = $this->context->getRoot()->get('email')->getData();
        if (null === $user) {
            return;
        }

        $hash = $this->authenticator->encodePassword($value);
        if ($hash !== $user->pass) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
