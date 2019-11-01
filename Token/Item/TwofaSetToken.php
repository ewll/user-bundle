<?php namespace Ewll\UserBundle\Token\Item;

use Ewll\UserBundle\Controller\TwofaController;
use Ewll\UserBundle\Token\AbstractToken;

class TwofaSetToken extends AbstractToken
{
    const TYPE_ID = 2;

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
        return TwofaController::ROUTE_NAME_PAGE_SET;
    }

    public function getIdDataKey(): string
    {
        return 'userId';
    }
}
