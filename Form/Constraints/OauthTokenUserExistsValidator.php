<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Entity\OauthToken;
use Ewll\UserBundle\Entity\User;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class OauthTokenUserExistsValidator extends ConstraintValidator
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof OauthTokenUserExists) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\OauthTokenUserExists');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var OauthToken|null $entity */
        $entity = $this->context->getRoot()->get('token')->getData();
        if (null === $entity) {
            return;
        }

        $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $entity->email]);
        $isUserExists = $user !== null;

        if ($constraint->mustBeExists && !$isUserExists) {
            $message = $constraint->messages[OauthTokenUserExists::MESSAGE_CODE_USER_NOT_EXISTS];
            $this->context->buildViolation($message)->addViolation();
        }

        if (!$constraint->mustBeExists && $isUserExists) {
            $message = $constraint->messages[OauthTokenUserExists::MESSAGE_CODE_USER_EXISTS];
            $this->context->buildViolation($message)->addViolation();
        }
    }
}
