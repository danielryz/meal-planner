<?php

require_once 'AppController.php';

class DashboardController extends AppController {

    public function home() {
        // TODO pobieranie danych z bazy
        // wstawianie zmiennych na widok
        $title = "MealPlanner";

        return $this->render("index", ["title" => $title]);
    }

    public function dashboard() {
        return $this->render("dashboard", [
            "currentRoute" => "dashboard",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function mealPlanner() {
        return $this->render("meal-planner", [
            "currentRoute" => "meal-planner",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function groceryList() {
        return $this->render("grocery-list", [
            "currentRoute" => "grocery-list",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function users() {
        return $this->render("users", [
            "currentRoute" => "users",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function profile() {
        return $this->render("profile", [
            "currentRoute" => "profile",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function settings() {
        return $this->render("settings", [
            "currentRoute" => "settings",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function about() {
        return $this->render("about");
    }

    public function recipes() {
        return $this->render("recipes", [
            "currentRoute" => "recipes",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    public function recipeDetails() {
        return $this->render("recipe-details", [
            "currentRoute" => "recipes",
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak"
        ]);
    }

    private function renderAppPlaceholder(string $route, string $title, string $description) {
        return $this->render("app-placeholder", [
            "currentRoute" => $route,
            "currentUserRole" => "owner",
            "currentUserName" => "Anna Nowak",
            "placeholderTitle" => $title,
            "placeholderDescription" => $description,
        ]);
    }
}
