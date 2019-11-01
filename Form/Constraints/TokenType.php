<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class TokenType extends Constraint
{
    public $message = 'token.incorrect-or-old';
    public $typeId;

    public function __construct(int $typeId)
    {
        $this->typeId = $typeId;
        parent::__construct();
    }
}
