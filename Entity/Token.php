<?php namespace Ewll\UserBundle\Entity;

use Ewll\DBBundle\Annotation as Db;

class Token
{
    /** @Db\BigIntType */
    public $id;
    /** @Db\TinyIntType */
    public $typeId;
    /** @Db\VarcharType(length = 32) */
    public $actionHash;
    /** @Db\CipheredType */
    public $data;
    /** @Db\VarcharType(length = 39) */
    public $ip;
    /** @Db\TimestampType */
    public $expirationTs;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($typeId, $actionHash, $data, $ip, $expirationTs): self
    {
        $item = new self();
        $item->typeId = $typeId;
        $item->actionHash = $actionHash;
        $item->data = $data;
        $item->ip = $ip;
        $item->expirationTs = $expirationTs;

        return $item;
    }
}
