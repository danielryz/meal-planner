<?php

declare(strict_types=1);

namespace App\Auth;

final class AuthResult
{
    /**
     * @param array<string, string> $errors
     */
    private function __construct(
        private readonly bool $success,
        private readonly array $errors = []
    ) {
    }

    public static function success(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, string> $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
