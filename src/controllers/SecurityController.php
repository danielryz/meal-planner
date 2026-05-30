<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Database\Database;
use App\Http\Response;
use App\Repositories\UserRepository;

final class SecurityController extends AppController
{
    public function login(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            return $this->redirect('/dashboard');
        }

        if ($this->isGet()) {
            return $this->renderLogin();
        }

        $csrfToken = $this->request->input('csrfToken');

        if (!$this->csrfTokens->isValid('login', $csrfToken)) {
            return $this->renderLogin(['global' => 'Sesja formularza wygasła. Spróbuj ponownie.'], 400);
        }

        $result = $this->authService()->login(
            (string) $this->request->input('email', ''),
            (string) $this->request->input('password', '')
        );

        if (!$result->isSuccess()) {
            return $this->renderLogin($result->errors(), 401);
        }

        return $this->redirect('/dashboard');
    }

    public function register(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            return $this->redirect('/dashboard');
        }

        if ($this->isGet()) {
            return $this->renderRegister();
        }

        $csrfToken = $this->request->input('csrfToken');

        if (!$this->csrfTokens->isValid('register', $csrfToken)) {
            return $this->renderRegister(['global' => 'Sesja formularza wygasła. Spróbuj ponownie.'], 400);
        }

        $result = $this->authService()->register(
            (string) $this->request->input('firstName', ''),
            (string) $this->request->input('email', ''),
            (string) $this->request->input('password', ''),
            $this->request->input('termsAccepted') === '1'
        );

        if (!$result->isSuccess()) {
            return $this->renderRegister($result->errors(), 400);
        }

        return $this->redirect('/dashboard');
    }

    public function logout(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            $this->sessions->logout();
        }

        return $this->redirect('/login');
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderLogin(array $errors = [], int $statusCode = 200): Response
    {
        return $this->render("login", [
            "authErrors" => $errors,
            "csrfToken" => $this->csrfTokens->token('login'),
            "oldEmail" => (string) $this->request->input('email', ''),
        ], $statusCode);
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderRegister(array $errors = [], int $statusCode = 200): Response
    {
        return $this->render("register", [
            "authErrors" => $errors,
            "csrfToken" => $this->csrfTokens->token('register'),
            "oldDisplayName" => (string) $this->request->input('firstName', ''),
            "oldEmail" => (string) $this->request->input('email', ''),
        ], $statusCode);
    }

    private function authService(): AuthService
    {
        $database = new Database();
        $connection = $database->connection();

        return new AuthService(
            new UserRepository($connection),
            $database->transactions(),
            $this->sessions
        );
    }
}
