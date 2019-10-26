<?php namespace Ewll\UserBundle\Twofa\Exception;

use Exception;

class IncorrectTwofaCodeException extends Exception
{
    private $isStoredKey;
    private $actionId;

    public function __construct(bool $isStoredKey, int $actionId)
    {
        parent::__construct();
        $this->isStoredKey = $isStoredKey;
        $this->actionId = $actionId;
    }

    public function isStoredKey(): bool
    {
        return $this->isStoredKey;
    }

    public function getActionId(): int
    {
        return $this->actionId;
    }
}
