<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

abstract class AbstractRepository
{
    public function __construct(protected readonly PDO $connection)
    {
    }
}
