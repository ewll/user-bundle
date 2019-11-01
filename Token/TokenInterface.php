<?php namespace Ewll\UserBundle\Token;

interface TokenInterface
{
    public function getTypeId(): int;
    public function getLifeTimeMinutes(): int;
    public function getRoute(): string;
    public function getIdDataKey(): string;
}
