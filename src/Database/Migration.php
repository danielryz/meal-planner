<?php

declare(strict_types=1);

namespace App\Database;

final class Migration
{
    public function __construct(
        private readonly string $version,
        private readonly string $name,
        private readonly string $path
    ) {
    }

    public function version(): string
    {
        return $this->version;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }
}
