<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;

class UserRecovery
{
    const VALID_INTERVAL = 30;//minutes

    /** @Db\BigIntType */
    public $id;
    /** @Db\BigIntType */
    public $userId;
    /** @Db\VarcharType(length = 64) */
    public $code;
    /** @Db\VarcharType(length = 39) */
    public $ip;
    /** @Db\BoolType */
    public $isUsed = 0;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($userId, $code, $ip): self
    {
        $item = new self();
        $item->userId = $userId;
        $item->code = $code;
        $item->ip = $ip;

        return $item;
    }
}
