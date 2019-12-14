<?php namespace Ewll\UserBundle\Twofa\Exception;

use Exception;

class CannotProvideCodeException extends Exception
{
    const CODE_CANNOT_SEND = 0;
    const CODE_RECIPIENT_NOT_EXISTS = 1;
    const CODE_HAVE_ACTIVE = 2;
    const CODE_USER_HAS_NO_TWOFA = 3;
}
