<?php namespace Ewll\UserBundle\Twofa\Exception;

use Exception;

class CannotProvideTokenException extends Exception
{
    const CODE_CANNOT_SEND = 0;
}
