<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\CsrfTokenManager;
use App\Auth\SessionManager;
use App\Http\Request;
use App\Http\Response;
use App\Http\ViewRenderer;

abstract class AppController
{
    protected readonly SessionManager $sessions;
    protected readonly CsrfTokenManager $csrfTokens;

    public function __construct(
        protected readonly Request $request,
        protected readonly ViewRenderer $viewRenderer
    ) {
        $this->sessions = new SessionManager();
        $this->csrfTokens = new CsrfTokenManager();
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

    protected function redirect(string $location): Response
    {
        return Response::redirect($location);
    }

    protected function requireLogin(): ?Response
    {
        if ($this->sessions->isLoggedIn()) {
            return null;
        }

        return $this->viewRenderer->renderError(401);
    }

    /**
     * @return array<string, string>
     */
    protected function currentUserViewData(): array
    {
        $user = $this->sessions->currentUser();

        if ($user === null) {
            return [
                "currentUserRole" => "guest",
                "currentUserName" => "Gość",
            ];
        }

        return [
            "currentUserRole" => $user->role(),
            "currentUserName" => $user->displayName(),
        ];
    }
}
