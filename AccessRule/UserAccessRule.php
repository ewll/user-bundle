<?php namespace Ewll\UserBundle\AccessRule;

class UserAccessRule implements AccessRuleInterface
{
    const ID = 1;

    public function getId(): int
    {
        return self::ID;
    }

    public function getName(): string
    {
        return 'user';
    }
}
