<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\GroceryListController;
use App\Controllers\MealPlanController;
use App\Controllers\MediaController;
use App\Controllers\ProfileController;
use App\Controllers\RecipeController;
use App\Controllers\ReviewController;
use App\Controllers\SecurityController;
use App\Controllers\UserController;
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
        "support" => [
            "controller" => DashboardController::class,
            "action" => "support",
        ],
        "contact" => [
            "controller" => DashboardController::class,
            "action" => "contact",
        ],
        "privacy" => [
            "controller" => DashboardController::class,
            "action" => "privacy",
        ],
        "terms" => [
            "controller" => DashboardController::class,
            "action" => "terms",
        ],
        "recipes" => [
            "controller" => DashboardController::class,
            "action" => "recipes",
        ],
        "recipe-details" => [
            "controller" => DashboardController::class,
            "action" => "recipeDetails",
        ],
        "recipe/{recipeId}" => [
            "controller" => DashboardController::class,
            "action" => "recipeDetail",
        ],
        "add-recipe" => [
            "controller" => DashboardController::class,
            "action" => "addRecipe",
        ],
        "edit-recipe/{recipeId}" => [
            "controller" => DashboardController::class,
            "action" => "editRecipe",
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
        "api/auth/login" => [
            "controller" => SecurityController::class,
            "action" => "loginApi",
        ],
        "api/auth/register" => [
            "controller" => SecurityController::class,
            "action" => "registerApi",
        ],
        "api/auth/resend-activation" => [
            "controller" => SecurityController::class,
            "action" => "resendActivationApi",
        ],
        "activate" => [
            "controller" => SecurityController::class,
            "action" => "activate",
        ],
        "resend-activation" => [
            "controller" => SecurityController::class,
            "action" => "resendActivationPage",
        ],
        "forgot-password" => [
            "controller" => SecurityController::class,
            "action" => "forgotPassword",
        ],
        "reset-password" => [
            "controller" => SecurityController::class,
            "action" => "resetPassword",
        ],
        "api/auth/forgot-password" => [
            "controller" => SecurityController::class,
            "action" => "forgotPasswordApi",
        ],
        "api/auth/reset-password" => [
            "controller" => SecurityController::class,
            "action" => "resetPasswordApi",
        ],
        "api/users" => [
            "controller" => UserController::class,
            "action" => "listUsers",
        ],
        "api/users/invitations" => [
            "controller" => UserController::class,
            "action" => "createInvitation",
        ],
        "api/users/{userId}/role" => [
            "controller" => UserController::class,
            "action" => "updateRole",
        ],
        "api/users/{userId}/status" => [
            "controller" => UserController::class,
            "action" => "updateStatus",
        ],
        "api/recipes" => [
            "controller" => RecipeController::class,
            "action" => "list",
        ],
        "api/recipes/form-options" => [
            "controller" => RecipeController::class,
            "action" => "formOptions",
        ],
        "api/recipes/drafts" => [
            "controller" => RecipeController::class,
            "action" => "createDraft",
        ],
        "api/recipes/{recipeId}" => [
            "controller" => RecipeController::class,
            "action" => "details",
        ],
        "api/recipes/{recipeId}/favorite" => [
            "controller" => RecipeController::class,
            "action" => "toggleFavorite",
        ],
        "api/recipes/{recipeId}/submit-for-review" => [
            "controller" => RecipeController::class,
            "action" => "submitForReview",
        ],
        "api/my-recipes" => [
            "controller" => RecipeController::class,
            "action" => "myRecipes",
        ],
        "api/recipe-reviews" => [
            "controller" => ReviewController::class,
            "action" => "queue",
        ],
        "api/recipe-reviews/{reviewId}/approve" => [
            "controller" => ReviewController::class,
            "action" => "approve",
        ],
        "api/recipe-reviews/{reviewId}/request-changes" => [
            "controller" => ReviewController::class,
            "action" => "requestChanges",
        ],
        "api/recipe-reviews/{reviewId}/reject" => [
            "controller" => ReviewController::class,
            "action" => "reject",
        ],
        "api/grocery-lists" => [
            "controller" => GroceryListController::class,
            "action" => "active",
        ],
        "api/grocery-lists/generate" => [
            "controller" => GroceryListController::class,
            "action" => "generate",
        ],
        "api/grocery-lists/{listId}/items" => [
            "controller" => GroceryListController::class,
            "action" => "addItem",
        ],
        "api/grocery-lists/{listId}/items/{itemId}" => [
            "controller" => GroceryListController::class,
            "action" => "itemAction",
        ],
        "api/meal-plans" => [
            "controller" => MealPlanController::class,
            "action" => "index",
        ],
        "api/meal-plans/{planId}" => [
            "controller" => MealPlanController::class,
            "action" => "details",
        ],
        "api/meal-plans/{planId}/slots/{slotId}/recipes" => [
            "controller" => MealPlanController::class,
            "action" => "addRecipe",
        ],
        "api/meal-plans/{planId}/slots/{slotId}/recipes/{recipeId}" => [
            "controller" => MealPlanController::class,
            "action" => "removeRecipe",
        ],
        "api/media/avatars" => [
            "controller" => MediaController::class,
            "action"     => "uploadAvatar",
        ],
        "api/media/recipe-photos" => [
            "controller" => MediaController::class,
            "action"     => "uploadRecipePhoto",
        ],
        "api/media/recipe-videos" => [
            "controller" => MediaController::class,
            "action"     => "uploadRecipeVideo",
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
        "api/settings/preference-options" => [
            "controller" => ProfileController::class,
            "action" => "preferenceOptions",
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
