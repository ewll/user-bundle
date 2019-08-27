<?php namespace Ewll\UserBundle\Constraints;

use Symfony\Component\Validator\Constraint;

class UniqueEmail extends Constraint
{
    public $message = 'constraint.unique-email';
}
