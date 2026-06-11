<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;

final class DashboardController extends AppController
{
    public function home(): Response
    {
        return $this->render("index", ["title" => "MealPlanner"]);
    }

    public function support(): Response
    {
        return $this->render("support");
    }

    public function contact(): Response
    {
        return $this->render("contact");
    }

    public function privacy(): Response
    {
        return $this->render("privacy");
    }

    public function terms(): Response
    {
        return $this->render("terms");
    }

    public function dashboard(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId = $this->sessions->currentUser()->id();
        $pdo    = (new Database())->connection();

        $stmt = $pdo->query("SELECT COUNT(*) FROM recipes WHERE visibility = 'public' AND status = 'approved'");
        $recipesCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT name FROM meal_plans WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $activePlanName = $stmt->fetchColumn() ?: null;

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM grocery_items gi
             JOIN grocery_lists gl ON gl.id = gi.grocery_list_id
             WHERE gl.user_id = ? AND gl.status = 'active' AND gi.is_checked = FALSE"
        );
        $stmt->execute([$userId]);
        $groceryItemsCount = (int) $stmt->fetchColumn();

        return $this->render('dashboard', array_merge(
            ['currentRoute' => 'dashboard'],
            $this->currentUserViewData(),
            [
                'recipesCount'      => $recipesCount,
                'activePlanName'    => $activePlanName,
                'groceryItemsCount' => $groceryItemsCount,
            ]
        ));
    }

    public function mealPlanner(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId = $this->sessions->currentUser()->id();
        $pdo    = (new Database())->connection();

        $stmt = $pdo->prepare("SELECT id FROM meal_plans WHERE user_id = ? AND status = 'active' ORDER BY week_start_date DESC LIMIT 1");
        $stmt->execute([$userId]);
        $activePlanId = $stmt->fetchColumn() ?: null;

        return $this->render('meal-planner', array_merge(
            ['currentRoute' => 'meal-planner'],
            $this->currentUserViewData(),
            ['activePlanId' => $activePlanId]
        ));
    }

    public function groceryList(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("grocery-list", "grocery-list");
    }

    public function users(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        return $this->renderAppView("users", "users");
    }

    public function profile(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }


        return $this->renderAppView("profile", "profile");
    }

    public function settings(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("settings", "settings");
    }

    public function notificationSettings(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("notification-settings", "settings");
    }

    public function preferences(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("preferences", "settings");
    }

    public function about(): Response
    {
        return $this->render("about");
    }

    public function recipes(): Response
    {
        return $this->renderAppView("recipes", "recipes");
    }

    public function recipeDetails(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("recipe-details", "recipes");
    }

    public function recipeDetail(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("recipe-details", "recipes");
    }

    public function addRecipe(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("add-recipe", "recipes");
    }

    public function editRecipe(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $recipeId = (int) $this->request->routeParam('recipeId');

        return $this->render('edit-recipe', array_merge(
            ['currentRoute' => 'recipes'],
            $this->currentUserViewData(),
            ['recipeId' => $recipeId]
        ));
    }

    public function recipeReviews(): Response
    {
        if ($response = $this->requireRole('owner', 'employee')) {
            return $response;
        }

        return $this->renderAppView("recipe-reviews", "recipes");
    }

    public function recipeManagement(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("recipe-management", "recipes");
    }

    private function renderAppView(string $template, string $currentRoute): Response
    {
        return $this->render($template, array_merge([
            "currentRoute" => $currentRoute,
        ], $this->currentUserViewData()));
    }
}
