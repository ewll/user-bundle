<?php namespace Ewll\UserBundle\Form\Constraints;

use Ewll\UserBundle\Captcha\CaptchaProvider;
use UnexpectedValueException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CaptchaValidator extends ConstraintValidator
{
    private $captchaProvider;

    public function __construct(CaptchaProvider $captchaProvider)
    {
        $this->captchaProvider = $captchaProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Captcha) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Captcha');
        }

        if (empty($value)) {
            $this->addViolation($constraint, Captcha::CODE_NOT_CHOOSED);
        } elseif (!$this->captchaProvider->isValid($value)) {
            $this->addViolation($constraint, Captcha::CODE_INCORRECT);
        }

        $this->captchaProvider->deactivate();
    }

    private function deleteDependentViolations(array $fieldNames)
    {
        $violations = $this->context->getViolations();
        $removeIdxs = [];
        foreach ($violations as $violationIdx => $violation) {
            $propertyPath = $violation->getPropertyPath();
            if (1 !== preg_match('/^children\[([a-z]+)\](\.data)?$/i', $propertyPath, $matches)) {
                throw new UnexpectedValueException($propertyPath);
            }
            $fieldName = $matches[1];
            if (in_array($fieldName, $fieldNames, true)) {
                $removeIdxs[] = $violationIdx;
            }
        }
        rsort($removeIdxs);
        foreach ($removeIdxs as $idx) {
            $violations->offsetUnset($idx);
        }
    }

    private function addViolation(Constraint $constraint, int $code)
    {
        $this->deleteDependentViolations($constraint->suppressFieldViolations);
        $message = $constraint->messages[$code];
        $this->context->buildViolation($message)->addViolation();
    }
}
