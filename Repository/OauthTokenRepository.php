<?php namespace Ewll\UserBundle\Repository;

use Ewll\DBBundle\Repository\Repository;
use Ewll\UserBundle\Entity\OauthToken;

class OauthTokenRepository extends Repository
{
    public function findActiveByToken(string $token): ?OauthToken
    {
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    token = :token
    AND createdTs > ADDDATE(NOW(), INTERVAL -5 MINUTE)
SQL
            )
            ->execute([
                'token' => $token,
            ]);
        $item = $this->hydrator->hydrateOne($this->config, $prefix, $statement, $this->getFieldTransformationOptions());

        return $item;
    }
}
