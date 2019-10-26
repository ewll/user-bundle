<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class CsrfToken extends Constraint
{
    const MESSAGE_PARAMETER_KEY_CODE = 'code';
    const CODE_NOT_AUTHORIZED = 1;
    const CODE_CSRF_NOT_VALID = 2;

    public $messages = [
        self::CODE_NOT_AUTHORIZED => 'csrf-token.not-authorized',
        self::CODE_CSRF_NOT_VALID => 'csrf-token.not-valid',
    ];
}
