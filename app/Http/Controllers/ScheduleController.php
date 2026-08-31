<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class ScheduleController
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

        $schedules = $pdo->query(
            "SELECT schedule_id, schedule_name, time_in, time_out,
                    work_days, grace_period_minutes, status
               FROM work_schedule
              ORDER BY schedule_name"
        )->fetchAll();

        $holidays = $pdo->query(
            "SELECT holiday_id, holiday_name, holiday_date, holiday_type
               FROM holiday_calendar
              ORDER BY holiday_date DESC
              LIMIT 30"
        )->fetchAll();

        $total    = count($schedules);
        $active   = count(array_filter($schedules, fn($r) => $r['status'] === 'Active'));
        $hTotal   = (int) $pdo->query("SELECT COUNT(*) FROM holiday_calendar")->fetchColumn();
        $upcoming = (int) $pdo->query(
            "SELECT COUNT(*) FROM holiday_calendar WHERE holiday_date >= CURDATE()"
        )->fetchColumn();

        $title      = 'Work Schedule';
        $activePage = 'schedule';
        $notifCount = 0;

        ob_start();
        require APP_ROOT . '/resources/views/schedule/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
