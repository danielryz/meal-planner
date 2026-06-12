<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MealPlanRepository
{
    private const DAY_OFFSETS = [
        'monday'    => 0,
        'tuesday'   => 1,
        'wednesday' => 2,
        'thursday'  => 3,
        'friday'    => 4,
        'saturday'  => 5,
        'sunday'    => 6,
    ];

    private const SLOT_TYPE_MAP = [
        'breakfast' => 'breakfast',
        'lunch'     => 'lunch',
        'dinner'    => 'dinner',
        'snacks'    => 'snack',
        'snack'     => 'snack',
        'supper'    => 'supper',
    ];

    public function __construct(private readonly PDO $connection) {}

    public function create(int $userId, array $data): int
    {
        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO meal_plans (user_id, name, week_start_date, status, weekly_budget)
                 VALUES (?, ?, ?, 'active', ?) RETURNING id"
            );
            $stmt->execute([$userId, $data['name'], $data['weekStartDate'], (int) ($data['weeklyBudget'] ?? 0)]);
            $planId = (int) $stmt->fetchColumn();

            $position = 0;
            foreach ($data['planningDays'] as $day) {
                $offset = self::DAY_OFFSETS[$day] ?? null;

                if ($offset === null) {
                    continue;
                }

                $date = (new \DateTime($data['weekStartDate']))
                    ->modify("+{$offset} days")
                    ->format('Y-m-d');

                $stmt = $this->connection->prepare(
                    'INSERT INTO meal_plan_days (meal_plan_id, planned_date) VALUES (?, ?) RETURNING id'
                );
                $stmt->execute([$planId, $date]);
                $dayId = (int) $stmt->fetchColumn();

                foreach ($data['mealTypes'] as $pos => $mealType) {
                    $slotType = self::SLOT_TYPE_MAP[$mealType] ?? null;

                    if ($slotType === null) {
                        continue;
                    }

                    $stmt = $this->connection->prepare(
                        'INSERT INTO meal_slots (meal_plan_day_id, slot_type, position) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$dayId, $slotType, $pos + 1]);
                }

                $position++;
            }

            $this->connection->commit();

            return $planId;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, name, week_start_date, status, created_at
             FROM meal_plans
             WHERE user_id = ?
             ORDER BY week_start_date DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIdForUser(int $planId, int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, name, week_start_date, status
             FROM meal_plans
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$planId, $userId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            return null;
        }

        $stmt = $this->connection->prepare('
            SELECT
                mpd.id    AS day_id,
                mpd.planned_date,
                ms.id     AS slot_id,
                ms.slot_type,
                msr.recipe_id,
                msr.servings,
                r.title            AS recipe_title,
                r.prep_time_minutes
            FROM meal_plan_days mpd
            LEFT JOIN meal_slots ms          ON ms.meal_plan_day_id = mpd.id
            LEFT JOIN meal_slot_recipes msr  ON msr.meal_slot_id = ms.id
            LEFT JOIN recipes r              ON r.id = msr.recipe_id
            WHERE mpd.meal_plan_id = ?
            ORDER BY mpd.planned_date, ms.slot_type, msr.position
        ');
        $stmt->execute([$planId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $days = [];

        foreach ($rows as $row) {
            $dayId = $row['day_id'];

            if (!isset($days[$dayId])) {
                $days[$dayId] = [
                    'id'    => (int) $dayId,
                    'date'  => $row['planned_date'],
                    'slots' => [],
                ];
            }

            if ($row['slot_id'] === null) {
                continue;
            }

            $slotId = $row['slot_id'];

            if (!isset($days[$dayId]['slots'][$slotId])) {
                $days[$dayId]['slots'][$slotId] = [
                    'id'      => (int) $slotId,
                    'type'    => $row['slot_type'],
                    'recipes' => [],
                ];
            }

            if ($row['recipe_id'] !== null) {
                $days[$dayId]['slots'][$slotId]['recipes'][] = [
                    'id'             => (int) $row['recipe_id'],
                    'title'          => $row['recipe_title'],
                    'servings'       => (int) $row['servings'],
                    'prepTimeMinutes' => (int) $row['prep_time_minutes'],
                ];
            }
        }

        $plan['days'] = array_values(array_map(static function (array $day): array {
            $day['slots'] = array_values($day['slots']);

            return $day;
        }, $days));

        return $plan;
    }

    public function planBelongsToUser(int $planId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1 FROM meal_plans WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$planId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    public function slotBelongsToPlan(int $slotId, int $planId): bool
    {
        $stmt = $this->connection->prepare('
            SELECT 1 FROM meal_slots ms
            JOIN meal_plan_days mpd ON mpd.id = ms.meal_plan_day_id
            WHERE ms.id = ? AND mpd.meal_plan_id = ?
        ');
        $stmt->execute([$slotId, $planId]);

        return (bool) $stmt->fetchColumn();
    }

    public function addRecipeToSlot(int $slotId, int $recipeId, int $servings): void
    {
        $stmt = $this->connection->prepare('
            INSERT INTO meal_slot_recipes (meal_slot_id, recipe_id, servings, position)
            VALUES (
                ?, ?, ?,
                COALESCE((SELECT MAX(position) FROM meal_slot_recipes WHERE meal_slot_id = ?), 0) + 1
            )
            ON CONFLICT (meal_slot_id, recipe_id) DO UPDATE SET servings = EXCLUDED.servings
        ');
        $stmt->execute([$slotId, $recipeId, $servings, $slotId]);
    }

    public function removeRecipeFromSlot(int $slotId, int $recipeId): bool
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM meal_slot_recipes WHERE meal_slot_id = ? AND recipe_id = ?'
        );
        $stmt->execute([$slotId, $recipeId]);

        return $stmt->rowCount() > 0;
    }

    public function getSlotsForPlan(int $planId): array
    {
        $stmt = $this->connection->prepare('
            SELECT ms.id, ms.slot_type
            FROM meal_slots ms
            JOIN meal_plan_days mpd ON mpd.id = ms.meal_plan_day_id
            WHERE mpd.meal_plan_id = ?
            ORDER BY mpd.planned_date, ms.position
        ');
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateMeals(int $planId, string $dietPreference, array $allergies): void
    {
        $stmt = $this->connection->prepare('SELECT weekly_budget FROM meal_plans WHERE id = ?');
        $stmt->execute([$planId]);
        $weeklyBudget = (int) $stmt->fetchColumn();

        $slots    = $this->getSlotsForPlan($planId);
        $numSlots = count($slots);

        $budgetCentsPerSlot = ($weeklyBudget > 0 && $numSlots > 0)
            ? (int) (($weeklyBudget * 100) / $numSlots)
            : 0;

        $this->connection->prepare('
            DELETE FROM meal_slot_recipes WHERE meal_slot_id IN (
                SELECT ms.id FROM meal_slots ms
                JOIN meal_plan_days mpd ON mpd.id = ms.meal_plan_day_id
                WHERE mpd.meal_plan_id = ?
            )
        ')->execute([$planId]);

        foreach ($slots as $slot) {
            $recipe = $this->randomRecipeForSlot($slot['slot_type'], $dietPreference, $allergies, $budgetCentsPerSlot);
            if ($recipe === null && $budgetCentsPerSlot > 0) {
                $recipe = $this->randomRecipeForSlot($slot['slot_type'], $dietPreference, $allergies, 0);
            }
            if ($recipe !== null) {
                $this->addRecipeToSlot((int) $slot['id'], (int) $recipe['id'], 1);
            }
        }
    }

    private function randomRecipeForSlot(
        string $slotType,
        string $dietPreference,
        array  $allergies,
        int    $budgetCentsPerSlot = 0
    ): ?array {
        $categoryMap = [
            'breakfast' => ['breakfast'],
            'lunch'     => ['dinner', 'lunch', 'soup'],
            'dinner'    => ['supper', 'dinner'],
            'snack'     => ['snack', 'dessert'],
        ];

        $categories = $categoryMap[$slotType] ?? [];
        if (empty($categories)) {
            return null;
        }

        $catPlaceholders = implode(',', array_fill(0, count($categories), '?'));
        $params          = $categories;
        $dietJoin        = '';
        $allergyWhere    = '';
        $budgetWhere     = '';

        if ($dietPreference !== '' && $dietPreference !== 'none') {
            $dietJoin = 'JOIN recipe_diet_types rdt ON rdt.recipe_id = r.id
                         JOIN diet_types dt ON dt.id = rdt.diet_type_id AND dt.code = ?';
            $params[] = $dietPreference;
        }

        if (!empty($allergies)) {
            $aPlaceholders = implode(',', array_fill(0, count($allergies), '?'));
            $allergyWhere  = "AND r.id NOT IN (
                SELECT rat.recipe_id FROM recipe_allergy_types rat
                JOIN allergy_types at2 ON at2.id = rat.allergy_type_id
                WHERE at2.code IN ($aPlaceholders)
            )";
            foreach ($allergies as $a) {
                $params[] = $a;
            }
        }

        if ($budgetCentsPerSlot > 0) {
            $budgetWhere = 'AND (
                SELECT COALESCE(SUM(ri.estimated_price_cents), 0)
                FROM recipe_ingredients ri WHERE ri.recipe_id = r.id
            ) BETWEEN 1 AND ?';
            $params[] = $budgetCentsPerSlot;
        }

        $sql = "
            SELECT r.id, r.title
            FROM recipes r
            JOIN recipe_categories rc ON rc.id = r.category_id AND rc.code IN ($catPlaceholders)
            $dietJoin
            WHERE r.status = 'approved' AND r.visibility = 'public'
            $allergyWhere
            $budgetWhere
            ORDER BY RANDOM()
            LIMIT 1
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
