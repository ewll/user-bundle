<?php namespace Ewll\UserBundle\Repository;

use Ewll\DBBundle\Repository\Repository;
use Ewll\UserBundle\Entity\TwofaCode;

class TwofaCodeRepository extends Repository
{
    public function findActive(int $userId, int $actionId, bool $forUpdate = false): ?TwofaCode
    {
        $forUpdateQuery = $forUpdate ? 'FOR UPDATE' : '';
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    userId = :userId
    AND actionId = :actionId
    AND isUsed = 0
    AND createdTs > ADDDATE(NOW(), INTERVAL -1 MINUTE)
LIMIT 1
$forUpdateQuery
SQL
            )
            ->execute([
                'userId' => $userId,
                'actionId' => $actionId,
            ]);
        $item = $this->hydrator->hydrateOne($this->config, $prefix, $statement, $this->getFieldTransformationOptions());

        return $item;
    }
}
