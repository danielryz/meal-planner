<?php

declare(strict_types=1);

namespace App\Auth;

final class CsrfTokenManager
{
    private const SESSION_KEY = 'csrf_tokens';

    public function token(string $formName): string
    {
        if (!isset($_SESSION[self::SESSION_KEY][$formName])) {
            $_SESSION[self::SESSION_KEY][$formName] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY][$formName];
    }

    public function isValid(string $formName, mixed $submittedToken): bool
    {
        if (!is_string($submittedToken)) {
            return false;
        }

        $storedToken = $_SESSION[self::SESSION_KEY][$formName] ?? null;

        return is_string($storedToken) && hash_equals($storedToken, $submittedToken);
    }
}
