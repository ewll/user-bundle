<?php namespace Ewll\UserBundle\AccessRule;

interface AccessRuleInterface
{
    public function getId(): int;
    public function getName(): string;
}
