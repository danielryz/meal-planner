<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/Routing.php';

$request = App\Http\Request::fromGlobals();
$sessionManager = new App\Auth\SessionManager();
$sessionManager->start($request);

Routing::run($request, __DIR__);
