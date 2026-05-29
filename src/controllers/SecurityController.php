<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;

final class SecurityController extends AppController
{
    public function login(): Response
    {
        return $this->render("login");
    }

    public function register(): Response
    {
        return $this->render("register");
    }

    public function logout(): Response
    {
        return $this->render("login", [
            "pageTitle" => "Logowanie - MealPlanner",
        ]);
    }
}
