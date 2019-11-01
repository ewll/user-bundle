<?php namespace Ewll\UserBundle\Token\Item;

use Ewll\UserBundle\Controller\TwofaController;
use Ewll\UserBundle\Token\AbstractToken;

class AuthToken extends AbstractToken
{
    const TYPE_ID = 1;

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
        return TwofaController::ROUTE_NAME_PAGE_LOGIN_CONFIRMATION;
    }

    public function getIdDataKey(): string
    {
        return 'userId';
    }
}
