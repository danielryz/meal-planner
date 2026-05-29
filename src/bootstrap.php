<?php

declare(strict_types=1);

use App\Config\Env;
use App\Core\Autoloader;

require_once __DIR__ . '/Core/Autoloader.php';

Autoloader::register(__DIR__);
Env::load(dirname(__DIR__) . '/.env');
