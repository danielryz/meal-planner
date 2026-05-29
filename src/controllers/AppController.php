<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\ViewRenderer;

abstract class AppController
{
    public function __construct(
        protected readonly Request $request,
        protected readonly ViewRenderer $viewRenderer
    ) {
    }

    protected function isGet(): bool
    {
        return $this->request->isGet();
    }

    protected function isPost(): bool
    {
        return $this->request->isPost();
    }

    protected function render(string $template, array $variables = [], int $statusCode = 200): Response
    {
        return $this->viewRenderer->render($template, $variables, $statusCode);
    }
}
