<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Throwable;

final class TransactionManager
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @template TResult
     * @param callable(PDO): TResult $callback
     * @return TResult
     * @throws Throwable
     */
    public function transactional(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback($this->connection);
            $this->connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
