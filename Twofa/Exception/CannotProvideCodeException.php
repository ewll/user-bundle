<?php namespace Ewll\UserBundle\Twofa\Exception;

use Exception;

class CannotProvideCodeException extends Exception
{
    const CODE_RECIPIENT_NOT_EXISTS = 1;
    const CODE_HAVE_ACTIVE = 2;
}
