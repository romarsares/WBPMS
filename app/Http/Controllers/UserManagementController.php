<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class UserManagementController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $identity    = AuthMiddleware::identity();
        $displayName = $identity['display_name'] ?? ($identity['username'] ?? '');
        $roleName    = $identity['role_name'] ?? '';
        $base        = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        $csrfField   = CsrfMiddleware::field();
        $flash       = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        $config = require APP_ROOT . '/config/database.php';
        $pdo    = (new Connection($config))->pdo();

        $rows = $pdo->query(
            "SELECT u.user_id, u.username, u.account_email, u.status, u.created_at,
                    r.role_name
               FROM users u
               JOIN role r ON r.role_id = u.role_id
              ORDER BY u.created_at DESC"
        )->fetchAll();

        $total    = count($rows);
        $active   = count(array_filter($rows, fn($r) => $r['status'] === 'Active'));
        $inactive = count(array_filter($rows, fn($r) => $r['status'] === 'Inactive'));
        $archived = count(array_filter($rows, fn($r) => $r['status'] === 'Archived'));

        $title      = 'User Management';
        $activePage = 'users';
        $notifCount = 0;

        ob_start();
        require APP_ROOT . '/resources/views/users/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
