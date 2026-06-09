<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\GroceryListRepository;

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

        return Response::json([
            'listId'     => (int) $list['id'],
            'weekLabel'  => $list['title'],
            'currency'   => 'PLN',
            'budget'     => ['limit' => 0, 'spent' => 0, 'saved' => 0],
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
