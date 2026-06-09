<?php

declare(strict_types=1);

namespace App\Entities;

use App\Auth\AuthenticatedUser;

final class AuthUser
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $username,
        private readonly string $passwordHash,
        private readonly bool $isActive,
        private readonly string $role,
        private readonly string $displayName,
        private readonly bool $emailVerified,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function emailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function authenticatedUser(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            $this->id,
            $this->email,
            $this->username,
            $this->role,
            $this->displayName,
            $this->emailVerified,
        );
    }
}
