<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;

class User
{
    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $email;
    /** @Db\VarcharType(length = 64) */
    public $pass;
    /** @Db\VarcharType(30) */
    public $timezone = 'Atlantic/Reykjavik';
    /** @Db\VarcharType(length = 64) */
    public $emailConfirmationCode;
    /** @Db\BoolType */
    public $isEmailConfirmed = 0;
    /** @Db\TimestampType */
    public $createdTs;

    public $token;

    public static function create($email, $pass, $emailConfirmationCode): self
    {
        $item = new self();
        $item->email = $email;
        $item->pass = $pass;
        $item->emailConfirmationCode = $emailConfirmationCode;

        return $item;
    }
}
