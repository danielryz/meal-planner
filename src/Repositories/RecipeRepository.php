<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RecipeRepository extends AbstractRepository
{
    public function listPublic(array $filters, ?int $userId): array
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

        $where = implode(' AND ', $conditions);

        $favoriteJoin = $userId !== null
            ? 'LEFT JOIN favorite_recipes fr ON fr.recipe_id = r.id AND fr.user_id = :user_id'
            : '';

        $favoriteSelect = $userId !== null ? ', (fr.user_id IS NOT NULL) AS is_favorite' : ', FALSE AS is_favorite';

        $sql = "SELECT r.id, r.title, r.difficulty, r.prep_time_minutes, r.servings,
                    rc.code AS category_code, rc.label AS category_label
                    {$favoriteSelect}
                FROM recipes r
                LEFT JOIN recipe_categories rc ON rc.id = r.category_id
                {$favoriteJoin}
                WHERE {$where}
                ORDER BY r.published_at DESC";

        $stmt = $this->connection->prepare($sql);

        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['diet_tags'] = $this->dietTagsForRecipe((int) $row['id']);
        }

        return $rows;
    }

    public function findById(int $recipeId, ?int $userId): ?array
    {
        $favoriteSelect = $userId !== null ? ', (fr.user_id IS NOT NULL) AS is_favorite' : ', FALSE AS is_favorite';
        $favoriteJoin   = $userId !== null
            ? 'LEFT JOIN favorite_recipes fr ON fr.recipe_id = r.id AND fr.user_id = :user_id'
            : '';

        $stmt = $this->connection->prepare(
            "SELECT r.id, r.title, r.description, r.difficulty, r.prep_time_minutes, r.servings,
                r.status, r.visibility,
                rc.code AS category_code, rc.label AS category_label,
                up.display_name AS author_name,
                rn.calories, rn.protein_grams, rn.fat_grams, rn.carbohydrates_grams, rn.fiber_grams
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
            'SELECT code, label FROM recipe_categories ORDER BY sort_order'
        );
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->connection->prepare('SELECT code, label FROM diet_types ORDER BY id');
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
                 prep_time_minutes, servings, status, visibility,
                 submitted_at, approved_at, published_at)
            VALUES
                (:author_id, :category_id, :title, :slug, :description, :difficulty,
                 :prep_time, :servings, :status, :visibility,
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
