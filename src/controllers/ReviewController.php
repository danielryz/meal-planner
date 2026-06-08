<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\RecipeRepository;

final class ReviewController extends AppController
{
    public function queue(): Response
    {
        if ($response = $this->requireRole('owner', 'employee')) {
            return $response;
        }

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());
        $rows = $repo->getReviewQueue();

        $reviews = array_map(fn(array $row) => [
            'id'             => (int) $row['id'],
            'title'          => $row['title'],
            'author'         => $row['author_name'],
            'authorEmail'    => $row['author_email'],
            'submittedAt'    => $row['submitted_at'] ? substr((string) $row['submitted_at'], 0, 10) : null,
            'summary'        => $row['description'],
            'status'         => 'pending',
            'category'       => $row['category_label'],
            'prepTimeMinutes'=> (int) $row['prep_time_minutes'],
            'servings'       => (int) $row['servings'],
            'difficulty'     => $row['difficulty'],
            'ingredients'    => $row['ingredients'],
            'reviewNote'     => (string) ($row['review_note'] ?? ''),
        ], $rows);

        return Response::json(['reviews' => $reviews]);
    }

    public function approve(): Response
    {
        if ($response = $this->requireRole('owner', 'employee')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        return $this->handleReviewAction('approve');
    }

    public function requestChanges(): Response
    {
        if ($response = $this->requireRole('owner', 'employee')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        return $this->handleReviewAction('changes_requested');
    }

    public function reject(): Response
    {
        if ($response = $this->requireRole('owner', 'employee')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        return $this->handleReviewAction('rejected');
    }

    private function handleReviewAction(string $action): Response
    {
        $recipeId   = (int) $this->request->routeParam('reviewId');
        $reason     = trim((string) $this->request->input('reason', ''));
        $reviewerId = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());

        try {
            match ($action) {
                'approve'           => $repo->approveRecipe($recipeId, $reviewerId),
                'changes_requested' => $repo->requestChanges($recipeId, $reviewerId, $reason),
                'rejected'          => $repo->rejectRecipe($recipeId, $reviewerId, $reason),
            };
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'not_found'      => $this->jsonError('Przepis nie istnieje.', 404),
                'invalid_status' => $this->jsonError('Przepis nie jest w kolejce recenzji.', 409),
                default          => $this->jsonError('Błąd serwera.', 500),
            };
        }

        return Response::json(['status' => $action === 'approve' ? 'approved' : $action]);
    }
}
