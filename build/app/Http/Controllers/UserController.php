<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use RuntimeException;
use Wbpms\Application\UserService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

/**
 * UserController — HTTP layer for User Management (Module 3).
 *
 * Routes:
 *   GET    /users                → index()        — list all users
 *   GET    /users/create         → create()       — show create form
 *   POST   /users                → store()        — persist new user
 *   GET    /users/{id}/edit      → edit()         — show edit form
 *   POST   /users/{id}  (_method=PUT)  → update() — persist edits
 *   POST   /users/{id}/toggle         → toggle()  — activate/deactivate
 *   POST   /users/{id}/archive        → archive() — archive
 *
 * REQ004–REQ008.  All mutations are role-guarded to BusinessOwner.
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
        [$service, $identity, $base] = $this->setup();

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
            $_SESSION['_flash'][] = ['error', 'HR Head may only reset passwords for Employee accounts.'];
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
                 record_id, description, action_at)
             VALUES
                (:uid, 'password_reset', 'password_reset', 'users',
                 :rid, :desc, NOW())"
        )->execute([
            ':uid'  => $actorId,
            ':rid'  => $userId,
            ':desc' => "Password reset by {$actorRole} (user_id={$actorId}) for user '{$user['username']}'",
        ]);

        \Wbpms\Http\View\ViewRenderer::render('users/reset-password-done', [
            'username'     => $user['username'],
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
        [$service, $identity, $base, $csrfField, $flash] = $this->setup();

        $rows     = $service->list(includeArchived: true);
        $total    = count($rows);
        $active   = count(array_filter($rows, fn($r) => $r['status'] === 'Active'));
        $inactive = count(array_filter($rows, fn($r) => $r['status'] === 'Inactive'));
        $archived = count(array_filter($rows, fn($r) => $r['status'] === 'Archived'));

        $title      = 'User Management';
        $activePage = 'users';
        $notifCount = 0;
        $displayName = $identity['display_name'] ?? $identity['username'];
        $roleName    = $identity['role_name'];

        ob_start();
        require APP_ROOT . '/resources/views/users/index.php';
        $content = ob_get_clean();

        $this->respond($content);
    }

    // -----------------------------------------------------------------------
    // GET /users/create
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function create(array $params = []): void
    {
        [$service, $identity, $base, $csrfField, $flash] = $this->setup();

        $roles     = $service->roles();
        $employees = $service->unlinkedEmployees();
        $errors    = [];

        $title      = 'Create User';
        $activePage = 'users';
        $notifCount = 0;
        $displayName = $identity['display_name'] ?? $identity['username'];
        $roleName    = $identity['role_name'];

        ob_start();
        require APP_ROOT . '/resources/views/users/form.php';
        $content = ob_get_clean();

        $this->respond($content);
    }

    // -----------------------------------------------------------------------
    // POST /users
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function store(array $params = []): void
    {
        [$service, $identity, $base, $csrfField, $flash] = $this->setup();

        try {
            $userId = $service->create($_POST, (int) $identity['user_id']);
            $_SESSION['_flash'][] = ['success', 'User account created successfully.'];
            $this->redirect('/users');
        } catch (RuntimeException $e) {
            // Re-render form with error and preserved input
            $roles     = $service->roles();
            $employees = $service->unlinkedEmployees();
            $errors    = [$e->getMessage()];
            $old       = $_POST;

            $title      = 'Create User';
            $activePage = 'users';
            $notifCount = 0;
            $displayName = $identity['display_name'] ?? $identity['username'];
            $roleName    = $identity['role_name'];

            ob_start();
            require APP_ROOT . '/resources/views/users/form.php';
            $content = ob_get_clean();

            http_response_code(422);
            $this->respond($content);
        }
    }

    // -----------------------------------------------------------------------
    // GET /users/{id}/edit
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function edit(array $params = []): void
    {
        [$service, $identity, $base, $csrfField, $flash] = $this->setup();

        try {
            $userId = (int) ($params['id'] ?? 0);
            $user   = $service->findOrFail($userId);
        } catch (RuntimeException $e) {
            $this->notFound();
            return;
        }

        $roles     = $service->roles();
        $employees = $service->unlinkedEmployees();
        // Include the user's current employee link so it appears in the list
        if ($user['employee_id'] !== null) {
            $stmt = $this->pdo()->prepare(
                "SELECT e.employee_id,
                        CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                        e.employee_number
                   FROM employee e WHERE e.employee_id = :id"
            );
            $stmt->execute([':id' => $user['employee_id']]);
            $current = $stmt->fetch();
            if ($current !== false) {
                array_unshift($employees, $current);
            }
        }

        $errors = [];
        $old    = $user;    // pre-populate form from existing record

        $title      = 'Edit User';
        $activePage = 'users';
        $notifCount = 0;
        $displayName = $identity['display_name'] ?? $identity['username'];
        $roleName    = $identity['role_name'];

        ob_start();
        require APP_ROOT . '/resources/views/users/form.php';
        $content = ob_get_clean();

        $this->respond($content);
    }

    // -----------------------------------------------------------------------
    // POST /users/{id}  (_method=PUT)
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function update(array $params = []): void
    {
        [$service, $identity, $base, $csrfField, $flash] = $this->setup();
        $userId = (int) ($params['id'] ?? 0);

        try {
            $service->update($userId, $_POST, (int) $identity['user_id']);
            $_SESSION['_flash'][] = ['success', 'User updated successfully.'];
            $this->redirect('/users');
        } catch (RuntimeException $e) {
            try {
                $user = $service->findOrFail($userId);
            } catch (RuntimeException) {
                $this->notFound();
                return;
            }

            $roles     = $service->roles();
            $employees = $service->unlinkedEmployees();
            $errors    = [$e->getMessage()];
            $old       = array_merge($user, $_POST);

            $title      = 'Edit User';
            $activePage = 'users';
            $notifCount = 0;
            $displayName = $identity['display_name'] ?? $identity['username'];
            $roleName    = $identity['role_name'];

            ob_start();
            require APP_ROOT . '/resources/views/users/form.php';
            $content = ob_get_clean();

            http_response_code(422);
            $this->respond($content);
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
            $_SESSION['_flash'][] = ['success', "User {$label} successfully."];
        } catch (RuntimeException $e) {
            $_SESSION['_flash'][] = ['error', $e->getMessage()];
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
            $_SESSION['_flash'][] = ['success', 'User archived successfully.'];
        } catch (RuntimeException $e) {
            $_SESSION['_flash'][] = ['error', $e->getMessage()];
        }

        $this->redirect('/users');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Boot shared dependencies and return them as a tuple.
     *
     * @return array{0:UserService, 1:array<string,mixed>, 2:string, 3:string, 4:array<mixed>}
     */
    private function setup(): array
    {
        $identity  = AuthMiddleware::identity() ?? [];
        $config    = require APP_ROOT . '/config/database.php';
        $conn      = new Connection($config);
        $service   = new UserService($conn);
        $base      = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        $csrfField = CsrfMiddleware::field();
        $flash     = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return [$service, $identity, $base, $csrfField, $flash];
    }

    private function pdo(): \PDO
    {
        $config = require APP_ROOT . '/config/database.php';
        return (new Connection($config))->pdo();
    }

    private function respond(string $content): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        $title   = '404 Not Found';
        $content = '<div class="page-head"><div><h1>User Not Found</h1><p>The requested user account does not exist.</p></div></div>';
        $base    = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
