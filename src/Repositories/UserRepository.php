<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\AuthUser;
use PDO;

final class UserRepository extends AbstractRepository
{
    public function findAuthUserByEmail(string $email): ?AuthUser
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.email, u.username, u.password_hash, u.is_active, u.email_verified_at,
                    r.name AS role, up.display_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            JOIN user_profiles up ON up.user_id = u.id
            WHERE lower(u.email) = lower(:email)
            LIMIT 1'
        );
        $statement->bindValue(':email', $email);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->mapAuthUser($row);
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $statement->bindValue(':email', $email);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function usernameExists(string $username): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM users WHERE lower(username) = lower(:username) LIMIT 1');
        $statement->bindValue(':username', $username);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function createUser(string $email, string $username, string $passwordHash, string $displayName): AuthUser
    {
        $roleId = $this->userRoleId();
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, email, username, password_hash)
            VALUES (:role_id, :email, :username, :password_hash)
            RETURNING id'
        );
        $statement->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $statement->bindValue(':email', $email);
        $statement->bindValue(':username', $username);
        $statement->bindValue(':password_hash', $passwordHash);
        $statement->execute();

        $userId = (int) $statement->fetchColumn();

        $this->createProfile($userId, $displayName);
        $this->createDefaultSettings($userId);

        $user = $this->findAuthUserByEmail($email);

        if ($user === null) {
            throw new \RuntimeException('Created user cannot be loaded.');
        }

        return $user;
    }

    public function markLoggedIn(int $userId): void
    {
        $statement = $this->connection->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->bindValue(':id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }

    public function markEmailVerified(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP
            WHERE id = :id AND email_verified_at IS NULL'
        );
        $statement->bindValue(':id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }

    public function setPassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->connection->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->bindValue(':hash', $passwordHash);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findEmailById(int $userId): ?string
    {
        $statement = $this->connection->prepare('SELECT email FROM users WHERE id = :id');
        $statement->bindValue(':id', $userId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetchColumn();
        return $result !== false ? (string) $result : null;
    }

    public function createEmailToken(int $userId, string $type, int $ttlSeconds): string
    {
        $statement = $this->connection->prepare(
            'DELETE FROM email_tokens WHERE user_id = :user_id AND type = :type'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':type', $type);
        $statement->execute();

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $statement = $this->connection->prepare(
            "INSERT INTO email_tokens (user_id, token_hash, type, expires_at)
            VALUES (:user_id, :token_hash, :type, NOW() + INTERVAL '1 second' * :ttl)"
        );
        $statement->bindValue(':user_id',    $userId, PDO::PARAM_INT);
        $statement->bindValue(':token_hash', $tokenHash);
        $statement->bindValue(':type',       $type);
        $statement->bindValue(':ttl',        $ttlSeconds, PDO::PARAM_INT);
        $statement->execute();

        return $rawToken;
    }

    public function findAndConsumeEmailToken(string $rawToken, string $type): ?int
    {
        $tokenHash = hash('sha256', $rawToken);

        $statement = $this->connection->prepare(
            'UPDATE email_tokens
            SET used_at = NOW()
            WHERE token_hash = :token_hash
              AND type = :type
              AND used_at IS NULL
              AND expires_at > NOW()
            RETURNING user_id'
        );
        $statement->bindValue(':token_hash', $tokenHash);
        $statement->bindValue(':type', $type);
        $statement->execute();

        $userId = $statement->fetchColumn();
        return $userId !== false ? (int) $userId : null;
    }

    public function recordActivity(?int $userId, string $eventType): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO user_activity_events (user_id, event_type)
            VALUES (:user_id, :event_type)'
        );
        $statement->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':event_type', $eventType);
        $statement->execute();
    }

    public function createUserWithRole(
        string $roleName,
        string $email,
        string $username,
        string $passwordHash,
        string $displayName
    ): AuthUser {
        $roleId = $this->roleIdByName($roleName);
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, email, username, password_hash, email_verified_at)
            VALUES (:role_id, :email, :username, :password_hash, CURRENT_TIMESTAMP)
            RETURNING id'
        );
        $statement->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $statement->bindValue(':email', $email);
        $statement->bindValue(':username', $username);
        $statement->bindValue(':password_hash', $passwordHash);
        $statement->execute();

        $userId = (int) $statement->fetchColumn();

        $this->createProfile($userId, $displayName);
        $this->createDefaultSettings($userId);

        $user = $this->findAuthUserByEmail($email);

        if ($user === null) {
            throw new \RuntimeException("Seeded user cannot be loaded: {$email}");
        }

        return $user;
    }

    public function findAll(): array
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.email, u.username, u.is_active, u.last_login_at,
                r.name AS role, up.display_name, up.avatar_initials
            FROM users u
            JOIN roles r ON r.id = u.role_id
            JOIN user_profiles up ON up.user_id = u.id
            ORDER BY u.created_at ASC'
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRole(int $userId, string $roleName): bool
    {
        $statement = $this->connection->prepare('SELECT id FROM roles WHERE name = :name');
        $statement->bindValue(':name', $roleName);
        $statement->execute();
        $roleId = $statement->fetchColumn();

        if ($roleId === false) {
            return false;
        }

        $statement = $this->connection->prepare(
            'UPDATE users SET role_id = :role_id, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id'
        );
        $statement->bindValue(':role_id', (int) $roleId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        return true;
    }

    public function updateStatus(int $userId, bool $isActive): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id'
        );
        $statement->bindValue(':is_active', $isActive, PDO::PARAM_BOOL);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }

    public function findById(int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.is_active, r.name AS role
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = :user_id'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapAuthUser(array $row): AuthUser
    {
        return new AuthUser(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['username'],
            (string) $row['password_hash'],
            (bool) $row['is_active'],
            (string) $row['role'],
            (string) $row['display_name'],
            $row['email_verified_at'] !== null,
        );
    }

    private function userRoleId(): int
    {
        return $this->roleIdByName('user');
    }

    private function roleIdByName(string $name): int
    {
        $statement = $this->connection->prepare('SELECT id FROM roles WHERE name = :name');
        $statement->bindValue(':name', $name);
        $statement->execute();

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new \RuntimeException("Role not found: {$name}");
        }

        return (int) $id;
    }

    private function createProfile(int $userId, string $displayName): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO user_profiles (user_id, display_name, avatar_initials)
            VALUES (:user_id, :display_name, :avatar_initials)'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':display_name', $displayName);
        $statement->bindValue(':avatar_initials', $this->initials($displayName));
        $statement->execute();
    }

    private function createDefaultSettings(int $userId): void
    {
        foreach ([
            'INSERT INTO user_account_settings (user_id, password_changed_at) VALUES (:user_id, CURRENT_TIMESTAMP)',
            'INSERT INTO user_notification_preferences (user_id) VALUES (:user_id)',
            'INSERT INTO user_food_preferences (user_id) VALUES (:user_id)',
        ] as $sql) {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    private function initials(string $displayName): string
    {
        $words = preg_split('/\s+/', trim($displayName)) ?: [];
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= substr($word, 0, 1);
        }

        return strtoupper($initials ?: 'U');
    }
}
