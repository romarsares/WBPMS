<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class EmployeeController
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
            "SELECT e.employee_id, e.employee_number, e.first_name, e.middle_initial,
                    e.last_name, e.position, e.employee_type, e.status, e.hire_date,
                    b.branch_name,
                    s.daily_rate
               FROM employee e
               LEFT JOIN employee_branch_assignment eba
                      ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
               LEFT JOIN salary s
                      ON s.employee_id = e.employee_id AND s.status = 'Active'
              ORDER BY e.last_name, e.first_name"
        )->fetchAll();

        $total       = count($rows);
        $active      = count(array_filter($rows, fn($r) => $r['status'] === 'Active'));
        $regular     = count(array_filter($rows, fn($r) => $r['employee_type'] === 'Regular'));
        $contractual = count(array_filter($rows, fn($r) => $r['employee_type'] === 'Contractual'));

        $title      = 'Employee Management';
        $activePage = 'employees';
        $notifCount = 0;

        ob_start();
        require APP_ROOT . '/resources/views/employees/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
