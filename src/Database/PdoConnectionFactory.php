<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class PdoConnectionFactory
{
    private ?PDO $connection = null;

    public function __construct(private readonly DatabaseConfig $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->connection = new PDO(
            $this->config->dsn(),
            $this->config->username(),
            $this->config->password(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $this->connection;
    }
}
