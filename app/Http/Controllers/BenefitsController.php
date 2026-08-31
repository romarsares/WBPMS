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

        // Canonical schema v1.1 contribution_policy_version columns:
        //   contribution_policy_id, policy_code, version, effective_from,
        //   effective_to, status ENUM('Draft','Approved','Retired')
        // — policy_version_id and 'program' do NOT exist; policy_code holds the program name.

        $policies = $pdo->query(
            "SELECT cpv.contribution_policy_id,
                    cpv.policy_code    AS program,
                    cpv.version,
                    cpv.effective_from,
                    cpv.effective_to,
                    cpv.status
               FROM contribution_policy_version cpv
              ORDER BY cpv.policy_code, cpv.effective_from DESC"
        )->fetchAll();

        // Canonical schema v1.1 contribution_record columns:
        //   contribution_id, payroll_id, deduction_id, contribution_policy_id,
        //   contribution_type ENUM('SSS','PhilHealth','PagIBIG'),
        //   eemr_basis, employee_share, employer_share, deduction_date,
        //   calculation_details, status, locked_at, locked_by
        // contribution_record links to payroll (not directly to employee).
        // Join payroll → employee to get employee details.
        // period_label does not exist — derive from payroll_period via payroll.

        $records = $pdo->query(
            "SELECT cr.contribution_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    cr.contribution_type AS program,
                    cr.employee_share,
                    cr.employer_share,
                    cr.deduction_date,
                    cr.status,
                    pp.period_start,
                    pp.period_end
               FROM contribution_record cr
               JOIN payroll p           ON p.payroll_id            = cr.payroll_id
               JOIN employee e          ON e.employee_id            = p.employee_id
               JOIN payroll_run pr      ON pr.payroll_run_id        = p.payroll_run_id
               JOIN payroll_period pp   ON pp.payroll_period_id     = pr.payroll_period_id
               JOIN contribution_policy_version cpv
                                        ON cpv.contribution_policy_id = cr.contribution_policy_id
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
