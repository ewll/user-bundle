<?php namespace Ewll\UserBundle\Constraints;

use Symfony\Component\Validator\Constraint;

class PassMatch extends Constraint
{
    public $message = 'incorrect-password';
}
