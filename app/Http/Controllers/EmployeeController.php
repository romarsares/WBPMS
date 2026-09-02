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
        [$branches, $schedules, $devices, $positions] = $this->dropdownData();

        ViewRenderer::render('hr/employees/edit', [
            'employee'  => null,
            'branches'  => $branches,
            'schedules' => $schedules,
            'devices'   => $devices,
            'positions' => $positions,
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
        $repo   = $this->makeRepo();
        $data   = $this->extractPostFields();
        $errors = $this->validate($data, false, $repo);

        if ($errors !== []) {
            [$branches, $schedules, $devices, $positions] = $this->dropdownData();
            ViewRenderer::render('hr/employees/edit', [
                'employee'  => null,
                'branches'  => $branches,
                'schedules' => $schedules,
                'devices'   => $devices,
                'positions' => $positions,
                'errors'    => $errors,
            ], 'Add Employee');
            return;
        }

        $connection = $this->makeConnection();

        try {
            $connection->transaction(function () use ($data, $repo): void {
            $employeeId = $repo->createEmployee([
                'employee_number'   => $data['employee_number'],
                'employee_type'     => $data['employee_type'] ?: 'Regular',
                'first_name'        => $data['first_name'],
                'middle_initial'    => $data['middle_name'] !== '' ? mb_substr($data['middle_name'], 0, 5) : null,
                'last_name'         => $data['last_name'],
                'email'             => $data['email']          !== '' ? $data['email']          : null,
                'contact_number'    => $data['contact_number'] !== '' ? $data['contact_number'] : null,
                'birthdate'         => $data['birthdate']      !== '' ? $data['birthdate']      : null,
                'hire_date'         => $data['effective_from'],
                'position'          => $data['position']       !== '' ? $data['position']       : 'Employee',
                'status'            => 'Active',
                'philhealth_number' => $data['philhealth_number'] !== '' ? $data['philhealth_number'] : null,
                'pagibig_number'    => $data['pagibig_number']    !== '' ? $data['pagibig_number']    : null,
                'tin_number'        => $data['tin_number']        !== '' ? $data['tin_number']        : null,
            ]);

            $repo->assignInitialBranch(
                $employeeId,
                (int) $data['branch_id'],
                $data['effective_from']
            );

            // Save daily rate in salary table if provided
            if ($data['daily_rate'] !== '' && (float) $data['daily_rate'] > 0) {
                $repo->createSalary($employeeId, (float) $data['daily_rate'], $data['effective_from']);
            }

            if ($data['device_id'] !== '' && $data['enrollment_code'] !== '') {
                $repo->enrollBiometricCode(
                    $employeeId,
                    (int) $data['device_id'],
                    $data['enrollment_code'],
                    $data['effective_from']
                );
            }
            });
        } catch (\PDOException $e) {
            $fieldErrors = $this->uniqueConstraintErrors($e);
            if ($fieldErrors === []) {
                throw $e;
            }

            [$branches, $schedules, $devices, $positions] = $this->dropdownData();
            ViewRenderer::render('hr/employees/edit', [
                'employee'  => null,
                'branches'  => $branches,
                'schedules' => $schedules,
                'devices'   => $devices,
                'positions' => $positions,
                'errors'    => $fieldErrors,
            ], 'Add Employee');
            return;
        }

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

        [$branches, $schedules, $devices, $positions] = $this->dropdownData();

        ViewRenderer::render('hr/employees/edit', [
            'employee'  => $row,
            'branches'  => $branches,
            'schedules' => $schedules,
            'devices'   => $devices,
            'positions' => $positions,
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
        $errors = $this->validate($data, true, $repo, $id);

        if ($errors !== []) {
            [$branches, $schedules, $devices, $positions] = $this->dropdownData();
            // Merge posted values back into the employee array for repopulation
            $merged = array_merge($row, $data, ['id' => $id]);
            ViewRenderer::render('hr/employees/edit', [
                'employee'  => $merged,
                'branches'  => $branches,
                'schedules' => $schedules,
                'devices'   => $devices,
                'positions' => $positions,
                'errors'    => $errors,
            ], 'Edit Employee');
            return;
        }

        $repo->updateEmployee($id, [
            'employee_type'     => $data['employee_type'] ?: 'Regular',
            'first_name'        => $data['first_name'],
            'middle_initial'    => $data['middle_name'] !== '' ? mb_substr($data['middle_name'], 0, 5) : null,
            'last_name'         => $data['last_name'],
            'email'             => $data['email']          !== '' ? $data['email']          : null,
            'contact_number'    => $data['contact_number'] !== '' ? $data['contact_number'] : null,
            'birthdate'         => $data['birthdate']      !== '' ? $data['birthdate']      : null,
            'position'          => $data['position']       !== '' ? $data['position']       : null,
            'philhealth_number' => $data['philhealth_number'] !== '' ? $data['philhealth_number'] : null,
            'pagibig_number'    => $data['pagibig_number']    !== '' ? $data['pagibig_number']    : null,
            'tin_number'        => $data['tin_number']        !== '' ? $data['tin_number']        : null,
            'status'            => ucfirst(strtolower($data['status'] ?? 'active')),
        ]);

        // Update daily rate if provided (insert new effective-dated salary row)
        if ($data['daily_rate'] !== '' && (float) $data['daily_rate'] > 0) {
            $repo->updateSalary($id, (float) $data['daily_rate']);
        }

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
            'employee_number'      => trim((string) ($_POST['employee_number']      ?? '')),
            'employee_type'        => trim((string) ($_POST['employee_type']        ?? 'Regular')),
            'first_name'           => trim((string) ($_POST['first_name']           ?? '')),
            'middle_name'          => trim((string) ($_POST['middle_name']          ?? '')),
            'last_name'            => trim((string) ($_POST['last_name']            ?? '')),
            'email'                => trim((string) ($_POST['email']                ?? '')),
            'contact_number'       => trim((string) ($_POST['contact_number']       ?? '')),
            'birthdate'            => trim((string) ($_POST['birthdate']            ?? '')),
            'position'             => trim((string) ($_POST['position']             ?? '')),
            'hire_date'            => trim((string) ($_POST['hire_date']            ?? '')),
            'effective_from'       => trim((string) ($_POST['effective_from']       ?? '')),
            'branch_id'            => trim((string) ($_POST['branch_id']            ?? '')),
            'schedule_id'          => trim((string) ($_POST['schedule_id']          ?? '')),
            'device_id'            => trim((string) ($_POST['device_id']            ?? '')),
            'enrollment_code'      => trim((string) ($_POST['enrollment_code']      ?? '')),
            'daily_rate'           => trim((string) ($_POST['daily_rate']           ?? '')),
            'philhealth_number'    => trim((string) ($_POST['philhealth_number']    ?? '')),
            'pagibig_number'       => trim((string) ($_POST['pagibig_number']       ?? '')),
            'tin_number'           => trim((string) ($_POST['tin_number']           ?? '')),
            'status'               => trim((string) ($_POST['status']               ?? 'active')),
        ];
    }

    /**
     * @param  array<string, string> $data
     * @param  bool                  $isEdit  Skip immutable fields on update
     * @return array<string, string> Validation errors keyed by field name
     */
    private function validate(
        array $data,
        bool $isEdit,
        ?EmployeeRepository $repo = null,
        ?int $currentEmployeeId = null
    ): array
    {
        $errors = [];

        if (!$isEdit && $data['employee_number'] === '') {
            $errors['employee_number'] = 'Employee number is required.';
        }

        if (!$isEdit && $data['employee_number'] !== '' && $repo !== null
            && $repo->employeeNumberExists($data['employee_number'])) {
            $errors['employee_number'] = 'Employee number ' . $data['employee_number'] . ' is already assigned. Use a different employee number.';
        }

        if ($data['email'] !== '' && $repo !== null
            && $repo->emailExists($data['email'], $currentEmployeeId)) {
            $errors['email'] = 'This email address is already assigned to another employee.';
        }

        if ($data['first_name'] === '') {
            $errors['first_name'] = 'First name is required.';
        }

        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Last name is required.';
        }

        if ($data['position'] === '') {
            $errors['position'] = 'Position is required.';
        }

        if ($data['daily_rate'] === '' || (float) $data['daily_rate'] <= 0) {
            $errors['daily_rate'] = 'Daily rate is required and must be greater than 0.';
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
     * Convert duplicate-key races into safe, field-level form errors.
     *
     * @return array<string, string>
     */
    private function uniqueConstraintErrors(\PDOException $exception): array
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        if ((string) $exception->getCode() !== '23000' || $driverCode !== 1062) {
            return [];
        }

        $message = $exception->getMessage();
        if (str_contains($message, 'uq_employee_number')) {
            return ['employee_number' => 'This employee number is already assigned. Use a different employee number.'];
        }
        if (str_contains($message, 'uq_employee_email')) {
            return ['email' => 'This email address is already assigned to another employee.'];
        }

        return [];
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
                    CONCAT(ws.schedule_name, ' — ', ws.work_start_time, '–', ws.work_end_time) AS name
             FROM work_schedule ws
             WHERE ws.status = 'Active'
             ORDER BY ws.schedule_name, ws.work_start_time"
        )->fetchAll(PDO::FETCH_ASSOC);

        $devices = $pdo->query(
            "SELECT device_id AS id, COALESCE(device_name, device_code) AS name
             FROM biometric_device WHERE status = 'Active' ORDER BY device_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $positions = $pdo->query(
            "SELECT position_id AS id, position_title AS name, department
               FROM job_position
              WHERE status = 'Active'
              ORDER BY sort_order ASC, position_title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return [$branches, $schedules, $devices, $positions];
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
