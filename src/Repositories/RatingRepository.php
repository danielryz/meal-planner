<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RatingRepository extends AbstractRepository
{
    public function findStatsByRecipe(int $recipeId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT ROUND(AVG(score)::numeric, 1) AS average, COUNT(*) AS count
             FROM recipe_ratings
             WHERE recipe_id = :recipe_id'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'average' => ($row && $row['average'] !== null) ? (float) $row['average'] : null,
            'count'   => (int) ($row['count'] ?? 0),
        ];
    }

    public function findByUserAndRecipe(int $userId, int $recipeId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT score, comment FROM recipe_ratings
             WHERE user_id = :user_id AND recipe_id = :recipe_id'
        );
        $stmt->execute([':user_id' => $userId, ':recipe_id' => $recipeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function upsert(int $userId, int $recipeId, int $score, ?string $comment): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_ratings (user_id, recipe_id, score, comment)
             VALUES (:user_id, :recipe_id, :score, :comment)
             ON CONFLICT (user_id, recipe_id) DO UPDATE
             SET score = EXCLUDED.score, comment = EXCLUDED.comment, updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':user_id'   => $userId,
            ':recipe_id' => $recipeId,
            ':score'     => $score,
            ':comment'   => $comment,
        ]);
    }

    public function delete(int $userId, int $recipeId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM recipe_ratings WHERE user_id = :user_id AND recipe_id = :recipe_id'
        );
        $stmt->execute([':user_id' => $userId, ':recipe_id' => $recipeId]);
    }
}
