<?php namespace Ewll\UserBundle\Twofa;

use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;

interface CheckKeyOnTheFlyTwofaInterface extends TwofaInterface
{
    public function compileDataFromContext(string $context): array;
    public function isCodeCorrect(array $data, string $code): bool;
}
