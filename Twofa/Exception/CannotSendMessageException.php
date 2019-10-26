<?php namespace Ewll\UserBundle\Twofa\Exception;

use Exception;

class CannotSendMessageException extends Exception
{
    const CODE_RECIPIENT_NOT_EXISTS = 1;
}
