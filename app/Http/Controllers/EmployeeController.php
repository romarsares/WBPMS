<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeRepository;
use Wbpms\Infrastructure\Persistence\ScheduleRepository;

/**
 * EmployeeController — HR-only employee management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/employees          → index()   — list with filters
 *   GET  /hr/employees/new      → create()  — blank create form
 *   POST /hr/employees          → store()   — persist new employee
 *   GET  /hr/employees/{id}     → show()    — view detail (alias to edit form)
 *   GET  /hr/employees/{id}/edit → editForm() — edit form
 *   POST /hr/employees/{id}     → update()  — persist changes
 *
 * REQ009–REQ017 (P0 subset): list/create/update.
 * Transfer history, archive workflow, and search pagination are deferred.
 */
final class EmployeeController
{
    /**
     * GET /hr/employees
     *
     * @param array<string, string> $params
     */
    public function index(array $params = []): void
    {
        $repo     = $this->makeRepo();
        $search   = trim((string) ($_GET['search'] ?? ''));
        $branch   = (string) ($_GET['branch']  ?? '');
        $status   = (string) ($_GET['status']  ?? '');

        $employees = $repo->findAll([
            'search' => $search,
            'branch' => $branch,
            'status' => $status !== '' ? $status : 'active',
        ]);
        $branches = $repo->activeBranches();

        ViewRenderer::render('hr/employees/index', [
            'employees'    => $employees,
            'branches'     => $branches,
            'search'       => $search,
            'filterBranch' => $branch,
            'filterStatus' => $status !== '' ? $status : 'active',
        ], 'Employees');
    }

    /**
     * GET /hr/employees/new
     *
     * @param array<string, string> $params
     */
    public function create(array $params = []): void
    {
        [$branches, $schedules, $devices] = $this->dropdownData();

        ViewRenderer::render('hr/employees/edit', [
            'employee'  => null,
            'branches'  => $branches,
            'schedules' => $schedules,
            'devices'   => $devices,
            'errors'    => [],
        ], 'Add Employee');
    }

    /**
     * POST /hr/employees
     *
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $data   = $this->extractPostFields();
        $errors = $this->validate($data, false);

        if ($errors !== []) {
            [$branches, $schedules, $devices] = $this->dropdownData();
            ViewRenderer::render('hr/employees/edit', [
                'employee'  => null,
                'branches'  => $branches,
                'schedules' => $schedules,
                'devices'   => $devices,
                'errors'    => $errors,
            ], 'Add Employee');
            return;
        }

        $repo       = $this->makeRepo();
        $connection = $this->makeConnection();

        $connection->transaction(function () use ($data, $repo): void {
            $employeeId = $repo->createEmployee([
                'employee_number'  => $data['employee_number'],
                'employee_type'    => 'Regular',
                'first_name'       => $data['first_name'],
                'middle_initial'   => $data['middle_name'] !== '' ? mb_substr($data['middle_name'], 0, 5) : null,
                'last_name'        => $data['last_name'],
                'hire_date'        => $data['effective_from'],
                'position'         => 'Employee', // Default; full position field deferred
                'status'           => 'Active',
            ]);

            $repo->assignInitialBranch(
                $employeeId,
                (int) $data['branch_id'],
                $data['effective_from']
            );

            if ($data['device_id'] !== '' && $data['enrollment_code'] !== '') {
                $repo->enrollBiometricCode(
                    $employeeId,
                    (int) $data['device_id'],
                    $data['enrollment_code'],
                    $data['effective_from']
                );
            }
        });

        ViewRenderer::flash('Employee created successfully.');
        $this->redirect('/hr/employees');
    }

    /**
     * GET /hr/employees/{id}  (view/detail — redirects to edit)
     *
     * @param array<string, string> $params
     */
    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->redirect('/hr/employees/' . $id . '/edit');
    }

    /**
     * GET /hr/employees/{id}/edit
     *
     * @param array<string, string> $params
     */
    public function editForm(array $params = []): void
    {
        $id  = (int) ($params['id'] ?? 0);
        $row = $this->makeRepo()->findById($id);

        if ($row === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        [$branches, $schedules, $devices] = $this->dropdownData();

        ViewRenderer::render('hr/employees/edit', [
            'employee'  => $row,
            'branches'  => $branches,
            'schedules' => $schedules,
            'devices'   => $devices,
            'errors'    => [],
        ], 'Edit Employee');
    }

    /**
     * GET /hr/employees/{id}/transfer
     *
     * @param array<string, string> $params
     */
    public function transferForm(array $params = []): void
    {
        $id  = (int) ($params['id'] ?? 0);
        $row = $this->makeRepo()->findById($id);

        if ($row === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $branches = (new Connection(require APP_ROOT . '/config/database.php'))->pdo()->query(
            "SELECT branch_id AS id, branch_name AS name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('hr/employees/transfer', [
            'employee' => $row,
            'branches' => $branches,
            'errors'   => [],
        ], 'Transfer Employee');
    }

    /**
     * POST /hr/employees/{id}/transfer
     *
     * @param array<string, string> $params
     */
    public function transfer(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $repo     = $this->makeRepo();
        $row      = $repo->findById($id);

        if ($row === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $newBranchId  = (int) ($_POST['branch_id']     ?? 0);
        $transferDate = trim((string) ($_POST['transfer_date'] ?? ''));
        $errors       = [];

        if ($newBranchId <= 0) {
            $errors['branch_id'] = 'Destination branch is required.';
        }
        if ($transferDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transferDate)) {
            $errors['transfer_date'] = 'Transfer date is required (YYYY-MM-DD).';
        }

        if ($errors === []) {
            try {
                $repo->transferEmployee($id, $newBranchId, $transferDate);
                ViewRenderer::flash('Employee transferred successfully.');
                $this->redirect('/hr/employees/' . $id . '/edit');
                return;
            } catch (\RuntimeException $e) {
                $errors['transfer_date'] = $e->getMessage();
            }
        }

        $branches = (new Connection(require APP_ROOT . '/config/database.php'))->pdo()->query(
            "SELECT branch_id AS id, branch_name AS name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('hr/employees/transfer', [
            'employee' => $row,
            'branches' => $branches,
            'errors'   => $errors,
        ], 'Transfer Employee');
    }

    /**
     * POST /hr/employees/{id}  (with _method=PUT from the form)
     *
     * @param array<string, string> $params
     */
    public function update(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->makeRepo();
        $row  = $repo->findById($id);

        if ($row === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $data   = $this->extractPostFields();
        $errors = $this->validate($data, true);

        if ($errors !== []) {
            [$branches, $schedules, $devices] = $this->dropdownData();
            // Merge posted values back into the employee array for repopulation
            $merged = array_merge($row, $data, ['id' => $id]);
            ViewRenderer::render('hr/employees/edit', [
                'employee'  => $merged,
                'branches'  => $branches,
                'schedules' => $schedules,
                'devices'   => $devices,
                'errors'    => $errors,
            ], 'Edit Employee');
            return;
        }

        $repo->updateEmployee($id, [
            'first_name'     => $data['first_name'],
            'middle_initial' => $data['middle_name'] !== '' ? mb_substr($data['middle_name'], 0, 5) : null,
            'last_name'      => $data['last_name'],
            'status'         => ucfirst(strtolower($data['status'] ?? 'active')),
        ]);

        ViewRenderer::flash('Employee updated successfully.');
        $this->redirect('/hr/employees');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @return array{
     *   employee_number: string,
     *   first_name: string,
     *   middle_name: string,
     *   last_name: string,
     *   effective_from: string,
     *   branch_id: string,
     *   schedule_id: string,
     *   device_id: string,
     *   enrollment_code: string,
     *   status: string
     * }
     */
    private function extractPostFields(): array
    {
        return [
            'employee_number' => trim((string) ($_POST['employee_number'] ?? '')),
            'first_name'      => trim((string) ($_POST['first_name']      ?? '')),
            'middle_name'     => trim((string) ($_POST['middle_name']     ?? '')),
            'last_name'       => trim((string) ($_POST['last_name']       ?? '')),
            'effective_from'  => trim((string) ($_POST['effective_from']  ?? '')),
            'branch_id'       => trim((string) ($_POST['branch_id']       ?? '')),
            'schedule_id'     => trim((string) ($_POST['schedule_id']     ?? '')),
            'device_id'       => trim((string) ($_POST['device_id']       ?? '')),
            'enrollment_code' => trim((string) ($_POST['enrollment_code'] ?? '')),
            'status'          => trim((string) ($_POST['status']          ?? 'active')),
        ];
    }

    /**
     * @param  array<string, string> $data
     * @param  bool                  $isEdit  Skip immutable fields on update
     * @return array<string, string> Validation errors keyed by field name
     */
    private function validate(array $data, bool $isEdit): array
    {
        $errors = [];

        if (!$isEdit && $data['employee_number'] === '') {
            $errors['employee_number'] = 'Employee number is required.';
        }

        if ($data['first_name'] === '') {
            $errors['first_name'] = 'First name is required.';
        }

        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Last name is required.';
        }

        if (!$isEdit) {
            if ($data['effective_from'] === '') {
                $errors['effective_from'] = 'Effective date is required.';
            }
            if ($data['branch_id'] === '') {
                $errors['branch_id'] = 'Branch is required.';
            }
        }

        return $errors;
    }

    /**
     * Load dropdown data for branches, schedules, and devices.
     *
     * @return array{
     *   0: list<array{id: int, name: string}>,
     *   1: list<array{id: int, name: string}>,
     *   2: list<array{id: int, name: string}>
     * }
     */
    private function dropdownData(): array
    {
        $pdo = $this->makeConnection()->pdo();

        $branches = $pdo->query(
            "SELECT branch_id AS id, branch_name AS name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $schedules = $pdo->query(
            "SELECT ws.schedule_id AS id,
                    CONCAT(e.last_name,', ',e.first_name,' — ', ws.work_start_time,'–',ws.work_end_time) AS name
             FROM work_schedule ws
             JOIN employee e ON e.employee_id = ws.employee_id
             WHERE ws.status = 'Active'
             ORDER BY e.last_name, ws.work_start_time"
        )->fetchAll(PDO::FETCH_ASSOC);

        $devices = $pdo->query(
            "SELECT device_id AS id, COALESCE(device_name, device_code) AS name
             FROM biometric_device WHERE status = 'Active' ORDER BY device_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        return [$branches, $schedules, $devices];
    }

    private function makeRepo(): EmployeeRepository
    {
        return new EmployeeRepository($this->makeConnection());
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
