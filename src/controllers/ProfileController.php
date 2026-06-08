<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\ProfileRepository;
use App\Repositories\SettingsRepository;

final class ProfileController extends AppController
{
    public function getProfile(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId = $this->sessions->currentUser()->id();
        $db = new Database();
        $repo = new ProfileRepository($db->connection());
        $row = $repo->findProfileByUserId($userId);

        if ($row === null) {
            return $this->jsonError('Nie znaleziono profilu.', 404);
        }

        return Response::json([
            'id'            => (int) $row['id'],
            'name'          => $row['display_name'],
            'username'      => $row['username'],
            'email'         => $row['email'],
            'role'          => $row['role'],
            'status'        => $row['is_active'] ? 'active' : 'inactive',
            'initials'      => $row['avatar_initials'] ?? strtoupper(substr($row['display_name'], 0, 2)),
            'bio'           => $row['bio'],
            'isPublic'      => (bool) $row['is_public'],
            'joinedAt'      => $row['created_at'],
            'lastLogin'     => $row['last_login_at'],
            'stats'         => [
                'favoriteRecipes' => (int) $row['favorite_recipes_count'],
                'ownRecipes'      => (int) $row['own_recipes_count'],
                'plannedMeals'    => 0,
            ],
        ]);
    }

    public function getAccount(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $user = $this->sessions->currentUser();
        $db = new Database();
        $repo = new ProfileRepository($db->connection());
        $row = $repo->findProfileByUserId($user->id());

        if ($row === null) {
            return $this->jsonError('Nie znaleziono konta.', 404);
        }

        return Response::json([
            'name'               => $row['display_name'],
            'username'           => $row['username'],
            'email'              => $row['email'],
            'role'               => $row['role'],
            'status'             => $row['is_active'] ? 'active' : 'inactive',
            'initials'           => $row['avatar_initials'] ?? strtoupper(substr($row['display_name'], 0, 2)),
            'lastPasswordChange' => $row['password_changed_at'],
        ]);
    }

    public function updateProfile(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPatch()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $displayName = trim((string) $this->request->input('displayName', ''));
        $username    = trim((string) $this->request->input('username', ''));
        $errors      = [];

        if (strlen($displayName) < 2 || strlen($displayName) > 120) {
            $errors['displayName'] = 'Imię i nazwisko musi mieć od 2 do 120 znaków.';
        }

        if (strlen($username) < 3 || strlen($username) > 64) {
            $errors['username'] = 'Nazwa użytkownika musi mieć od 3 do 64 znaków.';
        } elseif (!preg_match('/^[a-z0-9_]+$/i', $username)) {
            $errors['username'] = 'Nazwa użytkownika może zawierać tylko litery, cyfry i podkreślenia.';
        }

        if ($errors !== []) {
            return Response::json(['error' => 'Formularz zawiera błędy.', 'fields' => $errors], 422);
        }

        $userId = $this->sessions->currentUser()->id();
        $db = new Database();
        $repo = new ProfileRepository($db->connection());

        if ($repo->usernameExistsForOther($username, $userId)) {
            return Response::json(['error' => 'Formularz zawiera błędy.', 'fields' => [
                'username' => 'Ta nazwa użytkownika jest już zajęta.',
            ]], 422);
        }

        $repo->updateDisplayName($userId, $displayName);
        $repo->updateUsername($userId, $username);

        return Response::json(['success' => true]);
    }

    public function changePassword(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $currentPassword = (string) $this->request->input('currentPassword', '');
        $newPassword     = (string) $this->request->input('newPassword', '');
        $errors          = [];

        if ($currentPassword === '') {
            $errors['currentPassword'] = 'Podaj aktualne hasło.';
        }

        if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
            $errors['newPassword'] = 'Nowe hasło musi mieć od 8 do 128 znaków.';
        }

        if ($errors !== []) {
            return Response::json(['error' => 'Formularz zawiera błędy.', 'fields' => $errors], 422);
        }

        $userId = $this->sessions->currentUser()->id();
        $db = new Database();
        $repo = new ProfileRepository($db->connection());
        $hash = $repo->findPasswordHashById($userId);

        if ($hash === null || !password_verify($currentPassword, $hash)) {
            return Response::json(['error' => 'Formularz zawiera błędy.', 'fields' => [
                'currentPassword' => 'Aktualne hasło jest nieprawidłowe.',
            ]], 422);
        }

        $repo->updatePasswordHash($userId, password_hash($newPassword, PASSWORD_DEFAULT));

        return Response::json(['success' => true]);
    }

    public function notifications(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId = $this->sessions->currentUser()->id();
        $db = new Database();
        $repo = new SettingsRepository($db->connection());

        if ($this->isGet()) {
            $row = $repo->getNotificationPreferences($userId);

            return Response::json($row ?? []);
        }

        if ($this->isPatch()) {
            $data = [
                'mealRemindersEmail'    => $this->request->input('mealRemindersEmail'),
                'groceryRemindersEmail' => $this->request->input('groceryRemindersEmail'),
                'recipeReviewApp'       => $this->request->input('recipeReviewApp'),
                'teamActivityApp'       => $this->request->input('teamActivityApp'),
                'accountSecurityEmail'  => $this->request->input('accountSecurityEmail'),
                'quietHoursStart'       => $this->request->input('quietHoursStart', '22:00'),
                'quietHoursEnd'         => $this->request->input('quietHoursEnd', '07:00'),
            ];
            $repo->saveNotificationPreferences($userId, $data);

            return Response::json(['success' => true]);
        }

        return $this->jsonError('Metoda niedozwolona.', 405);
    }

    public function preferences(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $userId = $this->sessions->currentUser()->id();
        $db = new Database();
        $repo = new SettingsRepository($db->connection());

        if ($this->isGet()) {
            $row = $repo->getFoodPreferences($userId);

            return Response::json($row ?? []);
        }

        if ($this->isPatch()) {
            $data = [
                'dietType'            => $this->request->input('dietType'),
                'defaultServings'     => $this->request->input('defaultServings', 2),
                'mealsPerDay'         => $this->request->input('mealsPerDay', 3),
                'weeklyBudgetCents'   => $this->request->input('weeklyBudgetCents'),
                'dislikedIngredients' => $this->request->input('dislikedIngredients'),
            ];
            $repo->saveFoodPreferences($userId, $data);

            return Response::json(['success' => true]);
        }

        return $this->jsonError('Metoda niedozwolona.', 405);
    }
}
