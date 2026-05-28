<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

// TODO musimy zapewnic, ze utworzony 
// obiekt kontrollera ma tylko jedna instancję - SINGLETON

// TODO 2 /dashboard -- wszystkei dnae
// /dashboard/12234 -- wyciagnie nam jakis elemtn o wskaznaym ID 12234
// REGEX
class Routing {

    public static $routes = [
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
        "about" => [
            "controller" => "DashboardController",
            "action" => "about"
        ],
        "recipes" => [
            "controller" => "DashboardController",
            "action" => "recipes"
        ],
        "recipe-details" => [
            "controller" => "DashboardController",
            "action" => "recipeDetails"
        ],
        "meal-planner" => [
            "controller" => "DashboardController",
            "action" => "mealPlanner"
        ],
        "grocery-list" => [
            "controller" => "DashboardController",
            "action" => "groceryList"
        ],
        "users" => [
            "controller" => "DashboardController",
            "action" => "users"
        ],
        "settings" => [
            "controller" => "DashboardController",
            "action" => "settings"
        ],
        "logout" => [
            "controller" => "SecurityController",
            "action" => "logout"
        ],
        "dashboard" => [
            "controller" => "DashboardController",
            "action" => "dashboard"
        ],
        "" => [
            "controller" => "DashboardController",
            "action" => "home"
        ],
    ];

    public static function run(string $path) {
        // TODO sprawdzać za pomoca array_key_exists
        switch($path) {
            case 'dashboard':
            case '':
            case 'about':
            case 'recipes':
            case 'recipe-details':
            case 'meal-planner':
            case 'grocery-list':
            case 'users':
            case 'settings':
            case 'login':
            case 'logout':
            case 'register':
                $controller = Routing::$routes[$path]["controller"];
                $action = Routing::$routes[$path]["action"];

                $controllerObj = new $controller;
                $id = null;

                $controllerObj->$action($id);
                break; 
            default:
                http_response_code(404);
                include 'public/views/404.html';
                break;
        }
    }
}
