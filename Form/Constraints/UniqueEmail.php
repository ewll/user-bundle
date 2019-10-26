<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class UniqueEmail extends Constraint
{
    public $message = 'unique-email';
}
