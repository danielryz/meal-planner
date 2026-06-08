<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\ProfileController;
use App\Controllers\SecurityController;
use App\Http\Request;
use App\Http\Router;
use App\Http\ViewRenderer;

final class Routing
{
    public static array $routes = [
        "login" => [
            "controller" => SecurityController::class,
            "action" => "login",
        ],
        "register" => [
            "controller" => SecurityController::class,
            "action" => "register",
        ],
        "about" => [
            "controller" => DashboardController::class,
            "action" => "about",
        ],
        "recipes" => [
            "controller" => DashboardController::class,
            "action" => "recipes",
        ],
        "recipe-details" => [
            "controller" => DashboardController::class,
            "action" => "recipeDetails",
        ],
        "add-recipe" => [
            "controller" => DashboardController::class,
            "action" => "addRecipe",
        ],
        "recipe-reviews" => [
            "controller" => DashboardController::class,
            "action" => "recipeReviews",
        ],
        "recipe-management" => [
            "controller" => DashboardController::class,
            "action" => "recipeManagement",
        ],
        "meal-planner" => [
            "controller" => DashboardController::class,
            "action" => "mealPlanner",
        ],
        "grocery-list" => [
            "controller" => DashboardController::class,
            "action" => "groceryList",
        ],
        "users" => [
            "controller" => DashboardController::class,
            "action" => "users",
        ],
        "profile" => [
            "controller" => DashboardController::class,
            "action" => "profile",
        ],
        "settings" => [
            "controller" => DashboardController::class,
            "action" => "settings",
        ],
        "notification-settings" => [
            "controller" => DashboardController::class,
            "action" => "notificationSettings",
        ],
        "preferences" => [
            "controller" => DashboardController::class,
            "action" => "preferences",
        ],
        "api/profile" => [
            "controller" => ProfileController::class,
            "action" => "getProfile",
        ],
        "api/settings/account" => [
            "controller" => ProfileController::class,
            "action" => "getAccount",
        ],
        "api/settings/profile" => [
            "controller" => ProfileController::class,
            "action" => "updateProfile",
        ],
        "api/settings/password-change" => [
            "controller" => ProfileController::class,
            "action" => "changePassword",
        ],
        "api/settings/notifications" => [
            "controller" => ProfileController::class,
            "action" => "notifications",
        ],
        "api/settings/preferences" => [
            "controller" => ProfileController::class,
            "action" => "preferences",
        ],
        "logout" => [
            "controller" => SecurityController::class,
            "action" => "logout",
        ],
        "dashboard" => [
            "controller" => DashboardController::class,
            "action" => "dashboard",
        ],
        "" => [
            "controller" => DashboardController::class,
            "action" => "home",
        ],
    ];

    public static function run(Request $request, string $projectRoot): void
    {
        $router = new Router(self::$routes, new ViewRenderer($projectRoot));
        $router->dispatch($request)->send();
    }
}
