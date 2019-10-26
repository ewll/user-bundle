<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class UserHasNoTwofa extends Constraint
{
    public $message = 'twofa.already-has';
}
