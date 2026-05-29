<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Database
{
    private PdoConnectionFactory $connectionFactory;

    public function __construct(?DatabaseConfig $config = null)
    {
        $this->connectionFactory = new PdoConnectionFactory($config ?? DatabaseConfig::fromEnvironment());
    }

    public function connection(): PDO
    {
        return $this->connectionFactory->connection();
    }

    public function transactions(): TransactionManager
    {
        return new TransactionManager($this->connection());
    }

    public function migrations(string $migrationsPath): MigrationRunner
    {
        return new MigrationRunner(
            $migrationsPath,
            new MigrationRepository($this->connection()),
            $this->connectionFactory
        );
    }
}
