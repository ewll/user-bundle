<?php namespace Ewll\UserBundle\Twofa;

use Ewll\UserBundle\Twofa\Exception\CannotSendMessageException;

interface StoredKeyTwofaInterface extends TwofaInterface
{
    /** @throws CannotSendMessageException */
    public function sendMessage(string $contact, string $message): void;
}
