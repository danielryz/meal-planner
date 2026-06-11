<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RecipeRepository extends AbstractRepository
{
    public function listPublic(array $filters, ?int $userId, int $page = 1, int $perPage = 12): array
    {
        $conditions = ["r.visibility = 'public'", "r.status = 'approved'"];
        $params = [];

        if (!empty($filters['q'])) {
            $conditions[] = 'r.title ILIKE :q';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['difficulty'])) {
            $conditions[] = 'r.difficulty = :difficulty';
            $params[':difficulty'] = $filters['difficulty'];
        }

        if (!empty($filters['category'])) {
            $conditions[] = 'rc.code = :category';
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['time'])) {
            $conditions[] = 'r.prep_time_minutes <= :time';
            $params[':time'] = (int) $filters['time'];
        }

        if (!empty($filters['diet']) && is_array($filters['diet'])) {
            $diets = array_values(array_filter($filters['diet']));
            if (!empty($diets)) {
                $placeholders = [];
                foreach ($diets as $i => $d) {
                    $key = ':diet_' . $i;
                    $placeholders[] = $key;
                    $params[$key] = (string) $d;
                }
                $inList = implode(',', $placeholders);
                $conditions[] = "EXISTS (SELECT 1 FROM recipe_diet_types rdt JOIN diet_types dt ON dt.id = rdt.diet_type_id WHERE rdt.recipe_id = r.id AND dt.code IN ({$inList}))";
            }
        }

        $favoriteJoin = $userId !== null
            ? 'LEFT JOIN favorite_recipes fr ON fr.recipe_id = r.id AND fr.user_id = :user_id'
            : '';

        $favoriteSelect = $userId !== null ? ', (fr.user_id IS NOT NULL) AS is_favorite' : ', FALSE AS is_favorite';

        if (!empty($filters['favorites']) && $filters['favorites'] === '1' && $userId !== null) {
            $conditions[] = 'fr.user_id IS NOT NULL';
        }

        $where = implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*)
                     FROM recipes r
                     LEFT JOIN recipe_categories rc ON rc.id = r.category_id
                     {$favoriteJoin}
                     WHERE {$where}";

        $countStmt = $this->connection->prepare($countSql);
        if ($userId !== null) {
            $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT r.id, r.title, r.difficulty, r.prep_time_minutes, r.servings,
                    rc.code AS category_code, rc.label AS category_label,
                    (SELECT ROUND(AVG(rr.score)::numeric, 1) FROM recipe_ratings rr WHERE rr.recipe_id = r.id) AS avg_rating,
                    (SELECT COUNT(*) FROM recipe_ratings rr WHERE rr.recipe_id = r.id) AS rating_count
                    {$favoriteSelect}
                FROM recipes r
                LEFT JOIN recipe_categories rc ON rc.id = r.category_id
                {$favoriteJoin}
                WHERE {$where}
                ORDER BY r.published_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($sql);

        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['diet_tags'] = $this->dietTagsForRecipe((int) $row['id']);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function findById(int $recipeId, ?int $userId): ?array
    {
        $favoriteSelect = $userId !== null ? ', (fr.user_id IS NOT NULL) AS is_favorite' : ', FALSE AS is_favorite';
        $favoriteJoin   = $userId !== null
            ? 'LEFT JOIN favorite_recipes fr ON fr.recipe_id = r.id AND fr.user_id = :user_id'
            : '';

        $userRatingSelect = $userId !== null
            ? ', (SELECT score FROM recipe_ratings WHERE user_id = :rating_user_id AND recipe_id = r.id) AS user_rating'
            : ', NULL::integer AS user_rating';

        $stmt = $this->connection->prepare(
            "SELECT r.id, r.author_user_id, r.category_id, r.title, r.description, r.difficulty, r.prep_time_minutes, r.servings,
                r.status, r.visibility, r.video_url,
                rc.code AS category_code, rc.label AS category_label,
                up.display_name AS author_name,
                rn.calories, rn.protein_grams, rn.fat_grams, rn.carbohydrates_grams, rn.fiber_grams,
                (SELECT ROUND(AVG(rr.score)::numeric, 1) FROM recipe_ratings rr WHERE rr.recipe_id = r.id) AS avg_rating,
                (SELECT COUNT(*) FROM recipe_ratings rr WHERE rr.recipe_id = r.id) AS rating_count
                {$userRatingSelect}
                {$favoriteSelect}
            FROM recipes r
            LEFT JOIN recipe_categories rc ON rc.id = r.category_id
            JOIN users u ON u.id = r.author_user_id
            JOIN user_profiles up ON up.user_id = u.id
            LEFT JOIN recipe_nutrition rn ON rn.recipe_id = r.id
            {$favoriteJoin}
            WHERE r.id = :recipe_id"
        );

        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);

        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':rating_user_id', $userId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['ingredients'] = $this->ingredientsForRecipe($recipeId);
        $row['steps']       = $this->stepsForRecipe($recipeId);
        $row['diet_tags']   = $this->dietTagsForRecipe($recipeId);

        return $row;
    }

    public function findRelated(int $recipeId, ?int $categoryId, int $limit = 3): array
    {
        if ($categoryId === null) {
            return [];
        }
        $stmt = $this->connection->prepare(
            "SELECT r.id, r.title, r.prep_time_minutes, r.servings,
                    rc.label AS category_label
             FROM recipes r
             LEFT JOIN recipe_categories rc ON rc.id = r.category_id
             WHERE r.category_id = :cat_id
               AND r.id <> :recipe_id
               AND r.visibility = 'public'
               AND r.status = 'approved'
             ORDER BY RANDOM()
             LIMIT :limit"
        );
        $stmt->bindValue(':cat_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleFavorite(int $recipeId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1 FROM favorite_recipes WHERE user_id = :user_id AND recipe_id = :recipe_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
        $exists = $stmt->fetchColumn() !== false;

        if ($exists) {
            $stmt = $this->connection->prepare(
                'DELETE FROM favorite_recipes WHERE user_id = :user_id AND recipe_id = :recipe_id'
            );
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO favorite_recipes (user_id, recipe_id) VALUES (:user_id, :recipe_id)'
            );
        }

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();

        return !$exists;
    }

    public function listFilterOptions(): array
    {
        $stmt = $this->connection->prepare(
            'SELECT code AS id, label FROM recipe_categories ORDER BY sort_order'
        );
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->connection->prepare('SELECT code AS id, label FROM diet_types ORDER BY id');
        $stmt->execute();
        $diets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'categories'   => $categories,
            'difficulties' => [
                ['id' => 'easy',     'label' => 'Łatwy'],
                ['id' => 'medium',   'label' => 'Średni'],
                ['id' => 'advanced', 'label' => 'Zaawansowany'],
            ],
            'diets'        => $diets,
            'timeBuckets'  => [
                ['id' => '15',  'label' => 'Do 15 min'],
                ['id' => '30',  'label' => 'Do 30 min'],
                ['id' => '60',  'label' => 'Do 1 godz.'],
                ['id' => '120', 'label' => 'Do 2 godz.'],
            ],
        ];
    }

    public function listByAuthor(int $userId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT r.id, r.title, r.status, r.visibility, r.submitted_at,
                r.updated_at, rc.label AS category_label,
                (SELECT reason FROM recipe_publication_reviews
                 WHERE recipe_id = r.id AND reason IS NOT NULL
                 ORDER BY created_at DESC LIMIT 1) AS review_reason
            FROM recipes r
            LEFT JOIN recipe_categories rc ON rc.id = r.category_id
            WHERE r.author_user_id = :user_id
            ORDER BY r.updated_at DESC"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteDraft(int $recipeId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            "SELECT author_user_id, status FROM recipes WHERE id = :id"
        );
        $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return false;
        }

        if ((int) $row['author_user_id'] !== $userId) {
            throw new \RuntimeException('forbidden');
        }

        if ($row['status'] !== 'draft') {
            throw new \RuntimeException('invalid_status');
        }

        $stmt = $this->connection->prepare('DELETE FROM recipes WHERE id = :id');
        $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public function getReviewQueue(): array
    {
        $stmt = $this->connection->prepare(
            "SELECT r.id, r.title, r.description, r.difficulty, r.prep_time_minutes, r.servings,
                r.submitted_at, rc.label AS category_label,
                u.email AS author_email, up.display_name AS author_name,
                (SELECT reason FROM recipe_publication_reviews
                 WHERE recipe_id = r.id AND reason IS NOT NULL
                 ORDER BY created_at DESC LIMIT 1) AS review_note
            FROM recipes r
            JOIN users u ON u.id = r.author_user_id
            JOIN user_profiles up ON up.user_id = u.id
            LEFT JOIN recipe_categories rc ON rc.id = r.category_id
            WHERE r.status = 'submitted'
            ORDER BY r.submitted_at ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $ingredients = $this->ingredientsForRecipe((int) $row['id']);
            $row['ingredients'] = array_map(
                fn($i) => $i['note'] ? "{$i['name']} — {$i['amount']} ({$i['note']})" : "{$i['name']} — {$i['amount']}",
                $ingredients
            );
        }

        return $rows;
    }

    public function approveRecipe(int $recipeId, int $reviewerUserId): void
    {
        $this->assertReviewTransition($recipeId, ['submitted']);

        $this->connection->beginTransaction();
        try {
            $stmt = $this->connection->prepare(
                "UPDATE recipes SET status = 'approved', visibility = 'public',
                    approved_at = CURRENT_TIMESTAMP, published_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id"
            );
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            $this->insertReviewRecord($recipeId, $reviewerUserId, 'approved', null);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function requestChanges(int $recipeId, int $reviewerUserId, string $note): void
    {
        $this->assertReviewTransition($recipeId, ['submitted']);

        $this->connection->beginTransaction();
        try {
            $stmt = $this->connection->prepare(
                "UPDATE recipes SET status = 'changes_requested', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            $this->insertReviewRecord($recipeId, $reviewerUserId, 'changes_requested', $note);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function rejectRecipe(int $recipeId, int $reviewerUserId, string $note): void
    {
        $this->assertReviewTransition($recipeId, ['submitted']);

        $this->connection->beginTransaction();
        try {
            $stmt = $this->connection->prepare(
                "UPDATE recipes SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            $this->insertReviewRecord($recipeId, $reviewerUserId, 'rejected', $note);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function assertReviewTransition(int $recipeId, array $allowedStatuses): void
    {
        $stmt = $this->connection->prepare('SELECT status FROM recipes WHERE id = :id');
        $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException('not_found');
        }

        if (!in_array($row['status'], $allowedStatuses, true)) {
            throw new \RuntimeException('invalid_status');
        }
    }

    private function insertReviewRecord(int $recipeId, int $reviewerUserId, string $action, ?string $reason): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_publication_reviews (recipe_id, reviewer_user_id, action, reason)
            VALUES (:recipe_id, :reviewer_id, :action, :reason)'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':reviewer_id', $reviewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':reason', $reason);
        $stmt->execute();
    }

    public function createDraft(int $userId, array $data): int
    {
        $this->connection->beginTransaction();

        try {
            $recipeId = $this->createRecipe($userId, array_merge($data, [
                'status'     => 'draft',
                'visibility' => 'private',
            ]));

            foreach ($data['ingredients'] ?? [] as $i => $ingredient) {
                $this->addIngredient(
                    $recipeId,
                    $i + 1,
                    (string) ($ingredient['name'] ?? ''),
                    (string) ($ingredient['amount'] ?? ''),
                    isset($ingredient['note']) ? (string) $ingredient['note'] : null
                );
            }

            foreach ($data['steps'] ?? [] as $i => $step) {
                $this->addStep($recipeId, $i + 1, (string) ($step['instruction'] ?? ''));
            }

            foreach ($data['dietTypes'] ?? [] as $code) {
                $this->addDietType($recipeId, (string) $code);
            }

            foreach ($data['tags'] ?? [] as $code) {
                $this->addTag($recipeId, (string) $code);
            }

            if (!empty($data['nutrition'])) {
                $this->addNutrition($recipeId, $data['nutrition']);
            }

            $this->connection->commit();

            return $recipeId;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function updateDraft(int $recipeId, int $userId, array $data): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT author_user_id, status FROM recipes WHERE id = :id'
        );
        $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return false;
        }

        if ((int) $row['author_user_id'] !== $userId) {
            throw new \RuntimeException('forbidden');
        }

        if (!in_array($row['status'], ['draft', 'changes_requested'], true)) {
            throw new \RuntimeException('invalid_status');
        }

        $categoryId = null;
        if (!empty($data['categoryCode'])) {
            $stmt = $this->connection->prepare('SELECT id FROM recipe_categories WHERE code = :code');
            $stmt->bindValue(':code', $data['categoryCode']);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            $categoryId = $id !== false ? (int) $id : null;
        }

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare(
                "UPDATE recipes
                 SET title = :title, description = :description, category_id = :category_id,
                     difficulty = :difficulty, prep_time_minutes = :prep_time,
                     servings = :servings, video_url = :video_url,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $stmt->bindValue(':title', $data['title']);
            $stmt->bindValue(':description', $data['description']);
            $stmt->bindValue(':category_id', $categoryId, $categoryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':difficulty', $data['difficulty'] ?? 'easy');
            $stmt->bindValue(':prep_time', (int) ($data['prepTimeMinutes'] ?? 30), PDO::PARAM_INT);
            $stmt->bindValue(':servings', (int) ($data['servings'] ?? 2), PDO::PARAM_INT);
            $stmt->bindValue(':video_url', $data['videoUrl'] ?? null);
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->connection->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = :id');
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($data['ingredients'] ?? [] as $i => $ingredient) {
                $this->addIngredient(
                    $recipeId,
                    $i + 1,
                    (string) ($ingredient['name'] ?? ''),
                    (string) ($ingredient['amount'] ?? ''),
                    isset($ingredient['note']) ? (string) $ingredient['note'] : null
                );
            }

            $stmt = $this->connection->prepare('DELETE FROM recipe_steps WHERE recipe_id = :id');
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($data['steps'] ?? [] as $i => $step) {
                $this->addStep($recipeId, $i + 1, (string) ($step['instruction'] ?? ''));
            }

            $this->connection->commit();

            return true;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function submitForReview(int $recipeId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT author_user_id, status FROM recipes WHERE id = :id'
        );
        $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return false;
        }

        if ((int) $row['author_user_id'] !== $userId) {
            throw new \RuntimeException('forbidden');
        }

        if (!in_array($row['status'], ['draft', 'changes_requested'], true)) {
            throw new \RuntimeException('invalid_status');
        }

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare(
                "UPDATE recipes
                SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id"
            );
            $stmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->connection->prepare(
                "INSERT INTO recipe_publication_reviews (recipe_id, action) VALUES (:recipe_id, 'submitted')"
            );
            $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();

            $this->connection->commit();

            return true;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->connection->prepare('SELECT 1 FROM recipes WHERE lower(slug) = lower(:slug) LIMIT 1');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    public function createRecipe(int $authorId, array $data): int
    {
        $categoryId = null;
        if (!empty($data['categoryCode'])) {
            $stmt = $this->connection->prepare('SELECT id FROM recipe_categories WHERE code = :code');
            $stmt->bindValue(':code', $data['categoryCode']);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            $categoryId = $id !== false ? (int) $id : null;
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO recipes
                (author_user_id, category_id, title, slug, description, difficulty,
                 prep_time_minutes, servings, status, visibility, video_url,
                 submitted_at, approved_at, published_at)
            VALUES
                (:author_id, :category_id, :title, :slug, :description, :difficulty,
                 :prep_time, :servings, :status, :visibility, :video_url,
                 :submitted_at, :approved_at, :published_at)
            RETURNING id"
        );
        $status     = $data['status'] ?? 'draft';
        $visibility = $data['visibility'] ?? 'private';
        $isApproved = $status === 'approved';

        $stmt->bindValue(':author_id', $authorId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, $categoryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':slug', $data['slug']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':difficulty', $data['difficulty'] ?? 'easy');
        $stmt->bindValue(':prep_time', (int) ($data['prepTimeMinutes'] ?? 30), PDO::PARAM_INT);
        $stmt->bindValue(':servings', (int) ($data['servings'] ?? 2), PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':visibility', $visibility);
        $stmt->bindValue(':video_url', $data['videoUrl'] ?? null);
        $stmt->bindValue(':submitted_at', $isApproved ? date('Y-m-d H:i:sP') : null);
        $stmt->bindValue(':approved_at', $isApproved ? date('Y-m-d H:i:sP') : null);
        $stmt->bindValue(':published_at', $isApproved ? date('Y-m-d H:i:sP') : null);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function addNutrition(int $recipeId, array $data): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_nutrition (recipe_id, calories, protein_grams, fat_grams, carbohydrates_grams, fiber_grams)
            VALUES (:recipe_id, :calories, :protein, :fat, :carbs, :fiber)
            ON CONFLICT (recipe_id) DO UPDATE SET
                calories = EXCLUDED.calories,
                protein_grams = EXCLUDED.protein_grams,
                fat_grams = EXCLUDED.fat_grams,
                carbohydrates_grams = EXCLUDED.carbohydrates_grams,
                fiber_grams = EXCLUDED.fiber_grams'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':calories', $data['calories'] ?? null);
        $stmt->bindValue(':protein', $data['protein'] ?? null);
        $stmt->bindValue(':fat', $data['fat'] ?? null);
        $stmt->bindValue(':carbs', $data['carbs'] ?? null);
        $stmt->bindValue(':fiber', $data['fiber'] ?? null);
        $stmt->execute();
    }

    public function addIngredient(int $recipeId, int $position, string $name, string $amount, ?string $note = null): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_ingredients (recipe_id, position, name, amount, note)
            VALUES (:recipe_id, :position, :name, :amount, :note)
            ON CONFLICT (recipe_id, position) DO UPDATE SET name = EXCLUDED.name, amount = EXCLUDED.amount, note = EXCLUDED.note'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':position', $position, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':note', $note);
        $stmt->execute();
    }

    public function addStep(int $recipeId, int $position, string $instruction): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_steps (recipe_id, position, instruction)
            VALUES (:recipe_id, :position, :instruction)
            ON CONFLICT (recipe_id, position) DO UPDATE SET instruction = EXCLUDED.instruction'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':position', $position, PDO::PARAM_INT);
        $stmt->bindValue(':instruction', $instruction);
        $stmt->execute();
    }

    public function addDietType(int $recipeId, string $dietCode): void
    {
        $stmt = $this->connection->prepare('SELECT id FROM diet_types WHERE code = :code');
        $stmt->bindValue(':code', $dietCode);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return;
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_diet_types (recipe_id, diet_type_id) VALUES (:recipe_id, :diet_type_id)
            ON CONFLICT DO NOTHING'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':diet_type_id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function addTag(int $recipeId, string $tagCode): void
    {
        $stmt = $this->connection->prepare('SELECT id FROM recipe_tags WHERE code = :code');
        $stmt->bindValue(':code', $tagCode);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return;
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO recipe_tag_assignments (recipe_id, tag_id) VALUES (:recipe_id, :tag_id)
            ON CONFLICT DO NOTHING'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->bindValue(':tag_id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function addFavorite(int $userId, int $recipeId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO favorite_recipes (user_id, recipe_id) VALUES (:user_id, :recipe_id)
            ON CONFLICT DO NOTHING'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function ingredientsForRecipe(int $recipeId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT name, amount, note FROM recipe_ingredients
            WHERE recipe_id = :recipe_id ORDER BY position'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function stepsForRecipe(int $recipeId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT position, instruction FROM recipe_steps
            WHERE recipe_id = :recipe_id ORDER BY position'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dietTagsForRecipe(int $recipeId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT dt.code FROM recipe_diet_types rdt
            JOIN diet_types dt ON dt.id = rdt.diet_type_id
            WHERE rdt.recipe_id = :recipe_id'
        );
        $stmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
