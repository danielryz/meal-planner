<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class MigrationRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function ensureSchemaTableExists(): void
    {
        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(64) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    /**
     * @return array<string, true>
     */
    public function appliedVersions(): array
    {
        $statement = $this->connection->query('SELECT version FROM schema_migrations');
        $versions = [];

        foreach ($statement->fetchAll() as $row) {
            $versions[(string) $row['version']] = true;
        }

        return $versions;
    }
}
