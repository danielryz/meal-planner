<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\UserRepository;

final class UserController extends AppController
{
    private const ALLOWED_ROLES = ['owner', 'employee', 'user'];

    public function listUsers(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        $db = new Database();
        $repo = new UserRepository($db->connection());
        $rows = $repo->findAll();

        $users = array_map(fn(array $row) => [
            'id'         => (int) $row['id'],
            'name'       => $row['display_name'],
            'email'      => $row['email'],
            'role'       => $row['role'],
            'status'     => $row['is_active'] ? 'active' : 'inactive',
            'lastActive' => $row['last_login_at'],
            'initials'   => $row['avatar_initials'] ?? strtoupper(substr($row['display_name'], 0, 2)),
        ], $rows);

        return Response::json(['users' => $users]);
    }

    public function updateRole(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if (!$this->isPatch()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $userId = (int) $this->request->routeParam('userId');
        $role   = (string) $this->request->input('role', '');

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            return $this->jsonError('Nieprawidłowa rola.');
        }

        $db = new Database();
        $repo = new UserRepository($db->connection());
        $target = $repo->findById($userId);

        if ($target === null) {
            return $this->jsonError('Użytkownik nie istnieje.', 404);
        }

        if ($target['role'] === 'owner' && $role !== 'owner') {
            return $this->jsonError('Nie można zmienić roli właściciela.', 403);
        }

        $repo->updateRole($userId, $role);

        return Response::json(['success' => true]);
    }

    public function updateStatus(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if (!$this->isPatch()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $userId = (int) $this->request->routeParam('userId');
        $status = (string) $this->request->input('status', '');

        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->jsonError('Nieprawidłowy status.');
        }

        $db = new Database();
        $repo = new UserRepository($db->connection());
        $target = $repo->findById($userId);

        if ($target === null) {
            return $this->jsonError('Użytkownik nie istnieje.', 404);
        }

        if ($target['role'] === 'owner') {
            return $this->jsonError('Nie można zablokować właściciela.', 403);
        }

        $repo->updateStatus($userId, $status === 'active');

        return Response::json(['success' => true]);
    }

    public function createInvitation(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        return $this->jsonError('Zaproszenia nie są jeszcze obsługiwane.', 501);
    }
}
