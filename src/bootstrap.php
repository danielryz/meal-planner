<?php

declare(strict_types=1);

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    require_once __DIR__ . '/Core/Autoloader.php';
    App\Core\Autoloader::register(__DIR__);
}

App\Config\Env::load(dirname(__DIR__) . '/.env');

error_reporting(E_ALL);
ini_set('log_errors', '1');

if (!getenv('APP_DEBUG')) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
