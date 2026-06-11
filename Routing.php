<?php

declare(strict_types=1);

use App\Controllers\AiController;
use App\Controllers\PaymentController;
use App\Controllers\AdminApiController;
use App\Controllers\AdminController;
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
        "api/recipes/{recipeId}/rating" => [
            "controller" => RecipeController::class,
            "action" => "rating",
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
        "api/media/avatars/current" => [
            "controller" => MediaController::class,
            "action"     => "deleteAvatar",
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
        "api/profile/favorites" => [
            "controller" => ProfileController::class,
            "action" => "getProfileFavorites",
        ],
        "api/profile/recipes" => [
            "controller" => ProfileController::class,
            "action" => "getProfileRecipes",
        ],
        "api/profile/activity" => [
            "controller" => ProfileController::class,
            "action" => "getProfileActivity",
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
        "admin-panel/login" => [
            "controller" => AdminController::class,
            "action" => "login",
        ],
        "admin-panel/dashboard" => [
            "controller" => AdminController::class,
            "action" => "dashboard",
        ],
        "admin-panel/users" => [
            "controller" => AdminController::class,
            "action" => "users",
        ],
        "admin-panel/users/{userId}" => [
            "controller" => AdminController::class,
            "action" => "userDetail",
        ],
        "admin-panel/recipe-reviews" => [
            "controller" => AdminController::class,
            "action" => "recipeReviews",
        ],
        "admin-panel/settings" => [
            "controller" => AdminController::class,
            "action" => "settings",
        ],
        "api/admin/login" => [
            "controller" => AdminApiController::class,
            "action" => "login",
        ],
        "api/admin/logout" => [
            "controller" => AdminApiController::class,
            "action" => "logout",
        ],
        "api/admin/stats" => [
            "controller" => AdminApiController::class,
            "action" => "stats",
        ],
        "api/admin/users" => [
            "controller" => AdminApiController::class,
            "action" => "users",
        ],
        "api/admin/users/invite" => [
            "controller" => AdminApiController::class,
            "action" => "inviteUser",
        ],
        "api/admin/users/{userId}" => [
            "controller" => AdminApiController::class,
            "action" => "userDetail",
        ],
        "api/admin/users/{userId}/send-password-reset" => [
            "controller" => AdminApiController::class,
            "action" => "sendPasswordReset",
        ],
        "api/admin/recipe-reviews" => [
            "controller" => AdminApiController::class,
            "action" => "recipeReviews",
        ],
        "api/admin/recipes/{recipeId}/approve" => [
            "controller" => AdminApiController::class,
            "action" => "approveRecipe",
        ],
        "api/admin/recipes/{recipeId}/request-changes" => [
            "controller" => AdminApiController::class,
            "action" => "requestChangesRecipe",
        ],
        "api/admin/recipes/{recipeId}/reject" => [
            "controller" => AdminApiController::class,
            "action" => "rejectRecipe",
        ],
        "api/admin/settings" => [
            "controller" => AdminApiController::class,
            "action" => "settings",
        ],
        "api/ai/chat" => [
            "controller" => AiController::class,
            "action" => "chat",
        ],
        "api/ai/warmup" => [
            "controller" => AiController::class,
            "action" => "warmup",
        ],
        "api/payments/create" => [
            "controller" => PaymentController::class,
            "action" => "create",
        ],
        "api/payments/notify" => [
            "controller" => PaymentController::class,
            "action" => "notify",
        ],
        "auth/google" => [
            "controller" => SecurityController::class,
            "action" => "googleAuth",
        ],
        "auth/google/callback" => [
            "controller" => SecurityController::class,
            "action" => "googleCallback",
        ],
        "auth/apple" => [
            "controller" => SecurityController::class,
            "action" => "appleAuth",
        ],
        "auth/apple/callback" => [
            "controller" => SecurityController::class,
            "action" => "appleCallback",
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
