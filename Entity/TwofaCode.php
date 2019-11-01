<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;

class TwofaCode
{
    const ACTION_ID_ENROLL = 1;
    const ACTION_ID_LOGIN = 2;

    const LIFE_TIME_MINUTES = 1;

    /** @Db\BigIntType */
    public $id;
    /** @Db\BigIntType */
    public $userId;
    /** @Db\TinyIntType() */
    public $twofaTypeId;
    /** @Db\TinyIntType() */
    public $actionId;
    /** @Db\VarcharType(length = 64) */
    public $contact;
    /** @Db\VarcharType(length = 6) */
    public $code;
    /** @Db\TimestampType */
    public $createdTs;

    public $token;

    public static function create($userId, $twofaTypeId, $actionId, $contact, $code): self
    {
        $item = new self();
        $item->userId = $userId;
        $item->twofaTypeId = $twofaTypeId;
        $item->actionId = $actionId;
        $item->contact = $contact;
        $item->code = $code;

        return $item;
    }
}
