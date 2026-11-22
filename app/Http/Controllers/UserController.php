<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use RuntimeException;
use Wbpms\Application\UserService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * UserController — HTTP layer for User Management (Module 3).
 *
 * Routes:
 *   GET    /users                → index()        — list all users
 *   GET    /users/create         → create()       — show create form (Owner only)
 *   POST   /users                → store()        — persist new user (Owner only)
 *   GET    /users/{id}/edit      → edit()         — show edit form (Owner + HRHead)
 *   POST   /users/{id}  (_method=PUT)  → update() — persist edits (Owner + HRHead)
 *   POST   /users/{id}/toggle         → toggle()    — activate/deactivate (Owner only)
 *   POST   /users/{id}/archive        → archive()   — archive (Owner only)
 *   POST   /users/{id}/unarchive      → unarchive() — restore archived user (Owner only)
 *
 * HRHead edit scope: HRHead may only edit Employee-role accounts.
 * BusinessOwner edit scope: may edit any account.
 *
 * REQ004–REQ008.
 */
final class UserController
{
    // -----------------------------------------------------------------------
    // POST /users/{id}/reset-password   (HRHead + BusinessOwner)
    // -----------------------------------------------------------------------

    /**
     * Reset a user's password to a new system-generated temporary password
     * and force them to change it on next login.
     *
     * HR Head may only reset Employee-role accounts.
     * Business Owner may reset any account.
     *
     * Requirement 13 — HR password reset extension.
     *
     * @param array<string,string> $params
     */
    public function resetPassword(array $params = []): void
    {
        [$service, $identity] = $this->setup();

        $userId    = (int) ($params['id'] ?? 0);
        $actorRole = $identity['role_name'] ?? '';
        $actorId   = (int) ($identity['user_id'] ?? 0);

        try {
            $user = $service->findOrFail($userId);
        } catch (RuntimeException) {
            $this->notFound();
            return;
        }

        // HR Head may only reset Employee-role accounts
        if ($actorRole === 'HRHead' && ($user['role_name'] ?? '') !== 'Employee') {
            ViewRenderer::flashError('HR Head may only reset passwords for Employee accounts.');
            $this->redirect('/users');
            return;
        }

        $config  = require APP_ROOT . '/config/database.php';
        $conn    = new Connection($config);
        $tempPwd = $service->generateTemporaryPassword();
        $hash    = password_hash($tempPwd, PASSWORD_DEFAULT);

        $conn->pdo()->prepare(
            "UPDATE users
                SET password_hash = :hash,
                    requires_password_change = 1,
                    updated_at = UTC_TIMESTAMP()
              WHERE user_id = :id"
        )->execute([':hash' => $hash, ':id' => $userId]);

        $conn->pdo()->prepare(
            "INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected,
                 record_id, description, action_at, created_at)
             VALUES
                (:uid, 'password_reset', 'password_reset', 'users',
                 :rid, :desc, :action_at, :created_at)"
        )->execute([
            ':uid'       => $actorId,
            ':rid'       => $userId,
            ':desc'      => "Password reset by {$actorRole} (user_id={$actorId}) for user '{$user['username']}'",
            ':action_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ':created_at'=> (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        ViewRenderer::render('users/reset-password-done', [
            'email'        => $user['account_email'],
            'tempPassword' => $tempPwd,
            'resetBy'      => $actorRole,
        ], 'Password Reset');
    }

    // -----------------------------------------------------------------------
    // GET /users — index
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function index(array $params = []): void
    {
        [$service, $identity] = $this->setup();

        $filterPosition = trim((string) ($_GET['position'] ?? ''));
        $filterUsername = trim((string) ($_GET['username'] ?? ''));
        $filterStatus   = trim((string) ($_GET['status']   ?? ''));
        $page           = max(1, (int) ($_GET['page'] ?? 1));
        $allowedPageSizes = [10, 20, 30, 50, 100];
        $pageSize       = in_array((int) ($_GET['page_size'] ?? 10), $allowedPageSizes, true)
            ? (int) ($_GET['page_size'] ?? 10)
            : 10;

        $rows = $service->findPaginated(
            page: $page,
            pageSize: $pageSize,
            includeArchived: true,
            position:        $filterPosition,
            username:        $filterUsername,
            status:          $filterStatus,
        );
        $total      = $service->countFiltered(
            includeArchived: true,
            position:        $filterPosition,
            username:        $filterUsername,
            status:          $filterStatus,
        );
        $pageSize   = $service->pageSize($pageSize);
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $totalPages);

        // Load active positions for the filter dropdown
        $positions = $this->pdo()->query(
            "SELECT position_title AS name
               FROM job_position
              WHERE status = 'Active'
              ORDER BY sort_order ASC, position_title ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Count stats for the summary cards (these are full counts, not paginated)
        $allRows = $service->list(
            includeArchived: true,
            position:        $filterPosition,
            username:        $filterUsername,
            status:          $filterStatus,
        );
        $active   = count(array_filter($allRows, fn($r) => $r['status'] === 'Active'));
        $inactive = count(array_filter($allRows, fn($r) => $r['status'] === 'Inactive'));
        $archived = count(array_filter($allRows, fn($r) => $r['status'] === 'Archived'));

        $currentUserId = (int) ($identity['user_id'] ?? 0);

        ViewRenderer::render('users/index', [
            'rows'            => $rows,
            'total'           => $total,
            'active'          => $active,
            'inactive'        => $inactive,
            'archived'        => $archived,
            'positions'       => $positions,
            'filterPosition'  => $filterPosition,
            'filterUsername'  => $filterUsername,
            'filterStatus'    => $filterStatus,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'pageSize'        => $pageSize,
            'currentUserId'   => $currentUserId,
            'roleName'        => $identity['role_name'] ?? '',
            'activePage'      => 'users',
        ], 'User Management');
    }

    // -----------------------------------------------------------------------
    // GET /users/create
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function create(array $params = []): void
    {
        [$service, $identity] = $this->setup();

        $roles     = $service->roles();
        $errors    = [];
        $old       = [];

        ViewRenderer::render('users/form', [
            'roles'      => $roles,
            'errors'     => $errors,
            'old'        => $old,
            'roleName'   => $identity['role_name'] ?? '',
            'activePage' => 'users',
        ], 'Create User');
    }

    // -----------------------------------------------------------------------
    // POST /users
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function store(array $params = []): void
    {
        [$service, $identity] = $this->setup();

        try {
            $service->create($_POST, (int) $identity['user_id']);
            ViewRenderer::flash('User account created successfully.');
            $this->redirect('/users');
        } catch (RuntimeException $e) {
            $roles     = $service->roles();
            $errors    = [$e->getMessage()];
            $old       = $_POST;

            http_response_code(422);
            ViewRenderer::render('users/form', [
                'roles'      => $roles,
                'errors'     => $errors,
                'old'        => $old,
                'roleName'   => $identity['role_name'] ?? '',
                'activePage' => 'users',
            ], 'Create User');
        }
    }

    // -----------------------------------------------------------------------
    // GET /users/{id}/edit
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function edit(array $params = []): void
    {
        [$service, $identity] = $this->setup();

        try {
            $userId = (int) ($params['id'] ?? 0);
            $user   = $service->findOrFail($userId);
        } catch (RuntimeException $e) {
            $this->notFound();
            return;
        }

        // HRHead may only edit Employee-role accounts.
        $actorRole = (string) ($identity['role_name'] ?? '');
        if ($actorRole === 'HRHead' && ($user['role_name'] ?? '') !== 'Employee') {
            ViewRenderer::flashError('HR Head may only edit Employee accounts.');
            $this->redirect('/users');
            return;
        }

        $roles = $service->roles();

        ViewRenderer::render('users/form', [
            'user'       => $user,
            'roles'      => $roles,
            'errors'     => [],
            'old'        => $user,
            'roleName'   => $identity['role_name'] ?? '',
            'activePage' => 'users',
        ], 'Edit User');
    }

    // -----------------------------------------------------------------------
    // POST /users/{id}  (_method=PUT)
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function update(array $params = []): void
    {
        [$service, $identity] = $this->setup();
        $userId    = (int) ($params['id'] ?? 0);
        $actorRole = (string) ($identity['role_name'] ?? '');

        try {
            $targetUser = $service->findOrFail($userId);
        } catch (RuntimeException) {
            $this->notFound();
            return;
        }

        // HRHead may only update Employee-role accounts.
        if ($actorRole === 'HRHead' && ($targetUser['role_name'] ?? '') !== 'Employee') {
            ViewRenderer::flashError('HR Head may only edit Employee accounts.');
            $this->redirect('/users');
            return;
        }

        // HRHead cannot change the role of any account.
        if ($actorRole === 'HRHead') {
            unset($_POST['role_id']);
        }

        try {
            $service->update($userId, $_POST, (int) $identity['user_id']);
            ViewRenderer::flash('User updated successfully.');
            $this->redirect('/users');
        } catch (RuntimeException $e) {
            try {
                $user = $service->findOrFail($userId);
            } catch (RuntimeException) {
                $this->notFound();
                return;
            }

            $roles     = $service->roles();
            $errors    = [$e->getMessage()];
            $old       = array_merge($user, $_POST);

            http_response_code(422);
            ViewRenderer::render('users/form', [
                'user'       => $user,
                'roles'      => $roles,
                'errors'     => $errors,
                'old'        => $old,
                'roleName'   => $actorRole,
                'activePage' => 'users',
            ], 'Edit User');
        }
    }

    // -----------------------------------------------------------------------
    // POST /users/{id}/toggle
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function toggle(array $params = []): void
    {
        [$service, $identity] = $this->setup();
        $userId = (int) ($params['id'] ?? 0);

        try {
            $newStatus = $service->toggleStatus($userId, (int) $identity['user_id']);
            $label     = $newStatus === 'Active' ? 'activated' : 'deactivated';
            ViewRenderer::flash("User {$label} successfully.");
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/users');
    }

    // -----------------------------------------------------------------------
    // POST /users/{id}/archive
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function archive(array $params = []): void
    {
        [$service, $identity] = $this->setup();
        $userId = (int) ($params['id'] ?? 0);

        try {
            $service->archive($userId, (int) $identity['user_id']);
            ViewRenderer::flash('User archived successfully.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/users');
    }

    // -----------------------------------------------------------------------
    // POST /users/{id}/unarchive
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function unarchive(array $params = []): void
    {
        [$service, $identity] = $this->setup();
        $userId = (int) ($params['id'] ?? 0);

        try {
            $service->unarchive($userId, (int) $identity['user_id']);
            ViewRenderer::flash('User unarchived. Account is now Inactive — activate it to restore access.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/users');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Boot shared dependencies and return them as a tuple.
     *
     * @return array{0:UserService, 1:array<string,mixed>}
     */
    private function setup(): array
    {
        $identity = AuthMiddleware::identity() ?? [];
        $config   = require APP_ROOT . '/config/database.php';
        $conn     = new Connection($config);
        $service  = new UserService($conn);

        return [$service, $identity];
    }

    private function pdo(): \PDO
    {
        $config = require APP_ROOT . '/config/database.php';
        return (new Connection($config))->pdo();
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    private function notFound(): void
    {
        http_response_code(404);
        ViewRenderer::render('errors/404', [], '404 Not Found');
    }
}
