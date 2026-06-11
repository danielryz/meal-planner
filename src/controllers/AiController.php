<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\GroceryListRepository;
use App\Repositories\RecipeRepository;
use App\Services\AiService;
use App\Services\PriceEstimator;

final class AiController extends AppController
{
    private const MAX_HISTORY         = 20;
    private const MAX_TOOL_ITERATIONS = 5;

    private const BASE_PROMPT = 'Jesteś asystentem AI w aplikacji MealPlanner. Rozmawiasz wyłącznie po polsku. '
        . 'Pomagasz planować posiłki, wybierać przepisy, układać listę zakupów i wyjaśniać proste kwestie kulinarne. '
        . 'Nie używaj angielskich słów, jeśli użytkownik pisze po polsku. Nie wymyślaj funkcji aplikacji, których nie znasz. '
        . 'Jeśli użytkownik pisze luźno, odpowiedz luźno i krótko. Jeśli prosi o akcję w aplikacji, użyj dostępnego narzędzia. '
        . 'Gdy użytkownik wymienia kilka produktów do kupienia, wywołaj add_to_grocery_list osobno dla każdego produktu — możesz wywołać to narzędzie wielokrotnie w jednej odpowiedzi. '
        . 'Gdy użytkownik chce stworzyć przepis, dopytaj o brakujące szczegóły (składniki, kroki, czas), a następnie wywołaj create_recipe_draft z zebranymi danymi.';

    private const STYLE_PROMPT = 'Styl rozmowy: odpowiadaj naturalnie po polsku, krótko i konkretnie. '
        . 'Nie używaj dziwnych zwrotów typu "Dobry tydzień". Na luźne powitanie typu "siema", "hej", "cześć" odpowiedz swobodnie, np. "Siema! Mogę pomóc z przepisem, posiłkiem albo listą zakupów." '
        . 'Gdy użytkownik pyta ogólnie "co proponujesz" albo "co polecasz", zaproponuj 2-3 konkretne posiłki i dopytaj o czas, składniki albo dietę. '
        . 'Przykład: użytkownik pisze "co proponujesz", odpowiadasz: "Na dziś proponuję: makaron z warzywami, sałatkę z tuńczykiem albo tofu z ryżem i brokułem. Masz ochotę na coś szybkiego, lekkiego czy bardziej sycącego?". '
        . 'Nie twórz dziwnych nazw potraw. Używaj prostych, codziennych nazw dań. '
        . 'Nie pokazuj użytkownikowi JSON, nazw funkcji, parametrów ani technicznych danych. '
        . 'Narzędzia traktuj jako wewnętrzne akcje aplikacji: po ich użyciu podsumuj wynik zwykłym tekstem.';

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
                        'difficulty' => ['type' => 'string',  'enum' => ['easy', 'medium', 'advanced'], 'description' => 'Poziom trudności'],
                        'max_time'   => ['type' => 'integer', 'description' => 'Maksymalny czas przygotowania w minutach'],
                    ],
                ],
            ],
        ],
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'add_to_grocery_list',
                'description' => 'Dodaje jeden produkt do aktywnej listy zakupów użytkownika. Przy wielu produktach wywołaj tę funkcję raz dla każdego z nich.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'name'      => ['type' => 'string', 'description' => 'Nazwa produktu, np. "ziemniaki", "makaron Lubella"'],
                        'quantity'  => ['type' => 'string', 'description' => 'Ilość np. "500 g", "2 sztuki", "1 opakowanie 500g"'],
                        'price_pln' => ['type' => 'number', 'description' => 'Cena w PLN. Jeśli użytkownik podał cenę — użyj jej. W przeciwnym razie oszacuj sam na podstawie polskich cen rynkowych (np. ziemniaki 1 kg ≈ 3,50 zł, makaron 500g ≈ 4,00 zł, mleko 1l ≈ 4,20 zł).'],
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
                    'type' => 'object',
                ],
            ],
        ],
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'create_recipe_draft',
                'description' => 'Tworzy nowy szkic przepisu w aplikacji. Użyj gdy użytkownik chce zapisać lub stworzyć nowy przepis. Jeśli brakuje szczegółów, najpierw dopytaj użytkownika.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'title'             => ['type' => 'string',  'description' => 'Tytuł przepisu'],
                        'description'       => ['type' => 'string',  'description' => 'Krótki opis przepisu'],
                        'difficulty'        => ['type' => 'string',  'enum' => ['easy', 'medium', 'advanced']],
                        'prep_time_minutes' => ['type' => 'integer', 'description' => 'Czas przygotowania w minutach'],
                        'servings'          => ['type' => 'integer', 'description' => 'Liczba porcji'],
                        'ingredients'       => [
                            'type'        => 'array',
                            'description' => 'Lista składników. Dla każdego podaj szacunkową cenę w PLN na podstawie polskich cen rynkowych.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'name'      => ['type' => 'string', 'description' => 'Nazwa składnika'],
                                    'amount'    => ['type' => 'string', 'description' => 'Ilość, np. "500 g", "2 sztuki"'],
                                    'price_pln' => ['type' => 'number', 'description' => 'Szacunkowa cena w PLN za podaną ilość'],
                                ],
                                'required' => ['name', 'amount'],
                            ],
                        ],
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
            [
                ['role' => 'system', 'content' => self::BASE_PROMPT],
                ['role' => 'system', 'content' => self::STYLE_PROMPT],
            ],
            $history
        );

        try {
            $reply = $this->runAgentLoop($payload, $userId);
            return Response::json(['reply' => $reply]);
        } catch (\RuntimeException) {
            return $this->jsonError('Asystent AI jest chwilowo niedostępny. Sprawdź czy Ollama jest uruchomiona.', 503);
        }
    }

    public function warmup(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        try {
            (new AiService())->chat([['role' => 'user', 'content' => 'ok']]);
            return Response::json(['ok' => true]);
        } catch (\RuntimeException) {
            return Response::json(['ok' => false], 503);
        }
    }

    private function runAgentLoop(array $messages, int $userId): string
    {
        $ai = new AiService();

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $message = $ai->sendMessage($messages, self::TOOLS);

            if (empty($message['tool_calls'])) {
                $this->toolDb = null;
                return $this->cleanReply((string) ($message['content'] ?? ''));
            }

            $messages[] = $this->normalizeAssistantToolCalls($message);

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
        return $this->cleanReply((string) ($message['content'] ?? ''));
    }

    private function lastUserMessage(array $history): string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'user') {
                return (string) ($history[$i]['content'] ?? '');
            }
        }

        return '';
    }

    private function cleanReply(string $reply): string
    {
        $trimmed = trim(html_entity_decode($reply, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($trimmed === '') {
            return 'Napisz, czy chcesz przepis, pomysł na posiłek albo pomoc z listą zakupów.';
        }

        if ($this->looksLikeToolJson($trimmed)) {
            return 'Nie będę pokazywać technicznych danych. Napisz konkretnie, czy mam znaleźć przepis, pokazać listę zakupów albo dodać produkt.';
        }

        return $trimmed;
    }

    private function looksLikeToolJson(string $text): bool
    {
        if (!str_starts_with($text, '{') || !str_ends_with($text, '}')) {
            return false;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded)
            && isset($decoded['name'])
            && is_string($decoded['name']);
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

    private function normalizeAssistantToolCalls(array $message): array
    {
        if (empty($message['tool_calls']) || !is_array($message['tool_calls'])) {
            return $message;
        }

        foreach ($message['tool_calls'] as &$toolCall) {
            $args = $toolCall['function']['arguments'] ?? null;
            if ($args === []) {
                $toolCall['function']['arguments'] = new \stdClass();
            }
        }
        unset($toolCall);

        return $message;
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

        $diffLabels = ['easy' => 'łatwy', 'medium' => 'średni', 'advanced' => 'zaawansowany'];
        foreach ($result['rows'] as $row) {
            $diff    = $diffLabels[$row['difficulty']] ?? $row['difficulty'];
            $lines[] = "- {$row['title']} - /recipe/{$row['id']} (trudność: {$diff}, czas: {$row['prep_time_minutes']} min, porcje: {$row['servings']})";
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

        $estimator  = new PriceEstimator();
        $priceCents = isset($args['price_pln'])
            ? max(0, (int) round((float) $args['price_pln'] * 100))
            : $estimator->estimateCents($name, $quantity);

        $repo->addItem((int) $list['id'], $name, $quantity, null, null, $priceCents);

        $qtyStr   = $quantity ? " ({$quantity})" : '';
        $priceStr = number_format($priceCents / 100, 2, ',', ' ');

        return "Dodano \"{$name}\"{$qtyStr} do listy zakupów. Szacowana cena: {$priceStr} zł.";
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

        $diff = in_array($args['difficulty'] ?? '', ['easy', 'medium', 'advanced'], true)
            ? (string) $args['difficulty']
            : 'easy';

        $estimator   = new PriceEstimator();
        $ingredients = [];
        foreach ((array) ($args['ingredients'] ?? []) as $item) {
            $name   = trim((string) ($item['name'] ?? ''));
            $amount = trim((string) ($item['amount'] ?? ''));
            if ($name === '' || $amount === '') {
                continue;
            }
            $priceCents = isset($item['price_pln'])
                ? max(0, (int) round((float) $item['price_pln'] * 100))
                : $estimator->estimateCents($name, $amount);
            $ingredients[] = ['name' => $name, 'amount' => $amount, 'estimatedPriceCents' => $priceCents];
        }

        $recipeId = $repo->createDraft($userId, [
            'title'           => $title,
            'slug'            => $slug,
            'description'     => trim((string) ($args['description'] ?? '')),
            'difficulty'      => $diff,
            'prepTimeMinutes' => max(1, (int) ($args['prep_time_minutes'] ?? 30)),
            'servings'        => max(1, (int) ($args['servings'] ?? 2)),
            'ingredients'     => $ingredients,
        ]);

        return "Utworzono szkic przepisu \"{$title}\" (ID: {$recipeId}). Możesz go edytować pod adresem /edit-recipe/{$recipeId}.";
    }
}
