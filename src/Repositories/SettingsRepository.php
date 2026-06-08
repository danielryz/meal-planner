<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SettingsRepository extends AbstractRepository
{
    public function getNotificationPreferences(int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT
                meal_reminders_email,
                grocery_reminders_email,
                recipe_review_app,
                team_activity_app,
                account_security_email,
                TO_CHAR(quiet_hours_start, \'HH24:MI\') AS quiet_hours_start,
                TO_CHAR(quiet_hours_end, \'HH24:MI\') AS quiet_hours_end
            FROM user_notification_preferences
            WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function saveNotificationPreferences(int $userId, array $data): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE user_notification_preferences SET
                meal_reminders_email = :meal_reminders_email,
                grocery_reminders_email = :grocery_reminders_email,
                recipe_review_app = :recipe_review_app,
                team_activity_app = :team_activity_app,
                account_security_email = :account_security_email,
                quiet_hours_start = :quiet_hours_start,
                quiet_hours_end = :quiet_hours_end,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id'
        );
        $stmt->bindValue(':meal_reminders_email', (bool) ($data['mealRemindersEmail'] ?? true), PDO::PARAM_BOOL);
        $stmt->bindValue(':grocery_reminders_email', (bool) ($data['groceryRemindersEmail'] ?? true), PDO::PARAM_BOOL);
        $stmt->bindValue(':recipe_review_app', (bool) ($data['recipeReviewApp'] ?? true), PDO::PARAM_BOOL);
        $stmt->bindValue(':team_activity_app', (bool) ($data['teamActivityApp'] ?? false), PDO::PARAM_BOOL);
        $stmt->bindValue(':account_security_email', (bool) ($data['accountSecurityEmail'] ?? true), PDO::PARAM_BOOL);
        $stmt->bindValue(':quiet_hours_start', $data['quietHoursStart'] ?? '22:00');
        $stmt->bindValue(':quiet_hours_end', $data['quietHoursEnd'] ?? '07:00');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getFoodPreferences(int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT
                ufp.default_servings,
                ufp.meals_per_day,
                ufp.weekly_budget_cents,
                ufp.disliked_ingredients,
                dt.code AS diet_type
            FROM user_food_preferences ufp
            LEFT JOIN diet_types dt ON dt.id = ufp.diet_type_id
            WHERE ufp.user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $stmt2 = $this->connection->prepare(
            'SELECT at.code FROM user_allergy_preferences uap
            JOIN allergy_types at ON at.id = uap.allergy_type_id
            WHERE uap.user_id = :user_id'
        );
        $stmt2->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt2->execute();

        $row['allergies'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        return $row;
    }

    public function saveFoodPreferences(int $userId, array $data): void
    {
        $dietCode = $data['dietType'] ?? null;
        $dietTypeId = null;

        if ($dietCode !== null) {
            $stmt = $this->connection->prepare('SELECT id FROM diet_types WHERE code = :code');
            $stmt->bindValue(':code', $dietCode);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            $dietTypeId = $id !== false ? (int) $id : null;
        }

        $stmt = $this->connection->prepare(
            'UPDATE user_food_preferences SET
                diet_type_id = :diet_type_id,
                default_servings = :default_servings,
                meals_per_day = :meals_per_day,
                weekly_budget_cents = :weekly_budget_cents,
                disliked_ingredients = :disliked_ingredients,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id'
        );
        $stmt->bindValue(':diet_type_id', $dietTypeId, $dietTypeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':default_servings', (int) ($data['defaultServings'] ?? 2), PDO::PARAM_INT);
        $stmt->bindValue(':meals_per_day', (int) ($data['mealsPerDay'] ?? 3), PDO::PARAM_INT);
        $stmt->bindValue(':weekly_budget_cents', isset($data['weeklyBudgetCents']) ? (int) $data['weeklyBudgetCents'] : null,
            isset($data['weeklyBudgetCents']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':disliked_ingredients', $data['dislikedIngredients'] ?? null);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
