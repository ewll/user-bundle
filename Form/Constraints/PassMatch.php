<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class PassMatch extends Constraint
{
    public $message = 'incorrect-password';
}
