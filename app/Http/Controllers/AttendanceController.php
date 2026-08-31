<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class AttendanceController
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

        // Summary stats
        $total = (int) $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
        $complete = (int) $pdo->query(
            "SELECT COUNT(*) FROM attendance WHERE time_in IS NOT NULL AND time_out IS NOT NULL"
        )->fetchColumn();
        $incomplete = $total - $complete;
        $unmatched  = (int) $pdo->query(
            "SELECT COUNT(*) FROM biometric_punch WHERE employee_id IS NULL"
        )->fetchColumn();

        // Recent attendance rows
        $rows = $pdo->query(
            "SELECT a.attendance_id, e.employee_number,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    b.branch_name,
                    a.attendance_date, a.time_in, a.time_out,
                    a.worked_minutes, a.late_minutes, a.undertime_minutes,
                    a.overtime_minutes, a.is_incomplete
               FROM attendance a
               JOIN employee e ON e.employee_id = a.employee_id
               LEFT JOIN employee_branch_assignment eba
                      ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
              ORDER BY a.attendance_date DESC, e.last_name
              LIMIT 200"
        )->fetchAll();

        $title      = 'Attendance Management';
        $activePage = 'attendance';
        $notifCount = $incomplete;

        ob_start();
        require APP_ROOT . '/resources/views/attendance/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
