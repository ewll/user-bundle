<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Ewll\UserBundle\Entity\Token;
use App\Entity\User;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaHandler;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TwofaValidator extends ConstraintValidator
{
    private $twofaHandler;
    private $repositoryProvider;
    private $authenticator;

    public function __construct(
        TwofaHandler $twofaHandler,
        RepositoryProvider $repositoryProvider,
        Authenticator $authenticator
    ) {
        $this->twofaHandler = $twofaHandler;
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Twofa) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\Twofa');
        }

        $violations = $this->context->getViolations();
        if (count($violations) > 0) {
            return;
        }

        if ($constraint->isUserByToken) {
            /** @var Token|null $token */
            $token = $this->context->getRoot()->get('token')->getData();
            if (null === $token) {
                throw new RuntimeException('Token is expected here');
            }
            /** @var User|null $user */
            $user = $this->repositoryProvider->get(User::class)->findById($token->data['userId']);
        } else {
            try {
                $user = $this->authenticator->getUser();
            } catch (NotAuthorizedException $e) {
                throw new RuntimeException('User is expected here');
            }
        }
        if (!$user->hasTwofa()) {
            throw new RuntimeException('Twofa is expected here');
        }
        $twofa = $this->twofaHandler->getTwofaServiceByTypeId($user->twofaTypeId);
        $isStoredKey = $twofa instanceof StoredKeyTwofaInterface;
        $violationParameters = ['isStoredKey' => $isStoredKey, 'actionId' => $constraint->actionId];

        if (null === $value || '' === $value) {
            $message = $constraint->messages[Twofa::CODE_EMPTY];
            $this->context->buildViolation($message, $violationParameters)->addViolation();

            return;
        }

        try {
            $this->twofaHandler->checkAndDeactivateCode($twofa, $user, $value, $constraint->actionId);
        } catch (IncorrectTwofaCodeException $e) {
            $message = $constraint->messages[Twofa::CODE_INCORRECT];
            $this->context->buildViolation($message, $violationParameters)->addViolation();
        }
    }
}
