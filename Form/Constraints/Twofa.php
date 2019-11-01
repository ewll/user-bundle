<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class Twofa extends Constraint
{
    const CODE_EMPTY = 1;
    const CODE_INCORRECT = 2;

    public $actionId;
    public $messages = [
        self::CODE_EMPTY => 'twofa.code.empty',
        self::CODE_INCORRECT => 'twofa.code.incorrect',
    ];

    public function __construct(int $actionId)
    {
        parent::__construct();
        $this->actionId = $actionId;
    }
}
