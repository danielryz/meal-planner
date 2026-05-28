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
        return $this->renderAppPlaceholder("meal-planner", "Plan posiłków", "Widok planowania posiłków zostanie wdrożony w FE-05.");
    }

    public function groceryList() {
        return $this->renderAppPlaceholder("grocery-list", "Lista zakupów", "Interaktywna lista zakupów zostanie wdrożona w FE-08.");
    }

    public function users() {
        return $this->renderAppPlaceholder("users", "Zespół", "Widok zarządzania użytkownikami dla właściciela zostanie wdrożony w FE-10.");
    }

    public function settings() {
        return $this->renderAppPlaceholder("settings", "Ustawienia", "Ustawienia konta i preferencji zostaną podłączone w późniejszym etapie.");
    }

    public function about() {
        return $this->render("about");
    }

    public function recipes() {
        return $this->render("recipes");
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
