<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;

final class DashboardController extends AppController
{
    public function home(): Response
    {
        return $this->render("index", ["title" => "MealPlanner"]);
    }

    public function dashboard(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("dashboard", "dashboard");
    }

    public function mealPlanner(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("meal-planner", "meal-planner");
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
        if ($response = $this->requireLogin()) {
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
        return $this->renderAppView("recipe-details", "recipes");
    }

    public function addRecipe(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        return $this->renderAppView("add-recipe", "recipes");
    }

    public function recipeReviews(): Response
    {
        if ($response = $this->requireLogin()) {
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
