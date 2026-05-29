<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/Routing.php';

Routing::run(App\Http\Request::fromGlobals(), __DIR__);
