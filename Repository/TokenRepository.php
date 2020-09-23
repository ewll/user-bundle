<?php namespace Ewll\UserBundle\Repository;

use Ewll\DBBundle\Repository\Repository;
use Ewll\UserBundle\Entity\Token;

class TokenRepository extends Repository
{
    public function flush(): int
    {
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
DELETE FROM {$this->config->tableName} 
WHERE expirationTs < NOW()
SQL
            )
            ->execute();
        $affectedRows = $statement->affectedRows();

        return $affectedRows;
    }

    public function findActive(string $code)
    {
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT * FROM {$this->config->tableName} 
WHERE 
    actionHash = :code 
    AND expirationTs > NOW()
SQL
            )
            ->execute([
                'code' => $code,
            ]);

        $token = $statement->fetchObject(Token::class);
        return $token;
    }
}
