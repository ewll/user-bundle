<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class Captcha extends Constraint
{
    const CODE_NOT_CHOOSED = 1;
    const CODE_INCORRECT = 2;

    public $messages = [
        self::CODE_NOT_CHOOSED => 'captcha.not-choosed',
        self::CODE_INCORRECT => 'captcha.incorrect',
    ];
    public $suppressFieldViolations;

    public function __construct(array $suppressFieldViolations = [])
    {
        $this->suppressFieldViolations = $suppressFieldViolations;
        parent::__construct();
    }
}
