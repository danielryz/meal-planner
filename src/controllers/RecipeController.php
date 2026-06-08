<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\RecipeRepository;

final class RecipeController extends AppController
{
    public function list(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId  = $this->sessions->currentUser()->id();
        $filters = [
            'q'          => (string) $this->request->query('q', ''),
            'difficulty' => (string) $this->request->query('difficulty', ''),
            'category'   => (string) $this->request->query('category', ''),
            'time'       => (string) $this->request->query('time', ''),
        ];

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());
        $rows = $repo->listPublic($filters, $userId);

        $recipes = array_map(fn(array $row) => [
            'id'                  => (int) $row['id'],
            'title'               => $row['title'],
            'category'            => $row['category_label'],
            'imageUrl'            => null,
            'rating'              => null,
            'reviewCount'         => 0,
            'cookingTimeMinutes'  => (int) $row['prep_time_minutes'],
            'servings'            => (int) $row['servings'],
            'dietTags'            => $row['diet_tags'],
            'isFavorite'          => (bool) $row['is_favorite'],
        ], $rows);

        return Response::json([
            'recipes' => $recipes,
            'filters' => $repo->listFilterOptions(),
        ]);
    }

    public function details(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
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

        return Response::json([
            'id'                 => (int) $row['id'],
            'title'              => $row['title'],
            'description'        => $row['description'],
            'category'           => $row['category_label'],
            'difficulty'         => $row['difficulty'],
            'prepTimeMinutes'    => (int) $row['prep_time_minutes'],
            'cookTimeMinutes'    => (int) $row['prep_time_minutes'],
            'servings'           => (int) $row['servings'],
            'author'             => $row['author_name'],
            'isFavorite'         => (bool) $row['is_favorite'],
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
}
