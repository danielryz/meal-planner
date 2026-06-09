<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Database\Database;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\MailService;

final class SecurityController extends AppController
{
    public function login(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            return $this->redirect('/dashboard');
        }

        return $this->renderLogin();
    }

    public function loginApi(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Method not allowed.', 405);
        }

        if ($this->isRateLimited()) {
            return Response::json(['error' => 'Zbyt wiele prób logowania. Spróbuj za 15 minut.'], 429);
        }

        $csrfToken = $this->request->input('csrfToken');
        if (!$this->csrfTokens->isValid('login', $csrfToken)) {
            return Response::json(['error' => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'], 400);
        }

        $result = $this->authService()->login(
            (string) $this->request->input('email', ''),
            (string) $this->request->input('password', '')
        );

        if (!$result->isSuccess()) {
            $this->recordFailedAttempt();
            return Response::json(['error' => 'Niepoprawny e-mail lub hasło.'], 401);
        }

        $this->clearRateLimit();

        if ($this->request->input('rememberMe') === '1') {
            $this->sessions->extendSession(30 * 24 * 3600);
        }

        return Response::json(['success' => true]);
    }

    public function register(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            return $this->redirect('/dashboard');
        }

        return $this->renderRegister();
    }

    public function registerApi(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Method not allowed.', 405);
        }

        $csrfToken = $this->request->input('csrfToken');
        if (!$this->csrfTokens->isValid('register', $csrfToken)) {
            return Response::json(['error' => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'], 400);
        }

        $result = $this->authService()->register(
            (string) $this->request->input('firstName', ''),
            (string) $this->request->input('email', ''),
            (string) $this->request->input('password', ''),
            $this->request->input('termsAccepted') === '1'
        );

        if (!$result->isSuccess()) {
            $errors  = $result->errors();
            $message = $errors['email']
                ?? $errors['displayName']
                ?? $errors['password']
                ?? $errors['termsAccepted']
                ?? $errors['global']
                ?? 'Rejestracja nie powiodła się. Spróbuj ponownie.';
            return Response::json(['error' => $message], 400);
        }

        return Response::json(['success' => true]);
    }

    public function activate(): Response
    {
        $token = (string) ($this->request->query('token') ?? '');

        if ($token === '') {
            return $this->redirect('/login');
        }

        $users  = $this->userRepository();
        $userId = $users->findAndConsumeEmailToken($token, 'activation');

        if ($userId === null) {
            return $this->render('activate', ['status' => 'invalid']);
        }

        $users->markEmailVerified($userId);

        $current = $this->sessions->currentUser();
        if ($current !== null && $current->id() === $userId) {
            $this->sessions->markEmailVerified();
        }

        return $this->render('activate', ['status' => 'success']);
    }

    public function resendActivationPage(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        $user = $this->sessions->currentUser();
        if ($user?->isEmailVerified()) {
            return $this->redirect('/dashboard');
        }

        return $this->render('resend-activation', [
            'csrfToken' => $this->csrfTokens->token('resend-activation'),
        ]);
    }

    public function resendActivationApi(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        $user = $this->sessions->currentUser();

        if ($user?->isEmailVerified()) {
            return Response::json(['error' => 'Adres e-mail jest już potwierdzony.'], 400);
        }

        $users = $this->userRepository();
        $email = $users->findEmailById((int) $user?->id());

        if ($email === null) {
            return $this->jsonError('Nie znaleziono użytkownika.', 404);
        }

        $rawToken = $users->createEmailToken((int) $user?->id(), 'activation', 48 * 3600);

        try {
            (new MailService())->sendActivationEmail($email, (string) $user?->displayName(), $rawToken);
        } catch (\Throwable) {
            return Response::json(['error' => 'Nie udało się wysłać e-maila. Spróbuj ponownie później.'], 500);
        }

        return Response::json(['success' => true]);
    }

    public function forgotPassword(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            return $this->redirect('/dashboard');
        }

        return $this->render('forgot-password', [
            'csrfToken' => $this->csrfTokens->token('forgot-password'),
        ]);
    }

    public function forgotPasswordApi(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Method not allowed.', 405);
        }

        $csrfToken = $this->request->input('csrfToken');
        if (!$this->csrfTokens->isValid('forgot-password', $csrfToken)) {
            return Response::json(['error' => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'], 400);
        }

        $email = trim((string) $this->request->input('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['error' => 'Podaj poprawny adres e-mail.'], 400);
        }

        $users = $this->userRepository();
        $user  = $users->findAuthUserByEmail($email);

        if ($user !== null) {
            $rawToken    = $users->createEmailToken($user->id(), 'password_reset', 3600);
            $displayName = $user->displayName();
            try {
                (new MailService())->sendPasswordResetEmail($email, $displayName, $rawToken);
            } catch (\Throwable) {
            }
        }

        return Response::json(['success' => true]);
    }

    public function resetPassword(): Response
    {
        $token = trim((string) ($this->request->query('token') ?? ''));

        if ($token === '') {
            return $this->redirect('/forgot-password');
        }

        return $this->render('reset-password', [
            'token'     => $token,
            'csrfToken' => $this->csrfTokens->token('reset-password'),
        ]);
    }

    public function resetPasswordApi(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Method not allowed.', 405);
        }

        $csrfToken = $this->request->input('csrfToken');
        if (!$this->csrfTokens->isValid('reset-password', $csrfToken)) {
            return Response::json(['error' => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'], 400);
        }

        $token    = trim((string) $this->request->input('token', ''));
        $password = (string) $this->request->input('password', '');
        $confirm  = (string) $this->request->input('passwordConfirm', '');

        if ($token === '') {
            return Response::json(['error' => 'Brakuje tokenu resetowania.'], 400);
        }

        if (strlen($password) < 8 || strlen($password) > 128) {
            return Response::json(['error' => 'Hasło musi mieć od 8 do 128 znaków.'], 400);
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            return Response::json(['error' => 'Hasło musi zawierać co najmniej 1 dużą literę i 1 znak specjalny.'], 400);
        }

        if ($password !== $confirm) {
            return Response::json(['error' => 'Hasła nie są zgodne.'], 400);
        }

        $users  = $this->userRepository();
        $userId = $users->findAndConsumeEmailToken($token, 'password_reset');

        if ($userId === null) {
            return Response::json(['error' => 'Link resetowania jest nieważny lub wygasł.', 'code' => 'TOKEN_INVALID'], 400);
        }

        $users->setPassword($userId, password_hash($password, PASSWORD_BCRYPT));

        return Response::json(['success' => true]);
    }

    public function logout(): Response
    {
        if ($this->sessions->isLoggedIn()) {
            $this->sessions->logout();
        }

        return $this->redirect('/login');
    }

    private function renderLogin(array $errors = [], int $statusCode = 200): Response
    {
        return $this->render("login", [
            "csrfToken" => $this->csrfTokens->token('login'),
        ], $statusCode);
    }

    private function renderRegister(array $errors = [], int $statusCode = 200): Response
    {
        return $this->render("register", [
            "csrfToken" => $this->csrfTokens->token('register'),
        ], $statusCode);
    }

    private function isRateLimited(): bool
    {
        $key = $this->rateLimitKey();

        if (isset($_SESSION[$key . '_until']) && $_SESSION[$key . '_until'] > time()) {
            return true;
        }

        return false;
    }

    private function recordFailedAttempt(): void
    {
        $key      = $this->rateLimitKey();
        $attempts = (int) ($_SESSION[$key . '_count'] ?? 0) + 1;

        if ($attempts >= 5) {
            $_SESSION[$key . '_until'] = time() + 900;
            unset($_SESSION[$key . '_count']);
        } else {
            $_SESSION[$key . '_count'] = $attempts;
        }
    }

    private function clearRateLimit(): void
    {
        $key = $this->rateLimitKey();
        unset($_SESSION[$key . '_count'], $_SESSION[$key . '_until']);
    }

    private function rateLimitKey(): string
    {
        return 'rl_' . md5((string) $this->request->server('REMOTE_ADDR', ''));
    }

    private function authService(): AuthService
    {
        $database   = new Database();
        $connection = $database->connection();

        return new AuthService(
            new UserRepository($connection),
            $database->transactions(),
            $this->sessions
        );
    }

    private function userRepository(): UserRepository
    {
        return new UserRepository((new Database())->connection());
    }
}
