<?php namespace Ewll\UserBundle\Token\Item;

use Ewll\UserBundle\Controller\UserController;
use Ewll\UserBundle\Token\AbstractToken;

class RecoverPassToken extends AbstractToken
{
    const TYPE_ID = 7;

    public function getTypeId(): int
    {
        return self::TYPE_ID;
    }

    public function getLifeTimeMinutes(): int
    {
        return 15;
    }

    public function getRoute(): string
    {
        return UserController::ROUTE_NAME_RECOVERING_FINISH_PAGE;
    }

    public function getIdDataKey(): string
    {
        return 'userId';
    }
}
