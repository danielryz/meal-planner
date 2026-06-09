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

    protected function isPatch(): bool
    {
        return $this->request->isPatch();
    }

    protected function isDelete(): bool
    {
        return $this->request->isDelete();
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

        if (str_starts_with($this->request->path(), 'api/')) {
            return Response::json(['error' => 'Wymagane logowanie.'], 401);
        }

        return $this->redirect('/login');
    }

    protected function requireVerified(): ?Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        $user = $this->sessions->currentUser();

        if ($user?->isPending()) {
            if (str_starts_with($this->request->path(), 'api/')) {
                return Response::json([
                    'error' => 'Potwierdź adres e-mail, żeby korzystać z tej funkcji.',
                    'code'  => 'EMAIL_NOT_VERIFIED',
                ], 403);
            }

            return $this->redirect('/dashboard');
        }

        return null;
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
            "currentUserRole"      => $user->role(),
            "currentUserName"      => $user->displayName(),
            "currentUserPending"   => $user->isPending() ? 'true' : 'false',
        ];
    }

    protected function requireRole(string ...$roles): ?Response
    {
        if($redirect = $this->requireLogin()) {
            return $redirect;
        }

        $user = $this->sessions->currentUser();

        if(!in_array($user?->role(), $roles, true)) {
            return Response::json(['error' => 'Brak uprawnień.'], 403);
        }
        return null;
    }

    protected function jsonError(string $message, int $status = 400): Response
    {
        return Response::json(['error' => $message], $status);
    }
}
