<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;

class OauthToken
{
    /** @Db\BigIntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $email;
    /** @Db\VarcharType(length = 64) */
    public $token;
    /** @Db\VarcharType(length = 39) */
    public $ip;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($email, $token, $ip): self
    {
        $item = new self();
        $item->email = $email;
        $item->token = $token;
        $item->ip = $ip;

        return $item;
    }
}
