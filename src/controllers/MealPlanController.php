<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\MealPlanRepository;

final class MealPlanController extends AppController
{
    public function index(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        return $this->isPost() ? $this->create() : $this->list();
    }

    private function list(): Response
    {
        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new MealPlanRepository($db->connection());
        $rows   = $repo->listByUser($userId);

        $plans = array_map(fn(array $row) => [
            'id'            => (int) $row['id'],
            'name'          => $row['name'],
            'weekStartDate' => $row['week_start_date'],
            'status'        => $row['status'],
        ], $rows);

        return Response::json(['plans' => $plans]);
    }

    private function create(): Response
    {
        $planningDays  = (array) $this->request->input('planningDays', []);
        $mealTypes     = (array) $this->request->input('mealTypes', []);
        $weekStartDate = trim((string) $this->request->input('weekStartDate', ''));
        $weeklyBudget  = max(0, (int) $this->request->input('weeklyBudget', 0));

        if (empty($planningDays)) {
            return $this->jsonError('Wybierz co najmniej jeden dzień planowania.');
        }

        if (empty($mealTypes)) {
            return $this->jsonError('Wybierz co najmniej jeden typ posiłku.');
        }

        if (!$weekStartDate) {
            $weekStartDate = $this->currentWeekMonday();
        }

        if (!$this->isMonday($weekStartDate)) {
            return $this->jsonError('Data tygodnia musi być poniedziałkiem.');
        }

        $userId = $this->sessions->currentUser()->id();
        $name   = 'Tydzień od ' . (new \DateTime($weekStartDate))->format('j.m.Y');

        $db   = new Database();
        $repo = new MealPlanRepository($db->connection());

        try {
            $planId = $repo->create($userId, [
                'name'          => $name,
                'weekStartDate' => $weekStartDate,
                'planningDays'  => $planningDays,
                'mealTypes'     => $mealTypes,
                'weeklyBudget'  => $weeklyBudget,
            ]);
        } catch (\Exception $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') ||
                str_contains(strtolower($e->getMessage()), 'duplicate')) {
                return $this->jsonError('Plan na ten tydzień już istnieje.', 409);
            }

            return $this->jsonError('Błąd serwera.', 500);
        }

        return Response::json(['planId' => $planId, 'name' => $name], 201);
    }

    public function details(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        $planId = (int) $this->request->routeParam('planId');
        $userId = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new MealPlanRepository($db->connection());
        $plan = $repo->findByIdForUser($planId, $userId);

        if (!$plan) {
            return $this->jsonError('Plan nie istnieje.', 404);
        }

        return Response::json([
            'id'            => (int) $plan['id'],
            'name'          => $plan['name'],
            'weekStartDate' => $plan['week_start_date'],
            'status'        => $plan['status'],
            'days'          => $plan['days'],
        ]);
    }

    public function addRecipe(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $planId   = (int) $this->request->routeParam('planId');
        $slotId   = (int) $this->request->routeParam('slotId');
        $recipeId = (int) $this->request->input('recipeId', 0);
        $servings = max(1, (int) $this->request->input('servings', 1));
        $userId   = $this->sessions->currentUser()->id();

        if (!$recipeId) {
            return $this->jsonError('Podaj ID przepisu.');
        }

        $db   = new Database();
        $repo = new MealPlanRepository($db->connection());

        if (!$repo->planBelongsToUser($planId, $userId)) {
            return $this->jsonError('Brak dostępu.', 403);
        }

        if (!$repo->slotBelongsToPlan($slotId, $planId)) {
            return $this->jsonError('Slot nie istnieje w tym planie.', 404);
        }

        $repo->addRecipeToSlot($slotId, $recipeId, $servings);

        return Response::json(['added' => true]);
    }

    public function removeRecipe(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if (!$this->isDelete()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $planId   = (int) $this->request->routeParam('planId');
        $slotId   = (int) $this->request->routeParam('slotId');
        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new MealPlanRepository($db->connection());

        if (!$repo->planBelongsToUser($planId, $userId)) {
            return $this->jsonError('Brak dostępu.', 403);
        }

        $removed = $repo->removeRecipeFromSlot($slotId, $recipeId);

        if (!$removed) {
            return $this->jsonError('Przepis nie jest przypisany do tego slotu.', 404);
        }

        return Response::json(['removed' => true]);
    }

    private function currentWeekMonday(): string
    {
        $today = new \DateTime();
        $dow   = (int) $today->format('N');
        $today->modify('-' . ($dow - 1) . ' days');

        return $today->format('Y-m-d');
    }

    private function isMonday(string $date): bool
    {
        try {
            return (new \DateTime($date))->format('N') === '1';
        } catch (\Exception) {
            return false;
        }
    }
}
