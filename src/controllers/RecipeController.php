<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\MediaRepository;
use App\Repositories\RatingRepository;
use App\Repositories\RecipeRepository;
use App\Repositories\SettingsRepository;
use App\Services\PriceEstimator;

final class RecipeController extends AppController
{
    public function list(): Response
    {
        $userId = $this->sessions->currentUser()?->id();
        $page    = max(1, (int) $this->request->query('page', 1));
        $perPage = 12;

        $dietFilter = (array) $this->request->query('diet', []);
        $filters = [
            'q'          => (string) $this->request->query('q', ''),
            'difficulty' => (string) $this->request->query('difficulty', ''),
            'category'   => (string) $this->request->query('category', ''),
            'time'       => (string) $this->request->query('time', ''),
            'diet'       => $dietFilter,
            'favorites'  => (string) $this->request->query('favorites', ''),
        ];

        $userDietPreference = null;
        if ($userId !== null && empty(array_filter($dietFilter))) {
            $db2   = new Database();
            $prefs = (new SettingsRepository($db2->connection()))->getFoodPreferences($userId);
            if ($prefs && !empty($prefs['diet_type']) && $prefs['diet_type'] !== 'standard') {
                $userDietPreference  = $prefs['diet_type'];
                $filters['diet']     = [$userDietPreference];
            }
        }

        $db     = new Database();
        $repo   = new RecipeRepository($db->connection());
        $result = $repo->listPublic($filters, $userId, $page, $perPage);

        $recipes = array_map(fn(array $row) => [
            'id'                  => (int) $row['id'],
            'title'               => $row['title'],
            'category'            => $row['category_label'],
            'imageUrl'            => $row['image_url'] ?? null,
            'rating'              => $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null,
            'reviewCount'         => (int) $row['rating_count'],
            'cookingTimeMinutes'  => (int) $row['prep_time_minutes'],
            'servings'            => (int) $row['servings'],
            'dietTags'            => $row['diet_tags'],
            'isFavorite'          => (bool) $row['is_favorite'],
        ], $result['rows']);

        $total = $result['total'];
        $pages = max(1, (int) ceil($total / $perPage));

        $filterOptions = $repo->listFilterOptions();
        if ($userDietPreference !== null) {
            $filterOptions['userDietPreference'] = $userDietPreference;
        }

        return Response::json([
            'recipes' => $recipes,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => $pages,
            'filters' => $filterOptions,
        ]);
    }

    public function details(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if ($this->isDelete()) {
            return $this->handleDeleteDraft();
        }

        if ($this->isPut()) {
            return $this->handleUpdateDraft();
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());
        $row  = $repo->findById($recipeId, $userId);

        if ($row === null) {
            return $this->jsonError('Przepis nie istnieje.', 404);
        }

        if ($row['visibility'] !== 'public' && (int) $row['author_user_id'] !== $userId) {
            return $this->jsonError('Brak dostępu do tego przepisu.', 403);
        }

        $related = $repo->findRelated((int) $row['id'], $row['category_id'] ? (int) $row['category_id'] : null);

        return Response::json([
            'id'                 => (int) $row['id'],
            'title'              => $row['title'],
            'description'        => $row['description'],
            'category'           => $row['category_label'],
            'categoryCode'       => $row['category_code'],
            'difficulty'         => $row['difficulty'],
            'prepTimeMinutes'    => (int) $row['prep_time_minutes'],
            'cookTimeMinutes'    => (int) $row['prep_time_minutes'],
            'servings'           => (int) $row['servings'],
            'status'             => $row['status'],
            'videoUrl'           => $row['video_url'] ?? null,
            'author'             => $row['author_name'],
            'authorAvatarUrl'    => $row['author_avatar_url'] ?? null,
            'imageUrl'           => $row['image_url'] ?? null,
            'isFavorite'         => (bool) $row['is_favorite'],
            'averageRating'      => $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null,
            'ratingCount'        => (int) $row['rating_count'],
            'userRating'         => $row['user_rating'] !== null ? (int) $row['user_rating'] : null,
            'dietTags'           => $row['diet_tags'],
            'ingredients'        => $row['ingredients'],
            'steps'              => $row['steps'],
            'nutrition'          => [
                'calories'      => $row['calories'],
                'protein'       => $row['protein_grams'],
                'fat'           => $row['fat_grams'],
                'carbohydrates' => $row['carbohydrates_grams'],
                'fiber'         => $row['fiber_grams'],
            ],
            'related'            => array_map(fn(array $r) => [
                'id'              => (int) $r['id'],
                'title'           => $r['title'],
                'category'        => $r['category_label'],
                'prepTimeMinutes' => (int) $r['prep_time_minutes'],
                'servings'        => (int) $r['servings'],
            ], $related),
        ]);
    }

    public function toggleFavorite(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();

        $db        = new Database();
        $repo      = new RecipeRepository($db->connection());
        $isFavorite = $repo->toggleFavorite($recipeId, $userId);

        return Response::json(['isFavorite' => $isFavorite]);
    }

    public function formOptions(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());

        return Response::json($repo->listFilterOptions());
    }

    public function myRecipes(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new RecipeRepository($db->connection());
        $rows   = $repo->listByAuthor($userId);

        $recipes = array_map(fn(array $row) => [
            'id'           => (int) $row['id'],
            'title'        => $row['title'],
            'category'     => $row['category_label'],
            'status'       => $row['status'],
            'updatedAt'    => $row['updated_at'] ? substr((string) $row['updated_at'], 0, 10) : null,
            'visibility'   => $row['visibility'],
            'submittedAt'  => $row['submitted_at'] ? substr((string) $row['submitted_at'], 0, 10) : null,
            'reviewReason' => $row['review_reason'],
            'url'          => '/recipe-details?id=' . (int) $row['id'],
        ], $rows);

        return Response::json(['recipes' => $recipes]);
    }

    private function handleUpdateDraft(): Response
    {
        $recipeId    = (int) $this->request->routeParam('recipeId');
        $title       = trim((string) $this->request->input('title', ''));
        $description = trim((string) $this->request->input('description', ''));
        $ingredients = $this->request->input('ingredients', []);
        $steps       = $this->request->input('steps', []);

        if (strlen($title) < 3) {
            return $this->jsonError('Tytuł musi mieć co najmniej 3 znaki.');
        }
        if (strlen($description) < 20) {
            return $this->jsonError('Opis musi mieć co najmniej 20 znaków.');
        }
        if (empty($ingredients) || !is_array($ingredients)) {
            return $this->jsonError('Przepis musi mieć co najmniej jeden składnik.');
        }
        if (empty($steps) || !is_array($steps)) {
            return $this->jsonError('Przepis musi mieć co najmniej jeden krok.');
        }

        $rawVideoUrl = $this->request->input('videoUrl');
        $videoUrl    = null;
        if ($rawVideoUrl !== null) {
            $trimmed  = trim((string) $rawVideoUrl);
            $videoUrl = filter_var($trimmed, FILTER_VALIDATE_URL) !== false ? $trimmed : null;
        }

        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new RecipeRepository($db->connection());

        try {
            $found = $repo->updateDraft($recipeId, $userId, [
                'title'           => $title,
                'description'     => $description,
                'categoryCode'    => (string) $this->request->input('categoryCode', ''),
                'difficulty'      => (string) $this->request->input('difficulty', 'easy'),
                'prepTimeMinutes' => (int) $this->request->input('prepTimeMinutes', 30),
                'servings'        => (int) $this->request->input('servings', 2),
                'ingredients'     => $this->normalizeIngredients($ingredients),
                'steps'           => $steps,
                'videoUrl'        => $videoUrl,
            ]);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'forbidden'      => $this->jsonError('Brak dostępu do tego przepisu.', 403),
                'invalid_status' => $this->jsonError('Można edytować tylko szkice i przepisy do poprawy.', 409),
                default          => $this->jsonError('Błąd serwera.', 500),
            };
        }

        if (!$found) {
            return $this->jsonError('Przepis nie istnieje.', 404);
        }

        $mediaRepo = new MediaRepository($db->connection());

        $mediaId = $this->request->input('mediaId');
        if ($mediaId !== null) {
            $mediaId = (int) $mediaId;
            if ($mediaRepo->belongsToUser($mediaId, $userId)) {
                $mediaRepo->addRecipeMainPhoto($recipeId, $mediaId);
            }
        }

        $videoMediaId = $this->request->input('videoMediaId');
        if ($videoMediaId !== null) {
            $videoMediaId = (int) $videoMediaId;
            if ($mediaRepo->belongsToUser($videoMediaId, $userId)) {
                $mediaRepo->addRecipeMainVideo($recipeId, $videoMediaId);
            }
        }

        return Response::json(['recipeId' => $recipeId]);
    }

    private function handleDeleteDraft(): Response
    {
        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();
        $db       = new Database();
        $repo     = new RecipeRepository($db->connection());

        try {
            $found = $repo->deleteDraft($recipeId, $userId);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'forbidden'      => $this->jsonError('Brak dostępu do tego przepisu.', 403),
                'invalid_status' => $this->jsonError('Można usuwać tylko szkice.', 409),
                default          => $this->jsonError('Błąd serwera.', 500),
            };
        }

        if (!$found) {
            return $this->jsonError('Przepis nie istnieje.', 404);
        }

        return Response::json(['deleted' => true]);
    }

    public function createDraft(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $title       = trim((string) $this->request->input('title', ''));
        $description = trim((string) $this->request->input('description', ''));
        $ingredients = $this->request->input('ingredients', []);
        $steps       = $this->request->input('steps', []);

        if (strlen($title) < 3) {
            return $this->jsonError('Tytuł musi mieć co najmniej 3 znaki.');
        }

        if (strlen($description) < 20) {
            return $this->jsonError('Opis musi mieć co najmniej 20 znaków.');
        }

        if (empty($ingredients) || !is_array($ingredients)) {
            return $this->jsonError('Przepis musi mieć co najmniej jeden składnik.');
        }

        if (empty($steps) || !is_array($steps)) {
            return $this->jsonError('Przepis musi mieć co najmniej jeden krok.');
        }

        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new RecipeRepository($db->connection());

        $slug = $this->slugify($title);

        if ($repo->slugExists($slug)) {
            $slug .= '-' . time();
        }

        $rawVideoUrl = $this->request->input('videoUrl');
        $videoUrl    = null;
        if ($rawVideoUrl !== null) {
            $trimmed  = trim((string) $rawVideoUrl);
            $videoUrl = filter_var($trimmed, FILTER_VALIDATE_URL) !== false ? $trimmed : null;
        }

        $recipeId = $repo->createDraft($userId, [
            'title'           => $title,
            'slug'            => $slug,
            'description'     => $description,
            'categoryCode'    => (string) $this->request->input('categoryCode', ''),
            'difficulty'      => (string) $this->request->input('difficulty', 'easy'),
            'prepTimeMinutes' => (int) $this->request->input('prepTimeMinutes', 30),
            'servings'        => (int) $this->request->input('servings', 2),
            'ingredients'     => $this->normalizeIngredients($ingredients),
            'steps'           => $steps,
            'dietTypes'       => (array) $this->request->input('dietTypes', []),
            'tags'            => (array) $this->request->input('tags', []),
            'nutrition'       => $this->request->input('nutrition'),
            'videoUrl'        => $videoUrl,
        ]);

        $mediaRepo = new MediaRepository($db->connection());

        $mediaId = $this->request->input('mediaId');
        if ($mediaId !== null) {
            $mediaId = (int) $mediaId;
            if ($mediaRepo->belongsToUser($mediaId, $userId)) {
                $mediaRepo->addRecipeMainPhoto($recipeId, $mediaId);
            }
        }

        $videoMediaId = $this->request->input('videoMediaId');
        if ($videoMediaId !== null) {
            $videoMediaId = (int) $videoMediaId;
            if ($mediaRepo->belongsToUser($videoMediaId, $userId)) {
                $mediaRepo->addRecipeMainVideo($recipeId, $videoMediaId);
            }
        }

        return Response::json(['recipeId' => $recipeId], 201);
    }

    public function submitForReview(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());

        try {
            $found = $repo->submitForReview($recipeId, $userId);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'forbidden'      => $this->jsonError('Brak dostępu do tego przepisu.', 403),
                'invalid_status' => $this->jsonError('Przepis nie może być wysłany do recenzji w obecnym statusie.', 409),
                default          => $this->jsonError('Błąd serwera.', 500),
            };
        }

        if (!$found) {
            return $this->jsonError('Przepis nie istnieje.', 404);
        }

        return Response::json(['status' => 'submitted']);
    }

    public function rating(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();
        $db       = new Database();
        $repo     = new RatingRepository($db->connection());

        if ($this->isDelete()) {
            $repo->delete($userId, $recipeId);
            return Response::json(['success' => true]);
        }

        if ($this->isPost()) {
            $score   = (int) $this->request->input('score', 0);
            $comment = $this->request->input('comment');

            if ($score < 1 || $score > 5) {
                return $this->jsonError('Ocena musi być w skali 1–5.', 422);
            }

            $repo->upsert($userId, $recipeId, $score, $comment !== null ? (string) $comment : null);
            $stats = $repo->findStatsByRecipe($recipeId);

            return Response::json(['success' => true, 'stats' => $stats]);
        }

        return $this->jsonError('Metoda niedozwolona.', 405);
    }

    private function slugify(string $text): string
    {
        $map  = ['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
                 'Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ó'=>'O','Ś'=>'S','Ź'=>'Z','Ż'=>'Z'];
        $text = strtr($text, $map);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-') ?: 'przepis';
    }

    private function normalizeIngredients(array $ingredients): array
    {
        $estimator = new PriceEstimator();

        return array_map(static function (array $ingredient) use ($estimator): array {
            $name   = trim((string) ($ingredient['name'] ?? ''));
            $amount = trim((string) ($ingredient['amount'] ?? ''));
            $manual = $estimator->parseMoneyToCents($ingredient['estimatedPrice'] ?? null);

            $ingredient['name']                = $name;
            $ingredient['amount']              = $amount;
            $ingredient['estimatedPriceCents'] = $manual ?? $estimator->estimateCents($name, $amount);

            return $ingredient;
        }, $ingredients);
    }
}
