<?php

declare(strict_types=1);

namespace App\Auth;

final class AuthenticatedUser
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $username,
        private readonly string $role,
        private readonly string $displayName,
        private readonly bool $emailVerified = false,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function isPending(): bool
    {
        return !$this->emailVerified;
    }
}
