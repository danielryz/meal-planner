<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Request;

final class SessionManager
{
    private const USER_KEY = 'auth_user';

    public function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = in_array($request->server('HTTPS'), ['on', '1'], true)
            || $request->server('SERVER_PORT') === '443';

        session_set_cookie_params([
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function login(AuthenticatedUser $user): void
    {
        session_regenerate_id(true);

        $_SESSION[self::USER_KEY] = [
            'id'             => $user->id(),
            'email'          => $user->email(),
            'username'       => $user->username(),
            'role'           => $user->role(),
            'display_name'   => $user->displayName(),
            'email_verified' => $user->isEmailVerified(),
            'is_logged_in'   => true,
        ];
    }

    public function currentUser(): ?AuthenticatedUser
    {
        $user = $_SESSION[self::USER_KEY] ?? null;

        if (!is_array($user) || ($user['is_logged_in'] ?? false) !== true) {
            return null;
        }

        return new AuthenticatedUser(
            (int) $user['id'],
            (string) $user['email'],
            (string) $user['username'],
            (string) $user['role'],
            (string) $user['display_name'],
            (bool) ($user['email_verified'] ?? false),
        );
    }

    public function markEmailVerified(): void
    {
        if (isset($_SESSION[self::USER_KEY])) {
            $_SESSION[self::USER_KEY]['email_verified'] = true;
        }
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUser() instanceof AuthenticatedUser;
    }

    public function extendSession(int $lifetimeSeconds): void
    {
        ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);

        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            [
                'expires'  => time() + $lifetimeSeconds,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]
        );
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
