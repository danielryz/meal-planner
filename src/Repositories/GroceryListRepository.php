<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GroceryListRepository
{
    private const CATEGORY_ICONS = [
        'vegetables' => 'pantry.svg',
        'fruit'      => 'pantry.svg',
        'meat_fish'  => 'grocery.svg',
        'dairy'      => 'dairy.svg',
        'grains'     => 'cart.svg',
        'spices'     => 'pantry.svg',
        'other'      => 'cart.svg',
    ];

    private const POLISH_MONTHS = [
        1  => 'stycznia',  2 => 'lutego',   3 => 'marca',
        4  => 'kwietnia',  5 => 'maja',     6 => 'czerwca',
        7  => 'lipca',     8 => 'sierpnia', 9 => 'września',
        10 => 'października', 11 => 'listopada', 12 => 'grudnia',
    ];

    public function __construct(private readonly PDO $connection) {}

    public function findOrCreateActive(int $userId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT id, title, status
             FROM grocery_lists
             WHERE user_id = ? AND status = 'active'
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $list = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($list) {
            return $list;
        }

        $title = $this->currentWeekTitle();
        $stmt  = $this->connection->prepare(
            "INSERT INTO grocery_lists (user_id, title, status) VALUES (?, ?, 'active') RETURNING id"
        );
        $stmt->execute([$userId, $title]);
        $listId = (int) $stmt->fetchColumn();

        return ['id' => $listId, 'title' => $title, 'status' => 'active'];
    }

    public function getItemsGroupedByCategory(int $listId): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                gi.id,
                gi.name,
                gi.quantity,
                gi.note,
                gi.is_checked,
                gi.position,
                gic.code       AS category_code,
                gic.label      AS category_label,
                gic.sort_order AS category_sort_order
            FROM grocery_items gi
            LEFT JOIN grocery_item_categories gic ON gic.id = gi.category_id
            WHERE gi.grocery_list_id = ?
            ORDER BY COALESCE(gic.sort_order, 999), gi.position
        ');
        $stmt->execute([$listId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];

        foreach ($rows as $row) {
            $code  = $row['category_code']  ?? 'other';
            $label = $row['category_label'] ?? 'Inne';
            $sort  = $row['category_sort_order'] ?? 999;

            if (!isset($categories[$code])) {
                $categories[$code] = [
                    'id'    => $code,
                    'label' => $label,
                    'icon'  => self::CATEGORY_ICONS[$code] ?? 'grocery.svg',
                    'items' => [],
                ];
            }

            $categories[$code]['items'][] = [
                'id'             => (int) $row['id'],
                'name'           => $row['name'],
                'quantity'       => (string) ($row['quantity'] ?? ''),
                'estimatedPrice' => 0,
                'isBought'       => (bool) $row['is_checked'],
                'alternative'    => (string) ($row['note'] ?? ''),
            ];
        }

        return array_values($categories);
    }

    public function addItem(int $listId, string $name, ?string $quantity, ?int $categoryId, ?string $note): int
    {
        if ($quantity !== null) {
            $parsed = $this->parseQuantity($quantity);
            if ($parsed !== null) {
                $existing = $this->findDuplicate($listId, $name, $parsed['unit']);
                if ($existing !== null) {
                    $existingParsed = $this->parseQuantity($existing['quantity'] ?? '');
                    if ($existingParsed !== null) {
                        $newValue  = $existingParsed['value'] + $parsed['value'];
                        $formatted = $this->formatQuantity($newValue, $parsed['unit']);
                        $stmt = $this->connection->prepare(
                            'UPDATE grocery_items SET quantity = ?, updated_at = NOW() WHERE id = ? AND grocery_list_id = ?'
                        );
                        $stmt->execute([$formatted, $existing['id'], $listId]);
                        return (int) $existing['id'];
                    }
                }
            }
        }

        $stmt = $this->connection->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM grocery_items WHERE grocery_list_id = ?'
        );
        $stmt->execute([$listId]);
        $position = (int) $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            'INSERT INTO grocery_items (grocery_list_id, name, quantity, category_id, note, position)
             VALUES (?, ?, ?, ?, ?, ?) RETURNING id'
        );
        $stmt->execute([$listId, $name, $quantity, $categoryId, $note, $position]);

        return (int) $stmt->fetchColumn();
    }

    private function parseQuantity(string $quantity): ?array
    {
        if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*(.*)$/u', trim($quantity), $m)) {
            return null;
        }
        return [
            'value' => (float) str_replace(',', '.', $m[1]),
            'unit'  => mb_strtolower(trim($m[2])),
        ];
    }

    private function findDuplicate(int $listId, string $name, string $unit): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, quantity FROM grocery_items WHERE grocery_list_id = ? AND LOWER(name) = LOWER(?)'
        );
        $stmt->execute([$listId, $name]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $p = $this->parseQuantity($row['quantity'] ?? '');
            if ($p !== null && $p['unit'] === mb_strtolower($unit)) {
                return $row;
            }
        }
        return null;
    }

    private function formatQuantity(float $value, string $unit): string
    {
        $formatted = ($value == floor($value))
            ? (string) (int) $value
            : number_format($value, 1, ',', '');
        return $unit !== '' ? "{$formatted} {$unit}" : $formatted;
    }

    public function updateItem(int $listId, int $itemId, array $data): bool
    {
        $sets   = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[]   = 'name = ?';
            $params[] = (string) $data['name'];
        }

        if (array_key_exists('quantity', $data)) {
            $sets[]   = 'quantity = ?';
            $params[] = (string) $data['quantity'];
        }

        if (array_key_exists('note', $data)) {
            $sets[]   = 'note = ?';
            $params[] = (string) $data['note'];
        }

        if (array_key_exists('isChecked', $data)) {
            $sets[]   = 'is_checked = ?';
            $params[] = (bool) $data['isChecked'];
        }

        if (empty($sets)) {
            return false;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $itemId;
        $params[] = $listId;

        $stmt = $this->connection->prepare(
            'UPDATE grocery_items SET ' . implode(', ', $sets) . ' WHERE id = ? AND grocery_list_id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function deleteItem(int $listId, int $itemId): bool
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM grocery_items WHERE id = ? AND grocery_list_id = ?'
        );
        $stmt->execute([$itemId, $listId]);

        return $stmt->rowCount() > 0;
    }

    public function listBelongsToUser(int $listId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1 FROM grocery_lists WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$listId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    public function findCategoryByCode(string $code): ?int
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM grocery_item_categories WHERE code = ?'
        );
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function currentWeekTitle(): string
    {
        $today  = new \DateTime();
        $dow    = (int) $today->format('N');
        $monday = (clone $today)->modify('-' . ($dow - 1) . ' days');
        $sunday = (clone $monday)->modify('+6 days');

        $start = $monday->format('j');
        $end   = $sunday->format('j');
        $month = self::POLISH_MONTHS[(int) $sunday->format('n')];

        return "Zakupy na tydzień {$start}-{$end} {$month}";
    }
}
