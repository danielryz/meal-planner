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

        if (array_key_exists($path, $this->routes)) {
            return $this->handle($request, $this->routes[$path]);
        }

        foreach ($this->routes as $pattern => $route) {
            $params = $this->matchPattern($pattern, $path);
            if ($params !== null) {
                return $this->handle($request->withRouteParams($params), $route);
            }
        }
        return $this->viewRenderer->renderError(404);
    }

    private function matchPattern(string $pattern, string $path): ?array
    {
        $regex = preg_replace('/{(\w+)}/', '(?P<$1>[^/]+)', $pattern);
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function handle(Request $request, array $route): Response
    {
        $controllerClass = $route['controller'];
        $action = $route['action'];
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
