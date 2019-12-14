<?php namespace Ewll\UserBundle\Form\Constraints;

use App\Entity\User;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConfirmedEmailValidator extends ConstraintValidator
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ConfirmedEmail) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\ConfirmedEmail');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var User|null $user */
        $user = $this->context->getRoot()->get('email')->getData();
        if (null === $user) {
            return;
        }

        if (!$user->isEmailConfirmed) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
