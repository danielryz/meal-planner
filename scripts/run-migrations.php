<?php

declare(strict_types=1);

use App\Database\Database;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$database = new Database();
$runner = $database->migrations(dirname(__DIR__) . '/docker/db/migrations');
$migrations = $runner->runPending();

if ($migrations === []) {
    echo "No pending migrations.\n";
    exit(0);
}

foreach ($migrations as $migration) {
    echo sprintf("Applied migration %s %s\n", $migration->version(), $migration->name());
}
