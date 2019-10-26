<?php namespace Ewll\UserBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

class OauthTokenUserExists extends Constraint
{
    const MESSAGE_CODE_USER_EXISTS = 1;
    const MESSAGE_CODE_USER_NOT_EXISTS = 2;

    public $messages = [
        self::MESSAGE_CODE_USER_EXISTS => 'oauth-token.user.exists',
        self::MESSAGE_CODE_USER_NOT_EXISTS => 'oauth-token.user.not-exists',
    ];

    public $mustBeExists;

    public function __construct(bool $mustBeExists = true)
    {
        $this->mustBeExists = $mustBeExists;

        parent::__construct(null);
    }
}
