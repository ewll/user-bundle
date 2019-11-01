<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;
use Ewll\UserBundle\AccessRule\UserAccessRule;

class User
{
    /** @Db\BigIntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $email;
    /** @Db\VarcharType(length = 64) */
    public $pass;
    /** @Db\TinyIntType */
    public $twofaTypeId;
    /** @Db\CipheredType */
    public $twofaData;
    /** @Db\VarcharType(length = 39) */
    public $ip;
    /** @Db\VarcharType(30) */
    public $timezone = 'Atlantic/Reykjavik';
    /** @Db\BoolType */
    public $isEmailConfirmed;
    /** @Db\JsonType */
    public $accessRights = [['id' => UserAccessRule::ID]];
    /** @Db\TimestampType */
    public $createdTs;

    /** @var Token|null */
    public $token;

    public static function create($email, $pass, $ip, $isEmailConfirmed): self
    {
        $item = new self();
        $item->email = $email;
        $item->pass = $pass;
        $item->ip = $ip;
        $item->isEmailConfirmed = $isEmailConfirmed;

        return $item;
    }

    public function hasTwofa()
    {
        return null !== $this->twofaTypeId;
    }
}
