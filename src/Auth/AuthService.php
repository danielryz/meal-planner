<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\TransactionManager;
use App\Repositories\UserRepository;
use App\Services\MailService;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TransactionManager $transactions,
        private readonly SessionManager $sessions
    ) {
    }

    public function login(string $email, string $password): AuthResult
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return AuthResult::failure(['global' => 'Nieprawidłowy adres e-mail lub hasło.']);
        }

        $user = $this->users->findAuthUserByEmail($email);

        if ($user === null || !$user->isActive() || !password_verify($password, $user->passwordHash())) {
            $this->users->recordActivity(null, 'login_failed');

            return AuthResult::failure(['global' => 'Nieprawidłowy adres e-mail lub hasło.']);
        }

        $this->sessions->login($user->authenticatedUser());
        $this->users->markLoggedIn($user->id());
        $this->users->recordActivity($user->id(), 'login_success');

        return AuthResult::success();
    }

    public function register(string $displayName, string $email, string $password, bool $termsAccepted): AuthResult
    {
        $displayName = trim($displayName);
        $email       = strtolower(trim($email));
        $password    = trim($password);
        $errors      = [];

        if (strlen($displayName) < 2 || strlen($displayName) > 120) {
            $errors['displayName'] = 'Podaj imię i nazwisko.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Podaj poprawny adres e-mail.';
        } elseif ($this->users->emailExists($email)) {
            $errors['email'] = 'Ten adres e-mail jest już zajęty.';
        }

        if (strlen($password) < 8 || strlen($password) > 128) {
            $errors['password'] = 'Hasło musi mieć od 8 do 128 znaków.';
        }

        if (!$termsAccepted) {
            $errors['termsAccepted'] = 'Zaakceptuj regulamin i politykę prywatności.';
        }

        if ($errors !== []) {
            return AuthResult::failure($errors);
        }

        [$user, $rawToken] = $this->transactions->transactional(function () use ($displayName, $email, $password, $termsAccepted) {
            $user     = $this->users->createUser(
                $email,
                $this->createUsername($displayName, $email),
                password_hash($password, PASSWORD_DEFAULT),
                $displayName,
                $termsAccepted
            );
            $this->users->recordActivity($user->id(), 'registered');
            $rawToken = $this->users->createEmailToken($user->id(), 'activation', 48 * 3600);

            return [$user, $rawToken];
        });

        $this->sessions->login($user->authenticatedUser());

        try {
            (new MailService())->sendActivationEmail($email, $displayName, $rawToken);
        } catch (\Throwable) {
            // mail failure must not break registration
        }

        return AuthResult::success();
    }

    private function createUsername(string $displayName, string $email): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '_', strtolower($displayName));
        $base = trim((string) $base, '_');

        if ($base === '') {
            $base = strstr($email, '@', true) ?: 'user';
        }

        $base     = substr($base, 0, 40);
        $username = $base;
        $suffix   = 1;

        while ($this->users->usernameExists($username)) {
            $username = substr($base, 0, 36) . '_' . $suffix;
            $suffix++;
        }

        return $username;
    }
}
