<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use RuntimeException;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * BenefitsController — HR contribution policy and record management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/benefits                               → index()         — overview
 *   GET  /hr/benefits/policies                      → policies()      — policy versions list
 *   POST /hr/benefits/policies/{id}/approve         → approvePolicy() — approve a Draft policy
 *
 * REQ058–REQ062.
 */
final class BenefitsController
{
    // -----------------------------------------------------------------------
    // GET /hr/benefits  — overview
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

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

        // -----------------------------------------------------------------------
        // Contribution record filters (GET parameters)
        // -----------------------------------------------------------------------
        $validPrograms = ['SSS', 'PhilHealth', 'PagIBIG'];

        $filterProgram = trim((string) ($_GET['program'] ?? ''));
        $filterSearch  = trim((string) ($_GET['employee_search'] ?? ''));
        $filterFrom    = trim((string) ($_GET['date_from'] ?? ''));
        $filterTo      = trim((string) ($_GET['date_to']   ?? ''));

        // Validate / sanitise
        if (!in_array($filterProgram, $validPrograms, true)) {
            $filterProgram = '';
        }
        $parsedFrom = $filterFrom !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $filterFrom) : false;
        $parsedTo   = $filterTo   !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $filterTo)   : false;
        if ($parsedFrom === false) {
            $filterFrom = '';
        }
        if ($parsedTo === false) {
            $filterTo = '';
        }

        // Build parameterised WHERE clause
        $where = [];
        $bind  = [];

        if ($filterProgram !== '') {
            $where[]             = 'cr.contribution_type = :program';
            $bind[':program']    = $filterProgram;
        }
        if ($filterSearch !== '') {
            $pattern = '%' . $filterSearch . '%';
            $where[] = '(e.last_name LIKE :search_last OR e.first_name LIKE :search_first OR e.employee_number LIKE :search_num)';
            $bind[':search_last']  = $pattern;
            $bind[':search_first'] = $pattern;
            $bind[':search_num']   = $pattern;
        }
        if ($filterFrom !== '') {
            $where[]          = 'pp.period_start >= :date_from';
            $bind[':date_from'] = $filterFrom;
        }
        if ($filterTo !== '') {
            $where[]        = 'pp.period_end <= :date_to';
            $bind[':date_to'] = $filterTo;
        }

        $whereClause = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $pdo->prepare(
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
               JOIN payroll p           ON p.payroll_id              = cr.payroll_id
               JOIN employee e          ON e.employee_id              = p.employee_id
               JOIN payroll_run pr      ON pr.payroll_run_id          = p.payroll_run_id
               JOIN payroll_period pp   ON pp.payroll_period_id       = pr.payroll_period_id
              {$whereClause}
              ORDER BY cr.created_at DESC
              LIMIT 200"
        );
        $stmt->execute($bind);
        $records = $stmt->fetchAll();

        $totalPolicies = count($policies);
        $active        = count(array_filter($policies, fn($r) => $r['status'] === 'Approved'));
        $totalRecords  = count($records);
        $programs      = count(array_unique(array_column($policies, 'program')));

        $filters = [
            'program'         => $filterProgram,
            'employee_search' => $filterSearch,
            'date_from'       => $filterFrom,
            'date_to'         => $filterTo,
        ];
        $isFiltered = array_filter($filters) !== [];

        ViewRenderer::render('hr/benefits/index', [
            'policies'      => $policies,
            'records'       => $records,
            'totalPolicies' => $totalPolicies,
            'active'        => $active,
            'totalRecords'  => $totalRecords,
            'programs'      => $programs,
            'filters'       => $filters,
            'isFiltered'    => $isFiltered,
        ], 'Benefits & Deductions');
    }

    // -----------------------------------------------------------------------
    // GET /hr/benefits/policies
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function policies(array $params = []): void
    {
        $pdo      = $this->makeConnection()->pdo();
        $policies = $pdo->query(
            "SELECT contribution_policy_id, policy_code, version,
                    effective_from, effective_to, status, created_at
               FROM contribution_policy_version
              ORDER BY policy_code, effective_from DESC"
        )->fetchAll();

        ViewRenderer::render('hr/benefits/policies', [
            'policies' => $policies,
        ], 'Contribution Policies');
    }

    // -----------------------------------------------------------------------
    // POST /hr/benefits/policies/{id}/approve
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function approvePolicy(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();
        $pdo      = $this->makeConnection()->pdo();

        try {
            $stmt = $pdo->prepare(
                "SELECT status, policy_code FROM contribution_policy_version WHERE contribution_policy_id = :id"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                throw new RuntimeException('Contribution policy not found.');
            }
            if ($row['status'] !== 'Draft') {
                throw new RuntimeException('Only Draft policies can be approved.');
            }

            $pdo->prepare(
                "UPDATE contribution_policy_version
                    SET status = 'Approved', updated_at = NOW()
                  WHERE contribution_policy_id = :id"
            )->execute([':id' => $id]);

            ViewRenderer::flash('Contribution policy approved.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/benefits/policies');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
