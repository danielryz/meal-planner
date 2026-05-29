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
        return $this->renderAppView("dashboard", "dashboard");
    }

    public function mealPlanner(): Response
    {
        return $this->renderAppView("meal-planner", "meal-planner");
    }

    public function groceryList(): Response
    {
        return $this->renderAppView("grocery-list", "grocery-list");
    }

    public function users(): Response
    {
        return $this->renderAppView("users", "users");
    }

    public function profile(): Response
    {
        return $this->renderAppView("profile", "profile");
    }

    public function settings(): Response
    {
        return $this->renderAppView("settings", "settings");
    }

    public function notificationSettings(): Response
    {
        return $this->renderAppView("notification-settings", "settings");
    }

    public function preferences(): Response
    {
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
        return $this->renderAppView("add-recipe", "recipes");
    }

    public function recipeReviews(): Response
    {
        return $this->renderAppView("recipe-reviews", "recipes");
    }

    public function recipeManagement(): Response
    {
        return $this->renderAppView("recipe-management", "recipes");
    }

    private function renderAppView(string $template, string $currentRoute): Response
    {
        return $this->render($template, [
            "currentRoute" => $currentRoute,
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak",
        ]);
    }
}
