<?php

declare(strict_types=1);

namespace App\Http;

final class ViewRenderer
{
    private const VIEW_PATHS = [
        'index' => 'public/features/public/index.html',
        'about'   => 'public/features/public/about.html',
        'support' => 'public/features/public/support.html',
        'contact' => 'public/features/public/contact.html',
        'privacy' => 'public/features/public/privacy.html',
        'terms'   => 'public/features/public/terms.html',
        'login' => 'public/features/auth/login.html',
        'register' => 'public/features/auth/register.html',
        'activate'         => 'public/features/auth/activate.html',
        'resend-activation' => 'public/features/auth/resend-activation.html',
        'forgot-password'  => 'public/features/auth/forgot-password.html',
        'reset-password'   => 'public/features/auth/reset-password.html',
        'invitation'       => 'public/features/auth/invitation.html',
        'dashboard' => 'public/features/app/dashboard.html',
        'app-placeholder' => 'public/features/app/app-placeholder.html',
        'meal-planner' => 'public/features/meal-planner/meal-planner.html',
        'recipes' => 'public/features/recipes/recipes.html',
        'recipe-details' => 'public/features/recipes/recipe-details.html',
        'add-recipe' => 'public/features/recipes/add-recipe.html',
        'edit-recipe' => 'public/features/recipes/edit-recipe.html',
        'recipe-reviews' => 'public/features/recipes/recipe-reviews.html',
        'recipe-management' => 'public/features/recipes/recipe-management.html',
        'grocery-list' => 'public/features/grocery-list/grocery-list.html',
        'users' => 'public/features/users/users.html',
        'profile' => 'public/features/profile/profile.html',
        'settings' => 'public/features/profile/settings.html',
        'notification-settings' => 'public/features/profile/notification-settings.html',
        'preferences' => 'public/features/profile/preferences.html',
        'admin-panel-login'           => 'public/features/admin-panel/admin-panel-login.html',
        'admin-panel-dashboard'       => 'public/features/admin-panel/admin-panel-dashboard.html',
        'admin-panel-users'           => 'public/features/admin-panel/admin-panel-users.html',
        'admin-panel-user-detail'     => 'public/features/admin-panel/admin-panel-user-detail.html',
        'admin-panel-recipe-reviews'  => 'public/features/admin-panel/admin-panel-recipe-reviews.html',
        'admin-panel-settings'        => 'public/features/admin-panel/admin-panel-settings.html',
    ];

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function render(string $template, array $variables = [], int $statusCode = 200): Response
    {
        $templatePath = self::VIEW_PATHS[$template] ?? 'public/views/' . $template . '.html';
        $absoluteTemplatePath = $this->projectRoot . DIRECTORY_SEPARATOR . $templatePath;

        if (!is_file($absoluteTemplatePath)) {
            return $this->renderError(404);
        }

        return Response::html($this->renderFile($absoluteTemplatePath, $variables), $statusCode);
    }

    public function renderError(int $statusCode, array $variables = []): Response
    {
        $templatePath = $this->projectRoot . DIRECTORY_SEPARATOR . 'public/views/' . $statusCode . '.html';

        if (!is_file($templatePath)) {
            return Response::html('', $statusCode);
        }

        return Response::html($this->renderFile($templatePath, $variables), $statusCode);
    }

    private function renderFile(string $templatePath, array $variables): string
    {
        $partialsPath = 'public/views/partials';
        extract($variables, EXTR_SKIP);

        ob_start();
        include $templatePath;

        return (string) ob_get_clean();
    }
}
