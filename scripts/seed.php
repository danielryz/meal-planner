<?php

declare(strict_types=1);

use App\Database\Database;
use App\Database\DataSeeder;
use App\Repositories\RecipeRepository;
use App\Repositories\UserRepository;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$database   = new Database();
$connection = $database->connection();

$seeder = new DataSeeder(
    $connection,
    new UserRepository($connection),
    new RecipeRepository($connection),
);

$seeder->run();
