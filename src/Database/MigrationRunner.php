<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly string $migrationsPath,
        private readonly MigrationRepository $migrationRepository,
        private readonly PdoConnectionFactory $connectionFactory
    ) {
    }

    /**
     * @return Migration[]
     */
    public function pendingMigrations(): array
    {
        $this->migrationRepository->ensureSchemaTableExists();
        $appliedVersions = $this->migrationRepository->appliedVersions();

        return array_values(array_filter(
            $this->loadMigrations(),
            static fn (Migration $migration): bool => !isset($appliedVersions[$migration->version()])
        ));
    }

    /**
     * @return Migration[]
     */
    public function runPending(): array
    {
        $pendingMigrations = $this->pendingMigrations();
        $connection = $this->connectionFactory->connection();

        foreach ($pendingMigrations as $migration) {
            $sql = file_get_contents($migration->path());

            if ($sql === false) {
                throw new RuntimeException('Cannot read migration file: ' . $migration->path());
            }

            $connection->exec($sql);
        }

        return $pendingMigrations;
    }

    /**
     * @return Migration[]
     */
    private function loadMigrations(): array
    {
        $files = glob(rtrim($this->migrationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');

        if ($files === false) {
            throw new RuntimeException('Cannot read migrations path: ' . $this->migrationsPath);
        }

        sort($files, SORT_STRING);

        return array_map(static function (string $path): Migration {
            $filename = basename($path, '.sql');

            if (!preg_match('/^([0-9]+)_(.+)$/', $filename, $matches)) {
                throw new RuntimeException('Invalid migration filename: ' . $filename);
            }

            return new Migration($matches[1], $matches[2], $path);
        }, $files);
    }
}
