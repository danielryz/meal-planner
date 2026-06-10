<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\GroceryListRepository;
use App\Repositories\MealPlanRepository;

final class GroceryListController extends AppController
{
    public function active(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new GroceryListRepository($db->connection());

        $list       = $repo->findOrCreateActive($userId);
        $categories = $repo->getItemsGroupedByCategory((int) $list['id']);

        $stmt = $db->connection()->prepare(
            "SELECT weekly_budget FROM meal_plans WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $weeklyBudget = (int) ($stmt->fetchColumn() ?: 0);

        return Response::json([
            'listId'     => (int) $list['id'],
            'weekLabel'  => $list['title'],
            'currency'   => 'PLN',
            'budget'     => ['limit' => $weeklyBudget, 'spent' => 0, 'saved' => 0],
            'categories' => $categories,
        ]);
    }

    public function addItem(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $listId = (int) $this->request->routeParam('listId');
        $userId = $this->sessions->currentUser()->id();
        $name   = trim((string) $this->request->input('name', ''));

        if (strlen($name) < 2) {
            return $this->jsonError('Nazwa produktu musi mieć co najmniej 2 znaki.');
        }

        $db   = new Database();
        $repo = new GroceryListRepository($db->connection());

        if (!$repo->listBelongsToUser($listId, $userId)) {
            return $this->jsonError('Brak dostępu.', 403);
        }

        $quantity     = $this->request->input('quantity') !== null
            ? trim((string) $this->request->input('quantity'))
            : null;
        $categoryCode = (string) $this->request->input('categoryCode', 'other');
        $note         = $this->request->input('note') !== null
            ? trim((string) $this->request->input('note'))
            : null;
        $categoryId   = $repo->findCategoryByCode($categoryCode);

        $itemId = $repo->addItem($listId, $name, $quantity ?: null, $categoryId, $note ?: null);

        return Response::json(['itemId' => $itemId], 201);
    }

    public function generate(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $planId = (int) $this->request->input('planId', 0);
        $userId = $this->sessions->currentUser()->id();

        if (!$planId) {
            return $this->jsonError('Podaj ID planu.');
        }

        $db           = new Database();
        $mealPlanRepo = new MealPlanRepository($db->connection());

        if (!$mealPlanRepo->planBelongsToUser($planId, $userId)) {
            return $this->jsonError('Brak dostępu.', 403);
        }

        $repo   = new GroceryListRepository($db->connection());
        $list   = $repo->findOrCreateActive($userId);
        $listId = (int) $list['id'];

        $stmt = $db->connection()->prepare(
            'SELECT ri.name, ri.amount, msr.servings, r.servings AS recipe_servings
             FROM meal_plan_days mpd
             JOIN meal_slots ms          ON ms.meal_plan_day_id = mpd.id
             JOIN meal_slot_recipes msr  ON msr.meal_slot_id = ms.id
             JOIN recipes r              ON r.id = msr.recipe_id
             JOIN recipe_ingredients ri  ON ri.recipe_id = r.id
             WHERE mpd.meal_plan_id = :plan_id
             ORDER BY ri.name, ri.recipe_id, ri.position'
        );
        $stmt->bindValue(':plan_id', $planId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $added = 0;
        foreach ($rows as $row) {
            $ratio  = (float) max(1, (int) $row['servings']) / max(1, (int) $row['recipe_servings']);
            $amount = $this->scaleAmount((string) ($row['amount'] ?? ''), $ratio);
            $repo->addItem($listId, $row['name'], $amount ?: null, null, null);
            $added++;
        }

        return Response::json(['added' => $added]);
    }

    private function scaleAmount(string $amount, float $ratio): string
    {
        if ($ratio === 1.0 || $amount === '') {
            return $amount;
        }
        return (string) preg_replace_callback(
            '/\d+(?:[.,]\d+)?/',
            static function (array $m) use ($ratio): string {
                $n = (float) str_replace(',', '.', $m[0]) * $ratio;
                return $n == floor($n)
                    ? (string) (int) $n
                    : number_format($n, 1, ',', '');
            },
            $amount
        );
    }

    public function itemAction(): Response
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if ($this->isPatch()) {
            return $this->patchItem();
        }

        if ($this->isDelete()) {
            return $this->deleteItem();
        }

        return $this->jsonError('Metoda niedozwolona.', 405);
    }

    private function patchItem(): Response
    {
        $listId = (int) $this->request->routeParam('listId');
        $itemId = (int) $this->request->routeParam('itemId');
        $userId = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new GroceryListRepository($db->connection());

        if (!$repo->listBelongsToUser($listId, $userId)) {
            return $this->jsonError('Brak dostępu.', 403);
        }

        $data = [];

        if ($this->request->input('name') !== null) {
            $data['name'] = trim((string) $this->request->input('name'));
        }

        if ($this->request->input('quantity') !== null) {
            $data['quantity'] = trim((string) $this->request->input('quantity'));
        }

        if ($this->request->input('note') !== null) {
            $data['note'] = trim((string) $this->request->input('note'));
        }

        if ($this->request->input('isChecked') !== null) {
            $data['isChecked'] = (bool) $this->request->input('isChecked');
        }

        if (empty($data)) {
            return $this->jsonError('Brak danych do aktualizacji.');
        }

        $updated = $repo->updateItem($listId, $itemId, $data);

        if (!$updated) {
            return $this->jsonError('Element nie istnieje.', 404);
        }

        return Response::json(['updated' => true]);
    }

    private function deleteItem(): Response
    {
        $listId = (int) $this->request->routeParam('listId');
        $itemId = (int) $this->request->routeParam('itemId');
        $userId = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new GroceryListRepository($db->connection());

        if (!$repo->listBelongsToUser($listId, $userId)) {
            return $this->jsonError('Brak dostępu.', 403);
        }

        $deleted = $repo->deleteItem($listId, $itemId);

        if (!$deleted) {
            return $this->jsonError('Element nie istnieje.', 404);
        }

        return Response::json(['deleted' => true]);
    }
}
