<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProfileRepository extends AbstractRepository
{
    public function findProfileByUserId(int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT
                u.id,
                u.email,
                u.username,
                u.is_active,
                u.last_login_at,
                u.created_at,
                r.name AS role,
                up.display_name,
                up.avatar_initials,
                up.bio,
                up.is_public,
                uas.password_changed_at,
                (SELECT COUNT(*) FROM favorite_recipes fr WHERE fr.user_id = u.id) AS favorite_recipes_count,
                (SELECT COUNT(*) FROM recipes rec WHERE rec.author_id = u.id) AS own_recipes_count
            FROM users u
            JOIN roles r ON r.id = u.role_id
            JOIN user_profiles up ON up.user_id = u.id
            LEFT JOIN user_account_settings uas ON uas.user_id = u.id
            WHERE u.id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findFavoritesByUserId(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT r.id, r.title, r.slug, r.status, r.created_at,
                    up.display_name AS author_name
             FROM favorite_recipes fr
             JOIN recipes r ON r.id = fr.recipe_id
             JOIN users u ON u.id = r.author_id
             JOIN user_profiles up ON up.user_id = u.id
             WHERE fr.user_id = :user_id
             ORDER BY fr.created_at DESC
             LIMIT 50'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRecipesByUserId(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, title, slug, status, created_at
             FROM recipes
             WHERE author_id = :user_id
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActivityByUserId(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT event_type, metadata, created_at
             FROM user_activity_events
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT 20'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDisplayName(int $userId, string $displayName): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE user_profiles SET display_name = :display_name, updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id'
        );
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateUsername(int $userId, string $username): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE users SET username = :username, updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id'
        );
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updatePasswordHash(int $userId, string $hash): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id'
        );
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $stmt2 = $this->connection->prepare(
            'UPDATE user_account_settings SET password_changed_at = CURRENT_TIMESTAMP WHERE user_id = :user_id'
        );
        $stmt2->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt2->execute();
    }

    public function findPasswordHashById(int $userId): ?string
    {
        $stmt = $this->connection->prepare('SELECT password_hash FROM users WHERE id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function usernameExistsForOther(string $username, int $excludeUserId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1 FROM users WHERE lower(username) = lower(:username) AND id != :user_id LIMIT 1'
        );
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':user_id', $excludeUserId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }
}
