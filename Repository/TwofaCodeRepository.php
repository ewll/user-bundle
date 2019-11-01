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

    public function flush(): int
    {
        $lifeTimeMinutes = TwofaCode::LIFE_TIME_MINUTES;
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
DELETE FROM {$this->config->tableName} 
WHERE createdTs < ADDDATE(NOW(), INTERVAL -$lifeTimeMinutes MINUTE)
SQL
            )
            ->execute();
        $affectedRows = $statement->affectedRows();

        return $affectedRows;
    }
}
