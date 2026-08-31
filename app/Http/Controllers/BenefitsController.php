<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class BenefitsController
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

        // Contribution policy versions
        $policies = $pdo->query(
            "SELECT cpv.policy_version_id, cpv.program, cpv.effective_from,
                    cpv.effective_to, cpv.approved_by, cpv.status
               FROM contribution_policy_version cpv
              ORDER BY cpv.program, cpv.effective_from DESC"
        )->fetchAll();

        // Recent contribution records
        $records = $pdo->query(
            "SELECT cr.contribution_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    cpv.program,
                    cr.employee_share, cr.employer_share,
                    cr.period_label,
                    cr.status
               FROM contribution_record cr
               JOIN employee e ON e.employee_id = cr.employee_id
               JOIN contribution_policy_version cpv ON cpv.policy_version_id = cr.policy_version_id
              ORDER BY cr.created_at DESC
              LIMIT 100"
        )->fetchAll();

        $totalPolicies = count($policies);
        $active        = count(array_filter($policies, fn($r) => $r['status'] === 'Approved'));
        $totalRecords  = count($records);
        $programs      = count(array_unique(array_column($policies, 'program')));

        $title      = 'Benefits & Deductions';
        $activePage = 'benefits';
        $notifCount = 0;

        ob_start();
        require APP_ROOT . '/resources/views/benefits/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
