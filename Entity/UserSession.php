<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;

class UserSession
{
    /** @Db\BigIntType */
    public $id;
    /** @Db\BigIntType */
    public $userId;
    /** @Db\VarcharType(length = 64) */
    public $crypt;
    /** @Db\VarcharType(length = 64) */
    public $token;
    /** @Db\VarcharType(length = 39) */
    public $ip;
    /** @Db\TimestampType */
    public $lastActionTs;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($userId, $crypt, $token, $ip): self
    {
        $item = new self();
        $item->userId = $userId;
        $item->crypt = $crypt;
        $item->token = $token;
        $item->ip = $ip;

        return $item;
    }
}

