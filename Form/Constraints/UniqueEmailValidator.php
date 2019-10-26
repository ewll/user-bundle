<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\UserBundle\Entity\User;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueEmailValidator extends ConstraintValidator
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof UniqueEmail) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\UniqueEmail');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var User|null $account */
        $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $value]);

        if (null !== $user) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
