<?php namespace Ewll\UserBundle\Twofa;

interface TwofaInterface
{
    public function getId(): int;
    public function getType(): string;
}
