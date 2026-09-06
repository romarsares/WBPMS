<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use RuntimeException;
use Wbpms\Application\SalaryService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * SalaryController — HR salary structure management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/salary                → index()   — list with filters
 *   GET  /hr/salary/create          → create()  — new salary form
 *   POST /hr/salary                → store()   — persist new salary
 *   GET  /hr/salary/{id}/edit      → edit()    — edit form (shows history)
 *   POST /hr/salary/{id}           → update()  — insert new rate row
 *   POST /hr/salary/{id}/archive   → archive() — soft-archive
 *
 * REQ053–REQ057.
 */
final class SalaryController
{
    // -----------------------------------------------------------------------
    // GET /hr/salary
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $service = $this->makeService();

        $filters = [
            'search'    => trim((string) ($_GET['search'] ?? '')),
            'branch_id' => (int) ($_GET['branch_id'] ?? 0),
            'status'    => trim((string) ($_GET['status'] ?? 'Active')),
        ];
        if ($filters['branch_id'] === 0) {
            unset($filters['branch_id']);
        }

        $rows    = $service->list($filters);
        $total   = count($rows);
        $active  = count(array_filter($rows, fn($r) => $r['status'] === 'Active'));
        $rates   = array_map(fn($r) => (float) $r['daily_rate'], $rows);
        $avgRate = $total > 0 ? array_sum($rates) / $total : 0.0;
        $maxRate = $total > 0 ? max($rates) : 0.0;

        // Fetch branches for filter dropdown
        $branches = $this->makeConnection()->pdo()->query(
            "SELECT branch_id, branch_name FROM branch WHERE status='Active' ORDER BY branch_name"
        )->fetchAll();

        ViewRenderer::render('hr/salary/index', [
            'rows'     => $rows,
            'filters'  => $filters,
            'branches' => $branches,
            'total'    => $total,
            'active'   => $active,
            'avgRate'  => $avgRate,
            'maxRate'  => $maxRate,
        ], 'Salary Management');
    }

    // -----------------------------------------------------------------------
    // GET /hr/salary/create
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        $employees = $this->makeService()->employeeList();

        ViewRenderer::render('hr/salary/form', [
            'salary'    => null,
            'employees' => $employees,
            'errors'    => [],
            'formTitle' => 'Add Salary Record',
            'formAction'=> '/hr/salary',
        ], 'Add Salary Record');
    }

    // -----------------------------------------------------------------------
    // POST /hr/salary
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        $service  = $this->makeService();
        $identity = AuthMiddleware::identity();
        $data     = $this->extractPostFields();

        try {
            $service->create($data, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Salary record created successfully.');
            $this->redirect('/hr/salary');
        } catch (RuntimeException $e) {
            $employees = $service->employeeList();
            ViewRenderer::render('hr/salary/form', [
                'salary'    => null,
                'employees' => $employees,
                'errors'    => [$e->getMessage()],
                'formTitle' => 'Add Salary Record',
                'formAction'=> '/hr/salary',
                'old'       => $data,
            ], 'Add Salary Record');
        }
    }

    // -----------------------------------------------------------------------
    // GET /hr/salary/{id}/edit
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $service = $this->makeService();

        try {
            $salary = $service->findOrFail($id);
        } catch (RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $employees = $service->employeeList();

        // Salary history for this employee
        $history = $this->makeConnection()->pdo()->prepare(
            "SELECT salary_id, daily_rate, effective_from, effective_to, status
               FROM salary WHERE employee_id = :emp ORDER BY effective_from DESC"
        );
        $history->execute([':emp' => $salary['employee_id']]);
        $historyRows = $history->fetchAll();

        ViewRenderer::render('hr/salary/form', [
            'salary'     => $salary,
            'employees'  => $employees,
            'history'    => $historyRows,
            'errors'     => [],
            'formTitle'  => 'Update Salary Rate',
            'formAction' => '/hr/salary/' . $id,
        ], 'Edit Salary');
    }

    // -----------------------------------------------------------------------
    // POST /hr/salary/{id}
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $service  = $this->makeService();
        $identity = AuthMiddleware::identity();
        $data     = $this->extractPostFields();

        try {
            $service->update($id, $data, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Salary rate updated. Previous record preserved in history.');
            $this->redirect('/hr/salary');
        } catch (RuntimeException $e) {
            try {
                $salary = $service->findOrFail($id);
            } catch (RuntimeException) {
                $salary = null;
            }
            $employees = $service->employeeList();
            ViewRenderer::render('hr/salary/form', [
                'salary'     => $salary,
                'employees'  => $employees,
                'errors'     => [$e->getMessage()],
                'formTitle'  => 'Update Salary Rate',
                'formAction' => '/hr/salary/' . $id,
                'old'        => $data,
            ], 'Edit Salary');
        }
    }

    // -----------------------------------------------------------------------
    // POST /hr/salary/{id}/archive
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function archive(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeService()->archive($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Salary record archived.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/salary');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** @return array{employee_id: string, daily_rate: string, effective_from: string} */
    private function extractPostFields(): array
    {
        return [
            'employee_id'   => trim((string) ($_POST['employee_id']    ?? '')),
            'daily_rate'    => trim((string) ($_POST['daily_rate']     ?? '')),
            'effective_from'=> trim((string) ($_POST['effective_from'] ?? '')),
        ];
    }

    private function makeService(): SalaryService
    {
        return new SalaryService($this->makeConnection());
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
