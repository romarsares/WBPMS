<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class SalaryController
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
            "SELECT s.salary_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    b.branch_name,
                    s.daily_rate,
                    s.effective_from,
                    s.status
               FROM salary s
               JOIN employee e ON e.employee_id = s.employee_id
               LEFT JOIN employee_branch_assignment eba
                      ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
              ORDER BY e.last_name, s.effective_from DESC
              LIMIT 300"
        )->fetchAll();

        $total   = count($rows);
        $active  = count(array_filter($rows, fn($r) => $r['status'] === 'Active'));
        $avgRate = $total > 0
            ? array_sum(array_column($rows, 'daily_rate')) / $total
            : 0;
        $maxRate = $total > 0 ? max(array_column($rows, 'daily_rate')) : 0;

        $title      = 'Salary Management';
        $activePage = 'salary';
        $notifCount = 0;

        ob_start();
        require APP_ROOT . '/resources/views/salary/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
