<?php namespace Ewll\UserBundle\Token\Item;

use Ewll\UserBundle\Token\AbstractToken;

class UserSessionToken extends AbstractToken
{
    const TYPE_ID = 5;
    /** 10 days by minutes */
    const LIFE_TIME = 14400;

    public function getTypeId(): int
    {
        return self::TYPE_ID;
    }

    public function getLifeTimeMinutes(): int
    {
        return self::LIFE_TIME;
    }

    public function getRoute(): string
    {
        return 'private';
    }

    public function getIdDataKey(): string
    {
        return 'userId';
    }
}
