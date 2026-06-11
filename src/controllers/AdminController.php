<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;

final class AdminController extends AppController
{
    public function login(): Response
    {
        $user = $this->sessions->currentUser();
        if ($user !== null && in_array($user->role(), ['owner', 'admin'], true)) {
            return $this->redirect('/admin-panel/dashboard');
        }

        $csrfToken = $this->csrfTokens->token('admin_login');
        return $this->render('admin-panel-login', ['csrfToken' => $csrfToken]);
    }

    public function dashboard(): Response
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        return $this->render('admin-panel-dashboard', $this->adminViewData('dashboard'));
    }

    public function users(): Response
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        return $this->render('admin-panel-users', $this->adminViewData('users'));
    }

    public function userDetail(): Response
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        $userId = (int) $this->request->routeParam('userId');
        return $this->render('admin-panel-user-detail', array_merge(
            $this->adminViewData('users'),
            ['userId' => $userId]
        ));
    }

    public function recipeReviews(): Response
    {
        if ($response = $this->requireModerator()) {
            return $response;
        }
        return $this->render('admin-panel-recipe-reviews', $this->adminViewData('recipe-reviews'));
    }

    public function settings(): Response
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        return $this->render('admin-panel-settings', $this->adminViewData('settings'));
    }

    private function requireAdmin(): ?Response
    {
        $user = $this->sessions->currentUser();
        if ($user === null || !in_array($user->role(), ['owner', 'admin'], true)) {
            return $this->redirect('/admin-panel/login');
        }
        return null;
    }

    private function requireModerator(): ?Response
    {
        $user = $this->sessions->currentUser();
        if ($user === null || !in_array($user->role(), ['owner', 'admin', 'employee'], true)) {
            return $this->redirect('/admin-panel/login');
        }
        return null;
    }

    private function adminViewData(string $activeSection): array
    {
        $user = $this->sessions->currentUser();
        return [
            'adminUserName'  => $user?->displayName() ?? '',
            'adminUserRole'  => $user?->role() ?? '',
            'activeSection'  => $activeSection,
        ];
    }
}
