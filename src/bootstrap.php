<?php

declare(strict_types=1);

use App\Config\Env;
use App\Core\Autoloader;

require_once __DIR__ . '/Core/Autoloader.php';

Autoloader::register(__DIR__);
Env::load(dirname(__DIR__) . '/.env');

error_reporting(E_ALL);
ini_set('log_errors', '1');

if (!getenv('APP_DEBUG')) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
