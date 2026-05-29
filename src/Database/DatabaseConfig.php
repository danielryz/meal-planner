<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Env;

final class DatabaseConfig
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            Env::get('DB_HOST', 'db') ?? 'db',
            (int) (Env::get('DB_PORT', '5432') ?? '5432'),
            Env::get('POSTGRES_DB', 'mealplanner') ?? 'mealplanner',
            Env::get('POSTGRES_USER', 'mealplanner') ?? 'mealplanner',
            Env::get('POSTGRES_PASSWORD', '') ?? ''
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->host,
            $this->port,
            $this->database
        );
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }
}
