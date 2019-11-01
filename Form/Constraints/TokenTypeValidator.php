<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\UserBundle\Entity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TokenTypeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof TokenType) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\TokenType');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var Entity\Token $value */
        if ($value->typeId !== $constraint->typeId) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
