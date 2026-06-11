<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\GroceryListRepository;
use App\Repositories\RecipeRepository;
use App\Services\AiService;

final class AiController extends AppController
{
    private const SYSTEM_PROMPT = 'Jesteś pomocnym asystentem kulinarnym aplikacji MealPlanner. '
        . 'Pomagasz użytkownikom planować posiłki i diety, znajdować przepisy, '
        . 'rozumieć wartości odżywcze, tworzyć listy zakupów i odpowiadać na pytania kulinarne. '
        . 'Odpowiadaj po polsku, zwięźle i przyjaźnie. Jeśli pytanie nie dotyczy gotowania, '
        . 'jedzenia lub planowania posiłków, grzecznie przekieruj rozmowę na tematy kulinarne. '
        . 'Gdy użytkownik prosi o dodanie produktów do listy zakupów lub znalezienie przepisu, '
        . 'korzystaj z dostępnych narzędzi, a następnie potwierdź wykonanie akcji.';

    private const MAX_HISTORY        = 20;
    private const MAX_TOOL_ITERATIONS = 5;

    private const TOOLS = [
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'search_recipes',
                'description' => 'Wyszukuje przepisy w bazie danych MealPlanner. Użyj gdy użytkownik pyta o przepisy lub prosi o znalezienie czegoś do gotowania.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query'      => ['type' => 'string',  'description' => 'Słowo kluczowe do wyszukania w nazwie przepisu'],
                        'difficulty' => ['type' => 'string',  'enum' => ['easy', 'medium', 'hard'], 'description' => 'Poziom trudności'],
                        'max_time'   => ['type' => 'integer', 'description' => 'Maksymalny czas przygotowania w minutach'],
                    ],
                ],
            ],
        ],
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'add_to_grocery_list',
                'description' => 'Dodaje produkt do aktywnej listy zakupów użytkownika.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'name'     => ['type' => 'string', 'description' => 'Nazwa produktu do kupienia'],
                        'quantity' => ['type' => 'string', 'description' => 'Ilość np. "500g", "2 sztuki", "1 opakowanie"'],
                    ],
                    'required'   => ['name'],
                ],
            ],
        ],
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_grocery_list',
                'description' => 'Pobiera aktualną listę zakupów użytkownika.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
        ],
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'create_recipe_draft',
                'description' => 'Tworzy nowy szkic przepisu w aplikacji. Użyj gdy użytkownik chce zapisać lub stworzyć nowy przepis.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'title'             => ['type' => 'string',  'description' => 'Tytuł przepisu'],
                        'description'       => ['type' => 'string',  'description' => 'Krótki opis przepisu'],
                        'difficulty'        => ['type' => 'string',  'enum' => ['easy', 'medium', 'hard']],
                        'prep_time_minutes' => ['type' => 'integer', 'description' => 'Czas przygotowania w minutach'],
                        'servings'          => ['type' => 'integer', 'description' => 'Liczba porcji'],
                    ],
                    'required'   => ['title'],
                ],
            ],
        ],
    ];

    private ?Database $toolDb = null;

    public function chat(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $messages = $this->request->input('messages', []);

        if (empty($messages) || !is_array($messages)) {
            return $this->jsonError('Pole messages jest wymagane.', 400);
        }

        foreach ($messages as $msg) {
            if (!isset($msg['role'], $msg['content']) || !in_array($msg['role'], ['user', 'assistant'], true)) {
                return $this->jsonError('Nieprawidłowa struktura wiadomości.', 400);
            }
            if (strlen((string) $msg['content']) > 4000) {
                return $this->jsonError('Wiadomość jest zbyt długa.', 400);
            }
        }

        $userId  = $this->sessions->currentUser()->id();
        $history = array_slice($messages, -self::MAX_HISTORY);

        $payload = array_merge(
            [['role' => 'system', 'content' => self::SYSTEM_PROMPT]],
            $history
        );

        try {
            $reply = $this->runAgentLoop($payload, $userId);
            return Response::json(['reply' => $reply]);
        } catch (\RuntimeException) {
            return $this->jsonError('Asystent AI jest chwilowo niedostępny. Sprawdź czy Ollama jest uruchomiona.', 503);
        }
    }

    private function runAgentLoop(array $messages, int $userId): string
    {
        $ai = new AiService();

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $message = $ai->sendMessage($messages, self::TOOLS);

            if (empty($message['tool_calls'])) {
                $this->toolDb = null;
                return (string) ($message['content'] ?? '');
            }

            $messages[] = $message;

            foreach ($message['tool_calls'] as $toolCall) {
                $name = $toolCall['function']['name'] ?? '';
                $args = $toolCall['function']['arguments'] ?? [];
                if (is_string($args)) {
                    $args = json_decode($args, true) ?? [];
                }

                $messages[] = [
                    'role'    => 'tool',
                    'content' => $this->executeTool($name, (array) $args, $userId),
                ];
            }
        }

        $this->toolDb = null;
        $message      = $ai->sendMessage($messages);
        return (string) ($message['content'] ?? '');
    }

    private function executeTool(string $name, array $args, int $userId): string
    {
        try {
            return match ($name) {
                'search_recipes'      => $this->toolSearchRecipes($args),
                'add_to_grocery_list' => $this->toolAddToGroceryList($args, $userId),
                'get_grocery_list'    => $this->toolGetGroceryList($userId),
                'create_recipe_draft' => $this->toolCreateRecipeDraft($args, $userId),
                default               => "Nieznane narzędzie: {$name}.",
            };
        } catch (\Throwable $e) {
            return "Błąd podczas wykonania narzędzia {$name}: " . $e->getMessage();
        }
    }

    private function getDb(): Database
    {
        return $this->toolDb ??= new Database();
    }

    private function toolSearchRecipes(array $args): string
    {
        $repo    = new RecipeRepository($this->getDb()->connection());
        $filters = [];

        if (!empty($args['query'])) {
            $filters['q'] = (string) $args['query'];
        }
        if (!empty($args['difficulty'])) {
            $filters['difficulty'] = (string) $args['difficulty'];
        }
        if (!empty($args['max_time'])) {
            $filters['time'] = (int) $args['max_time'];
        }

        $result = $repo->listPublic($filters, null, 1, 6);

        if (empty($result['rows'])) {
            return 'Nie znaleziono przepisów pasujących do podanych kryteriów.';
        }

        $total = (int) $result['total'];
        $lines = ["Znaleziono {$total} przepisów. Przykładowe wyniki:"];

        $diffLabels = ['easy' => 'łatwy', 'medium' => 'średni', 'hard' => 'trudny'];
        foreach ($result['rows'] as $row) {
            $diff    = $diffLabels[$row['difficulty']] ?? $row['difficulty'];
            $lines[] = "- {$row['title']} (trudność: {$diff}, czas: {$row['prep_time_minutes']} min, porcje: {$row['servings']})";
        }

        return implode("\n", $lines);
    }

    private function toolAddToGroceryList(array $args, int $userId): string
    {
        if (empty($args['name'])) {
            return 'Błąd: brak nazwy produktu.';
        }

        $repo     = new GroceryListRepository($this->getDb()->connection());
        $list     = $repo->findOrCreateActive($userId);
        $name     = trim((string) $args['name']);
        $quantity = !empty($args['quantity']) ? trim((string) $args['quantity']) : null;

        $repo->addItem((int) $list['id'], $name, $quantity, null, null);

        $qtyStr = $quantity ? " ({$quantity})" : '';
        return "Dodano \"{$name}\"{$qtyStr} do listy zakupów \"{$list['title']}\".";
    }

    private function toolGetGroceryList(int $userId): string
    {
        $repo       = new GroceryListRepository($this->getDb()->connection());
        $list       = $repo->findOrCreateActive($userId);
        $categories = $repo->getItemsGroupedByCategory((int) $list['id']);

        if (empty($categories)) {
            return "Lista zakupów \"{$list['title']}\" jest pusta.";
        }

        $lines = ["Lista zakupów \"{$list['title']}\":"];
        foreach ($categories as $cat) {
            $lines[] = "\n{$cat['label']}:";
            foreach ($cat['items'] as $item) {
                $qty     = !empty($item['quantity']) ? " — {$item['quantity']}" : '';
                $done    = $item['isBought'] ? ' [kupione]' : '';
                $lines[] = "  • {$item['name']}{$qty}{$done}";
            }
        }

        return implode("\n", $lines);
    }

    private function toolCreateRecipeDraft(array $args, int $userId): string
    {
        if (empty($args['title'])) {
            return 'Błąd: tytuł przepisu jest wymagany.';
        }

        $repo  = new RecipeRepository($this->getDb()->connection());
        $title = trim((string) $args['title']);

        $map  = ['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
                 'Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ó'=>'O','Ś'=>'S','Ź'=>'Z','Ż'=>'Z'];
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($title, $map))) ?? '', '-') ?: 'przepis';

        if ($repo->slugExists($slug)) {
            $slug .= '-' . time();
        }

        $diff = in_array($args['difficulty'] ?? '', ['easy', 'medium', 'hard'], true)
            ? (string) $args['difficulty']
            : 'easy';

        $recipeId = $repo->createDraft($userId, [
            'title'           => $title,
            'slug'            => $slug,
            'description'     => trim((string) ($args['description'] ?? '')),
            'difficulty'      => $diff,
            'prepTimeMinutes' => max(1, (int) ($args['prep_time_minutes'] ?? 30)),
            'servings'        => max(1, (int) ($args['servings'] ?? 2)),
        ]);

        return "Utworzono szkic przepisu \"{$title}\" (ID: {$recipeId}). Możesz go edytować pod adresem /edit-recipe/{$recipeId}.";
    }
}
