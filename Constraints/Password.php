<?php namespace Ewll\UserBundle\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Password extends Constraint
{
    public $message = 'constraint.invalid-password';
}
