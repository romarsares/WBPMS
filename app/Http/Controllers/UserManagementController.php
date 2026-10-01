<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * UserManagementController — legacy stub kept for any routes still pointing here.
 * New work should use UserController instead.
 */
final class UserManagementController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $identity = AuthMiddleware::identity() ?? [];
        $config   = require APP_ROOT . '/config/database.php';
        $pdo      = (new Connection($config))->pdo();

        $rows = $pdo->query(
            "SELECT u.user_id, u.username, u.account_email, u.status, u.created_at,
                    r.role_name,
                    CASE WHEN e.employee_id IS NOT NULL
                         THEN CONCAT(e.first_name, ' ', e.last_name)
                         ELSE NULL END AS employee_name
               FROM users u
               JOIN role r ON r.role_id = u.role_id
               LEFT JOIN employee e ON e.employee_id = u.employee_id
              ORDER BY u.created_at DESC"
        )->fetchAll();

        $total    = count($rows);
        $active   = count(array_filter($rows, fn($r) => $r['status'] === 'Active'));
        $inactive = count(array_filter($rows, fn($r) => $r['status'] === 'Inactive'));
        $archived = count(array_filter($rows, fn($r) => $r['status'] === 'Archived'));

        ViewRenderer::render('users/index', [
            'rows'           => $rows,
            'total'          => $total,
            'active'         => $active,
            'inactive'       => $inactive,
            'archived'       => $archived,
            'positions'      => [],
            'filterPosition' => '',
            'filterUsername' => '',
            'filterStatus'   => '',
            'currentUserId'  => (int) ($identity['user_id'] ?? 0),
            'activePage'     => 'users',
        ], 'User Management');
    }
}
