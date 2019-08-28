<?php namespace Ewll\UserBundle\AccessRule;

interface AccessRuleListInterface extends AccessRuleInterface
{
    public function isIdValid(int $id): bool;
}
