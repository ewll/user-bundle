<?php namespace Ewll\UserBundle\Token\Item;

use Ewll\UserBundle\Token\AbstractToken;
use RuntimeException;

class CaptchaToken extends AbstractToken
{
    const TYPE_ID = 3;

    public function getTypeId(): int
    {
        return self::TYPE_ID;
    }

    public function getLifeTimeMinutes(): int
    {
        return 5;
    }

    public function getRoute(): string
    {
        throw new RuntimeException('Not realised');
    }

    public function getIdDataKey(): string
    {
        return 'ip';
    }
}
