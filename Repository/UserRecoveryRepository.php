<?php namespace Ewll\UserBundle\Repository;

use Ewll\DBBundle\Repository\Repository;
use Ewll\UserBundle\Entity\UserRecovery;

class UserRecoveryRepository extends Repository
{
    public function findValidByCode(string $code): ?UserRecovery
    {
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    code = :code
    AND createdTs > ADDDATE(NOW(), INTERVAL :validInterval MINUTE)
SQL
            )
            ->execute([
                'code' => $code,
                'validInterval' => -UserRecovery::VALID_INTERVAL,
            ]);
        $item = $this->hydrator->hydrateOne($this->config, $prefix, $statement, $this->getFieldTransformationOptions());

        return $item;
    }
}
