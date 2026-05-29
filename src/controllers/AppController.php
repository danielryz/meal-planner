<?php


class AppController {
    private const VIEW_PATHS = [
        "index" => "public/features/public/index.html",
        "about" => "public/features/public/about.html",
        "login" => "public/features/auth/login.html",
        "register" => "public/features/auth/register.html",
        "dashboard" => "public/features/app/dashboard.html",
        "app-placeholder" => "public/features/app/app-placeholder.html",
        "meal-planner" => "public/features/meal-planner/meal-planner.html",
        "recipes" => "public/features/recipes/recipes.html",
        "recipe-details" => "public/features/recipes/recipe-details.html",
        "add-recipe" => "public/features/recipes/add-recipe.html",
        "recipe-reviews" => "public/features/recipes/recipe-reviews.html",
        "recipe-management" => "public/features/recipes/recipe-management.html",
        "grocery-list" => "public/features/grocery-list/grocery-list.html",
        "users" => "public/features/users/users.html",
        "profile" => "public/features/profile/profile.html",
        "settings" => "public/features/profile/settings.html",
        "notification-settings" => "public/features/profile/notification-settings.html",
        "preferences" => "public/features/profile/preferences.html",
    ];

    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }
 
    protected function render(?string $template = null, array $variables = [])
    {
        $templatePath = self::VIEW_PATHS[$template] ?? 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $partialsPath = 'public/views/partials';
        $output = "";
                 
        if(file_exists($templatePath)){
            extract($variables);
            // ["tab_name" => $title]

            // $tab_name = $title

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        echo $output;
    }

}
