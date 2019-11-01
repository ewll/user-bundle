<?php namespace Ewll\UserBundle\Token\Item;

use Ewll\UserBundle\Controller\UserController;
use Ewll\UserBundle\Token\AbstractToken;

class ConfirmEmailToken extends AbstractToken
{
    const TYPE_ID = 4;

    public function getTypeId(): int
    {
        return self::TYPE_ID;
    }

    public function getLifeTimeMinutes(): int
    {
        return 60 * 24 * 180;
    }

    public function getRoute(): string
    {
        return UserController::ROUTE_NAME_LOGIN_PAGE;
    }

    public function getIdDataKey(): string
    {
        return 'userId';
    }
}
