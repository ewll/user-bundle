<?php namespace Ewll\UserBundle\Constraints;

use Symfony\Component\Validator\Constraint;

class ConfirmedEmail extends Constraint
{
    public $message = 'email-not-confirmed';
}
