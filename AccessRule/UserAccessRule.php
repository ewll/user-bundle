<?php namespace Ewll\UserBundle\AccessRule;

class UserAccessRule implements AccessRuleInterface
{
    public function getId(): int
    {
        return 1;
    }

    public function getName(): string
    {
        return 'user';
    }
}
