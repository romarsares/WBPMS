<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class RequestsController
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
            "SELECT r.request_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    rt.type_name,
                    r.status,
                    r.submitted_at,
                    r.remarks
               FROM request r
               JOIN employee e  ON e.employee_id  = r.employee_id
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
              ORDER BY r.submitted_at DESC
              LIMIT 200"
        )->fetchAll();

        $total    = count($rows);
        $pending  = count(array_filter($rows, fn($r) => $r['status'] === 'Pending'));
        $approved = count(array_filter($rows, fn($r) => $r['status'] === 'Approved'));
        $rejected = count(array_filter($rows, fn($r) => $r['status'] === 'Rejected'));

        $title      = 'Request Management';
        $activePage = 'requests';
        $notifCount = $pending;

        ob_start();
        require APP_ROOT . '/resources/views/requests/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
