<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;

final class Router
{
    /**
     * @param array<string, array{controller: class-string, action: string}> $routes
     */
    public function __construct(
        private readonly array $routes,
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        if (!array_key_exists($path, $this->routes)) {
            return $this->viewRenderer->renderError(404);
        }

        $controllerClass = $this->routes[$path]['controller'];
        $action = $this->routes[$path]['action'];
        $controller = new $controllerClass($request, $this->viewRenderer);

        if (!method_exists($controller, $action)) {
            return $this->viewRenderer->renderError(404);
        }

        try {
            $response = $controller->{$action}();
        } catch (Throwable) {
            return $this->viewRenderer->renderError(500);
        }

        if (!$response instanceof Response) {
            return $this->viewRenderer->renderError(500);
        }

        return $response;
    }
}
