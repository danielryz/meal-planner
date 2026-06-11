<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Database\Database;
use App\Database\TransactionManager;
use App\Http\Response;
use App\Repositories\RecipeRepository;
use App\Repositories\UserRepository;

final class AdminApiController extends AppController
{
    public function login(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $csrfToken = $this->request->input('csrfToken');
        if (!$this->csrfTokens->isValid('admin_login', $csrfToken)) {
            return Response::json(['error' => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'], 400);
        }

        $email    = (string) $this->request->input('email', '');
        $password = (string) $this->request->input('password', '');

        $db   = new Database();
        $repo = new UserRepository($db->connection());
        $tx   = new TransactionManager($db->connection());
        $auth = new AuthService($repo, $tx, $this->sessions);

        $result = $auth->login($email, $password);

        if (!$result->isSuccess()) {
            return Response::json(['error' => 'Niepoprawny e-mail lub hasło.'], 401);
        }

        $user = $this->sessions->currentUser();
        if ($user === null || !in_array($user->role(), ['owner', 'admin'], true)) {
            $this->sessions->logout();
            return Response::json(['error' => 'Brak uprawnień do panelu administracyjnego.'], 403);
        }

        return Response::json(['success' => true]);
    }

    public function logout(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $this->sessions->logout();
        return Response::json(['success' => true]);
    }

    public function stats(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        $db  = new Database();
        $pdo = $db->connection();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users u JOIN user_profiles up ON up.user_id = u.id WHERE u.is_active = TRUE AND u.email_verified_at IS NOT NULL");
        $activeUsers = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL AND is_active = TRUE");
        $pendingUsers = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = FALSE");
        $suspendedUsers = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM recipes WHERE status = 'approved' AND visibility = 'public'");
        $publicRecipes = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM recipes WHERE status = 'submitted'");
        $pendingRecipes = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM meal_plans WHERE status = 'active'");
        $activePlans = (int) $stmt->fetchColumn();

        $stmt = $pdo->query(
            "SELECT uae.event_type, uae.created_at,
                    up.display_name AS actor_name, u.email AS actor_email
             FROM user_activity_events uae
             LEFT JOIN users u ON u.id = uae.user_id
             LEFT JOIN user_profiles up ON up.user_id = uae.user_id
             WHERE uae.event_type IN ('approved_recipe', 'rejected_recipe', 'requested_changes', 'login_failed')
             ORDER BY uae.created_at DESC
             LIMIT 10"
        );
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json([
            'users' => [
                'active'    => $activeUsers,
                'pending'   => $pendingUsers,
                'suspended' => $suspendedUsers,
                'total'     => $activeUsers + $pendingUsers + $suspendedUsers,
            ],
            'recipes' => [
                'public'  => $publicRecipes,
                'pending' => $pendingRecipes,
            ],
            'activePlans' => $activePlans,
            'recentEvents' => array_map(fn($e) => [
                'eventType'  => $e['event_type'],
                'actorName'  => $e['actor_name'],
                'actorEmail' => $e['actor_email'],
                'createdAt'  => $e['created_at'] ? substr((string) $e['created_at'], 0, 10) : null,
            ], $events),
        ]);
    }

    public function users(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if ($this->isPatch()) {
            return $this->handleUserUpdate();
        }

        $db   = new Database();
        $pdo  = $db->connection();

        $query  = (string) $this->request->query('query', '');
        $role   = (string) $this->request->query('role', '');
        $status = (string) $this->request->query('status', '');

        $where  = ['1=1'];
        $params = [];

        if ($query !== '') {
            $where[]  = "(lower(u.email) LIKE lower(:query) OR lower(up.display_name) LIKE lower(:query2))";
            $params[':query']  = '%' . $query . '%';
            $params[':query2'] = '%' . $query . '%';
        }

        if ($role !== '') {
            $where[]       = "r.name = :role";
            $params[':role'] = $role;
        }

        if ($status === 'active') {
            $where[] = "u.is_active = TRUE AND u.email_verified_at IS NOT NULL";
        } elseif ($status === 'pending') {
            $where[] = "u.email_verified_at IS NULL AND u.is_active = TRUE";
        } elseif ($status === 'suspended') {
            $where[] = "u.is_active = FALSE";
        }

        $sql  = "SELECT u.id, u.email, u.created_at, u.last_login_at, u.is_active, u.email_verified_at,
                        r.name AS role, up.display_name, up.avatar_initials
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 JOIN user_profiles up ON up.user_id = u.id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY u.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users = array_map(fn($row) => [
            'id'          => (int) $row['id'],
            'name'        => $row['display_name'],
            'email'       => $row['email'],
            'role'        => $row['role'],
            'status'      => $this->mapUserStatus($row),
            'createdAt'   => $row['created_at'] ? substr((string) $row['created_at'], 0, 10) : null,
            'lastLoginAt' => $row['last_login_at'] ? substr((string) $row['last_login_at'], 0, 10) : null,
            'initials'    => $row['avatar_initials'] ?? strtoupper(substr($row['display_name'], 0, 2)),
        ], $rows);

        $active    = count(array_filter($users, fn($u) => $u['status'] === 'active'));
        $pending   = count(array_filter($users, fn($u) => $u['status'] === 'pending'));
        $suspended = count(array_filter($users, fn($u) => $u['status'] === 'suspended'));

        return Response::json([
            'users' => $users,
            'stats' => [
                'active'    => $active,
                'pending'   => $pending,
                'suspended' => $suspended,
                'total'     => count($users),
            ],
        ]);
    }

    public function userDetail(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if ($this->isPatch()) {
            return $this->handleUserUpdate();
        }

        $userId = (int) $this->request->routeParam('userId');
        $db     = new Database();
        $pdo    = $db->connection();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.email, u.created_at, u.last_login_at, u.is_active, u.email_verified_at,
                    r.name AS role, up.display_name, up.avatar_initials
             FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id = :id"
        );
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return $this->jsonError('Użytkownik nie istnieje.', 404);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE author_user_id = :id");
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $recipeCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id = :id");
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $planCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorite_recipes WHERE user_id = :id");
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $favoriteCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipe_publication_reviews WHERE reviewer_user_id = :id");
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $reviewCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT event_type, created_at FROM user_activity_events
             WHERE user_id = :id ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $activity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json([
            'user' => [
                'id'          => (int) $row['id'],
                'name'        => $row['display_name'],
                'email'       => $row['email'],
                'role'        => $row['role'],
                'status'      => $this->mapUserStatus($row),
                'createdAt'   => $row['created_at'] ? substr((string) $row['created_at'], 0, 10) : null,
                'lastLoginAt' => $row['last_login_at'] ? substr((string) $row['last_login_at'], 0, 10) : null,
                'initials'    => $row['avatar_initials'] ?? strtoupper(substr($row['display_name'], 0, 2)),
                'stats' => [
                    'recipes'   => $recipeCount,
                    'plans'     => $planCount,
                    'favorites' => $favoriteCount,
                    'reviews'   => $reviewCount,
                ],
                'activity' => array_map(fn($e) => [
                    'eventType' => $e['event_type'],
                    'createdAt' => $e['created_at'] ? substr((string) $e['created_at'], 0, 16) : null,
                ], $activity),
            ],
        ]);
    }

    public function sendPasswordReset(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        return Response::json(['success' => true, 'message' => 'Funkcja wysyłki e-mail wymaga konfiguracji skrzynki pocztowej.']);
    }

    public function inviteUser(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        return $this->jsonError('Zaproszenia wymagają konfiguracji skrzynki pocztowej.', 501);
    }

    public function recipeReviews(): Response
    {
        if ($response = $this->requireRole('owner', 'admin', 'employee')) {
            return $response;
        }

        $db     = new Database();
        $pdo    = $db->connection();

        $query  = (string) $this->request->query('query', '');
        $status = (string) $this->request->query('status', 'all');

        $allowedStatuses = ['submitted', 'approved', 'rejected', 'changes_requested', 'draft'];

        $where  = ['1=1'];
        $params = [];

        if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
            $where[]       = "r.status = :status";
            $params[':status'] = $status;
        } else {
            $where[] = "r.status IN ('submitted', 'approved', 'rejected', 'changes_requested')";
        }

        if ($query !== '') {
            $where[]         = "(lower(r.title) LIKE lower(:query) OR lower(up.display_name) LIKE lower(:query2))";
            $params[':query']  = '%' . $query . '%';
            $params[':query2'] = '%' . $query . '%';
        }

        $sql = "SELECT r.id, r.title, r.description, r.status, r.submitted_at, r.prep_time_minutes, r.servings,
                       up.display_name AS author_name, u.email AS author_email,
                       rc.label AS category_label,
                       (SELECT reason FROM recipe_publication_reviews
                        WHERE recipe_id = r.id AND reason IS NOT NULL
                        ORDER BY created_at DESC LIMIT 1) AS review_note
                FROM recipes r
                JOIN users u ON u.id = r.author_user_id
                JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN recipe_categories rc ON rc.id = r.category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.submitted_at ASC NULLS LAST, r.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $reviews = array_map(fn($row) => [
            'id'             => (int) $row['id'],
            'title'          => $row['title'],
            'author'         => $row['author_name'],
            'authorEmail'    => $row['author_email'],
            'status'         => $row['status'] === 'submitted' ? 'pending' : $row['status'],
            'submittedAt'    => $row['submitted_at'] ? substr((string) $row['submitted_at'], 0, 10) : null,
            'summary'        => $row['description'],
            'category'       => $row['category_label'],
            'prepTimeMinutes'=> (int) $row['prep_time_minutes'],
            'servings'       => (int) $row['servings'],
            'reviewNote'     => (string) ($row['review_note'] ?? ''),
        ], $rows);

        $pending   = count(array_filter($reviews, fn($r) => $r['status'] === 'pending'));
        $changes   = count(array_filter($reviews, fn($r) => $r['status'] === 'changes_requested'));
        $approved  = count(array_filter($reviews, fn($r) => $r['status'] === 'approved'));
        $rejected  = count(array_filter($reviews, fn($r) => $r['status'] === 'rejected'));

        return Response::json([
            'reviews' => $reviews,
            'stats' => [
                'pending'            => $pending,
                'changes_requested'  => $changes,
                'approved'           => $approved,
                'rejected'           => $rejected,
            ],
        ]);
    }

    public function approveRecipe(): Response
    {
        if ($response = $this->requireRole('owner', 'admin', 'employee')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $userId   = $this->sessions->currentUser()->id();

        $db   = new Database();
        $repo = new RecipeRepository($db->connection());
        try {
            $repo->approveRecipe($recipeId, $userId);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'not_found') return $this->jsonError('Przepis nie istnieje.', 404);
            return $this->jsonError('Nieprawidłowy status przepisu.', 422);
        }

        return Response::json(['success' => true]);
    }

    public function requestChangesRecipe(): Response
    {
        if ($response = $this->requireRole('owner', 'admin', 'employee')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $note     = trim((string) $this->request->input('note', ''));

        if ($note === '') {
            return $this->jsonError('Komentarz jest wymagany.', 422);
        }

        $userId   = $this->sessions->currentUser()->id();
        $db       = new Database();
        $repo     = new RecipeRepository($db->connection());
        try {
            $repo->requestChanges($recipeId, $userId, $note);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'not_found') return $this->jsonError('Przepis nie istnieje.', 404);
            return $this->jsonError('Nieprawidłowy status przepisu.', 422);
        }

        return Response::json(['success' => true]);
    }

    public function rejectRecipe(): Response
    {
        if ($response = $this->requireRole('owner', 'admin', 'employee')) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $recipeId = (int) $this->request->routeParam('recipeId');
        $reason   = trim((string) $this->request->input('reason', ''));

        if ($reason === '') {
            return $this->jsonError('Powód odrzucenia jest wymagany.', 422);
        }

        $userId   = $this->sessions->currentUser()->id();
        $db       = new Database();
        $repo     = new RecipeRepository($db->connection());
        try {
            $repo->rejectRecipe($recipeId, $userId, $reason);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'not_found') return $this->jsonError('Przepis nie istnieje.', 404);
            return $this->jsonError('Nieprawidłowy status przepisu.', 422);
        }

        return Response::json(['success' => true]);
    }

    public function settings(): Response
    {
        if ($response = $this->requireRole('owner', 'admin')) {
            return $response;
        }

        if ($this->isPost()) {
            return $this->saveSettings();
        }

        return Response::json(['settings' => $this->loadSettings()]);
    }

    private function saveSettings(): Response
    {
        $current = $this->loadSettings();

        $mediaLimitMb    = $this->request->input('mediaLimitMb');
        $videoLimitMb    = $this->request->input('videoLimitMb');
        $sessionTtlHours = $this->request->input('sessionTtlHours');
        $rememberMeDays  = $this->request->input('rememberMeDays');
        $appName         = $this->request->input('appName');
        $tagline         = $this->request->input('tagline');
        $contactEmail    = $this->request->input('contactEmail');
        $allowedInviteRoles = $this->request->input('allowedInviteRoles');

        if ($mediaLimitMb !== null)    $current['mediaLimitMb']    = (int) $mediaLimitMb;
        if ($videoLimitMb !== null)    $current['videoLimitMb']    = (int) $videoLimitMb;
        if ($sessionTtlHours !== null) $current['sessionTtlHours'] = (int) $sessionTtlHours;
        if ($rememberMeDays !== null)  $current['rememberMeDays']  = (int) $rememberMeDays;
        if ($appName !== null)         $current['appName']         = (string) $appName;
        if ($tagline !== null)         $current['tagline']         = (string) $tagline;
        if ($contactEmail !== null)    $current['contactEmail']    = (string) $contactEmail;
        if (is_array($allowedInviteRoles)) $current['allowedInviteRoles'] = $allowedInviteRoles;

        $this->persistSettings($current);

        return Response::json(['success' => true, 'settings' => $current]);
    }

    private function loadSettings(): array
    {
        $path = dirname(__DIR__, 2) . '/storage/app-settings.json';

        if (!is_file($path)) {
            return $this->defaultSettings();
        }

        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? array_merge($this->defaultSettings(), $data) : $this->defaultSettings();
    }

    private function persistSettings(array $settings): void
    {
        $dir  = dirname(__DIR__, 2) . '/storage';
        $path = $dir . '/app-settings.json';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT));
    }

    private function defaultSettings(): array
    {
        return [
            'mediaLimitMb'       => 10,
            'videoLimitMb'       => 200,
            'sessionTtlHours'    => 24,
            'rememberMeDays'     => 30,
            'appName'            => 'MealPlanner',
            'tagline'            => 'Jedz lepiej. Żyj prościej.',
            'contactEmail'       => 'kontakt@mealplanner.pl',
            'allowedInviteRoles' => ['employee', 'user'],
        ];
    }

    private function handleUserUpdate(): Response
    {
        $userId = (int) $this->request->routeParam('userId');
        $role   = $this->request->input('role');
        $status = $this->request->input('status');

        $db   = new Database();
        $repo = new UserRepository($db->connection());
        $target = $repo->findById($userId);

        if ($target === null) {
            return $this->jsonError('Użytkownik nie istnieje.', 404);
        }

        if ($role !== null) {
            $allowed = ['owner', 'admin', 'employee', 'user'];
            if (!in_array($role, $allowed, true)) {
                return $this->jsonError('Nieprawidłowa rola.');
            }
            if ($target['role'] === 'owner' && $role !== 'owner') {
                return $this->jsonError('Nie można zmienić roli właściciela.', 403);
            }
            $repo->updateRole($userId, $role);
        }

        if ($status !== null) {
            if ($target['role'] === 'owner') {
                return $this->jsonError('Nie można zmienić statusu właściciela.', 403);
            }
            $isActive = $status === 'active';
            $repo->updateStatus($userId, $isActive);
        }

        return Response::json(['success' => true]);
    }

    private function mapUserStatus(array $row): string
    {
        if (!(bool) $row['is_active']) {
            return 'suspended';
        }
        if ($row['email_verified_at'] === null) {
            return 'pending';
        }
        return 'active';
    }
}
