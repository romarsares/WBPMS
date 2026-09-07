<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;
use Wbpms\Application\EmployeeSetup\EmployeeSetupGateway;
use Wbpms\Infrastructure\Database\Connection;

/**
 * EmployeeRepository — PDO persistence for the Employee module.
 *
 * Implements EmployeeSetupGateway (B2 contract) and exposes additional
 * read methods required by EmployeeController.
 *
 * Tasks 4.1–4.3 (P0 subset): list/create/update; no transfer history or archive.
 *
 * ADR-0003: all SQL lives here, never in controllers or services.
 */
final class EmployeeRepository extends AbstractRepository implements EmployeeSetupGateway
{
    // -----------------------------------------------------------------------
    // EmployeeSetupGateway implementation
    // -----------------------------------------------------------------------

    /**
     * Insert a new employee record and return the generated employee_id.
     *
     * Required attributes:
     *   employee_number (string), employee_type (Regular|Contractual),
     *   first_name (string), last_name (string), hire_date (Y-m-d),
     *   position (string), status (Active|Inactive|Separated|Archived)
     *
     * Optional: middle_initial, email, contact_number, birthdate,
     *           philhealth_number, pagibig_number, tin_number,
     *           contract_review_date
     *
     * @param  array<string, mixed> $attributes
     * @return int  New employee_id
     */
    public function createEmployee(array $attributes): int
    {
        $sql = <<<'SQL'
            INSERT INTO employee (
                employee_number, employee_type,
                first_name, middle_initial, last_name,
                email, contact_number, birthdate, hire_date,
                position, status,
                sss_number, philhealth_number, pagibig_number, tin_number,
                contract_review_date
            ) VALUES (
                :employee_number, :employee_type,
                :first_name, :middle_initial, :last_name,
                :email, :contact_number, :birthdate, :hire_date,
                :position, :status,
                :sss_number, :philhealth_number, :pagibig_number, :tin_number,
                :contract_review_date
            )
        SQL;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':employee_number'     => $attributes['employee_number'],
            ':employee_type'       => $attributes['employee_type']   ?? 'Regular',
            ':first_name'          => $attributes['first_name'],
            ':middle_initial'      => $attributes['middle_initial']   ?? null,
            ':last_name'           => $attributes['last_name'],
            ':email'               => $attributes['email']            ?? null,
            ':contact_number'      => $attributes['contact_number']   ?? null,
            ':birthdate'           => $attributes['birthdate']        ?? null,
            ':hire_date'           => $attributes['hire_date'],
            ':position'            => $attributes['position'],
            ':status'              => $attributes['status']           ?? 'Active',
            ':sss_number'          => $attributes['sss_number']        ?? null,
            ':philhealth_number'   => $attributes['philhealth_number'] ?? null,
            ':pagibig_number'      => $attributes['pagibig_number']   ?? null,
            ':tin_number'          => $attributes['tin_number']       ?? null,
            ':contract_review_date'=> $attributes['contract_review_date'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Open an initial branch assignment for a newly created employee.
     * Uses the half-open [from, to) period; effective_to is NULL (current).
     */
    public function assignInitialBranch(int $employeeId, int $branchId, string $effectiveFrom): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO employee_branch_assignment
                (employee_id, branch_id, effective_from, effective_to)
             VALUES (:emp_id, :branch_id, :from, NULL)'
        );
        $stmt->execute([
            ':emp_id'    => $employeeId,
            ':branch_id' => $branchId,
            ':from'      => $effectiveFrom,
        ]);
    }

    /**
     * Create an effective work schedule for an employee.
     *
     * $scheduleId is the schedule_id of an existing work_schedule row.
     * The effective_from date becomes the start of the schedule's validity.
     *
     * NOTE: This method creates a new assignment link; it does not create the
     * schedule itself. For P0 the schedule row is created by ScheduleRepository.
     * This method exists for the EmployeeSetupGateway contract compatibility.
     * In the P0 flow the schedule is linked at creation time via createEmployee()
     * combined with the schedule_id attribute.
     */
    public function assignEffectiveSchedule(int $employeeId, int $scheduleId, string $effectiveFrom): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO employee_schedule_assignment
                (employee_id, schedule_id, effective_from, effective_to, status)
             VALUES (:employee_id, :schedule_id, :effective_from, NULL, :status)'
        );
        $stmt->execute([
            ':employee_id'    => $employeeId,
            ':schedule_id'    => $scheduleId,
            ':effective_from' => $effectiveFrom,
            ':status'         => 'Active',
        ]);
    }

    /**
     * Register a biometric enrollment code for an employee on a specific device.
     */
    public function enrollBiometricCode(int $employeeId, int $deviceId, string $enrollmentCode, string $effectiveFrom): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO employee_biometric_enrollment
                (employee_id, device_id, device_employee_code, effective_from, effective_to, status)
             VALUES (:emp_id, :dev_id, :code, :from, NULL, :status)'
        );
        $stmt->execute([
            ':emp_id' => $employeeId,
            ':dev_id' => $deviceId,
            ':code'   => $enrollmentCode,
            ':from'   => $effectiveFrom,
            ':status' => 'Active',
        ]);
    }

    /**
     * Return whether an employee number is already assigned.
     */
    public function employeeNumberExists(string $employeeNumber): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1 FROM employee WHERE employee_number = :employee_number LIMIT 1'
        );
        $stmt->execute([':employee_number' => $employeeNumber]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Return whether an email is used by another employee.
     */
    public function emailExists(string $email, ?int $excludeEmployeeId = null): bool
    {
        $sql = 'SELECT 1 FROM employee WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeEmployeeId !== null) {
            $sql .= ' AND employee_id != :employee_id';
            $params[':employee_id'] = $excludeEmployeeId;
        }

        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    // -----------------------------------------------------------------------
    // Read methods for EmployeeController
    // -----------------------------------------------------------------------

    /**
     * Return a paginated list of employees with their current branch and schedule.
     *
     * Applies optional search (name / employee_number), branch filter, and
     * status filter.  Returns rows matching the shape expected by the index view.
     *
     * @param  array{search?: string, branch?: string|int, status?: string} $filters
     * @return list<array{
     *   id: int,
     *   employee_number: string,
     *   last_name: string,
     *   first_name: string,
     *   branch_name: string,
     *   schedule_name: string,
     *   status: string,
     *   effective_from: string
     * }>
     */
    public function findAll(array $filters = []): array
    {
        $params = [];
        $where  = [];

        // Status filter
        $status = $filters['status'] ?? '';
        if ($status !== '') {
            $where[]          = 'e.status = :status';
            $params[':status'] = ucfirst(strtolower($status));
        }

        // Branch filter (by branch_id)
        $branchId = (string) ($filters['branch'] ?? '');
        if ($branchId !== '') {
            $where[]           = 'eba.branch_id = :branch_id';
            $params[':branch_id'] = (int) $branchId;
        }

        // Search by name or employee number
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[]              = '(e.last_name LIKE :search_ln OR e.first_name LIKE :search_fn OR e.employee_number LIKE :search_en)';
            $params[':search_ln'] = '%' . $search . '%';
            $params[':search_fn'] = '%' . $search . '%';
            $params[':search_en'] = '%' . $search . '%';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = <<<SQL
            SELECT
                e.employee_id              AS id,
                e.employee_number,
                e.last_name,
                e.first_name,
                COALESCE(b.branch_name, '—')     AS branch_name,
                COALESCE(
                    CONCAT(ws.work_start_time, ' – ', ws.work_end_time),
                    '—'
                )                                AS schedule_name,
                LOWER(e.status)                  AS status,
                COALESCE(eba.effective_from, e.hire_date) AS effective_from
            FROM employee e
            LEFT JOIN employee_branch_assignment eba
                   ON eba.employee_id = e.employee_id
                  AND eba.effective_to IS NULL
            LEFT JOIN branch b ON b.branch_id = eba.branch_id
            LEFT JOIN employee_schedule_assignment esa
                   ON esa.employee_id = e.employee_id
                  AND esa.effective_to IS NULL
                  AND esa.status = 'Active'
            LEFT JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
            {$whereClause}
            ORDER BY e.last_name ASC, e.first_name ASC
            LIMIT 200
        SQL;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a single employee by ID with branch and schedule info.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $employeeId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                e.employee_id              AS id,
                e.employee_number,
                e.employee_type,
                e.first_name,
                e.middle_initial,
                e.middle_initial           AS middle_name,
                e.last_name,
                e.email,
                e.contact_number,
                e.birthdate,
                e.hire_date,
                e.hire_date                AS effective_from,
                e.position,
                LOWER(e.status)            AS status,
                e.philhealth_number,
                e.pagibig_number,
                e.tin_number,
                e.contract_review_date,
                COALESCE(b.branch_id, 0)   AS branch_id,
                COALESCE(b.branch_name,'—') AS branch_name,
                COALESCE(esa.schedule_id, 0) AS schedule_id,
                COALESCE(s.daily_rate, 0)  AS daily_rate,
                COALESCE(ebe.device_id, 0) AS device_id,
                COALESCE(ebe.device_employee_code, '') AS enrollment_code
             FROM employee e
             LEFT JOIN employee_branch_assignment eba
                    ON eba.employee_id = e.employee_id
                   AND eba.effective_to IS NULL
             LEFT JOIN branch b ON b.branch_id = eba.branch_id
             LEFT JOIN employee_schedule_assignment esa
                    ON esa.employee_id = e.employee_id
                   AND esa.effective_to IS NULL
                   AND esa.status = 'Active'
             LEFT JOIN salary s
                    ON s.employee_id = e.employee_id
                   AND s.effective_to IS NULL
                   AND s.status = 'Active'
             LEFT JOIN employee_biometric_enrollment ebe
                    ON ebe.employee_id = e.employee_id
                   AND ebe.effective_to IS NULL
                   AND ebe.status = 'Active'
             WHERE e.employee_id = :id"
        );
        $stmt->execute([':id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Update mutable fields of an existing employee record.
     *
     * @param int                   $employeeId
     * @param array<string, mixed>  $attributes  Partial list of updatable fields
     */
    public function updateEmployee(int $employeeId, array $attributes): void
    {
        $allowed = [
            'employee_type', 'first_name', 'middle_initial', 'last_name',
            'email', 'contact_number', 'birthdate', 'hire_date', 'position',
            'status', 'sss_number', 'philhealth_number', 'pagibig_number',
            'tin_number', 'contract_review_date',
        ];

        $setClauses = [];
        $params     = [':id' => $employeeId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $attributes)) {
                $setClauses[]      = "{$field} = :{$field}";
                $params[":{$field}"] = $attributes[$field];
            }
        }

        if ($setClauses === []) {
            return; // Nothing to update
        }

        $sql  = 'UPDATE employee SET ' . implode(', ', $setClauses) . ' WHERE employee_id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Insert a new salary row for an employee (effective-dated).
     */
    public function createSalary(int $employeeId, float $dailyRate, string $effectiveFrom): void
    {
        $this->pdo()->prepare(
            "INSERT INTO salary (employee_id, daily_rate, effective_from, effective_to, status, created_at, updated_at)
             VALUES (:emp_id, :rate, :from, NULL, 'Active', NOW(), NOW())"
        )->execute([
            ':emp_id' => $employeeId,
            ':rate'   => $dailyRate,
            ':from'   => $effectiveFrom,
        ]);
    }

    /**
     * Update the current active salary (archive old row, insert new one today).
     */
    public function updateSalary(int $employeeId, float $dailyRate): void
    {
        $today = date('Y-m-d');

        // Archive existing active salary
        $this->pdo()->prepare(
            "UPDATE salary SET effective_to = :today, status = 'Archived', updated_at = NOW()
              WHERE employee_id = :emp_id AND effective_to IS NULL AND status = 'Active'"
        )->execute([':today' => $today, ':emp_id' => $employeeId]);

        // Insert new rate effective today
        $this->createSalary($employeeId, $dailyRate, $today);
    }

    /**
     * Transfer an employee to a new branch.
     *
     * Closes the current active branch assignment (effective_to = transfer_date - 1 day)
     * and opens a new one (effective_from = transfer_date). The operation is transactional.
     *
     * ADR-0002: branch assignments must not overlap; the half-open [from, to) period is enforced.
     *
     * @param int    $employeeId   Target employee
     * @param int    $newBranchId  Destination branch
     * @param string $transferDate YYYY-MM-DD — the first day on the new branch
     * @throws \RuntimeException   when no active assignment exists, branch unchanged,
     *                             or transfer date would create an overlap
     */
    public function transferEmployee(int $employeeId, int $newBranchId, string $transferDate): void
    {
        $this->connection->transaction(function (\PDO $pdo) use ($employeeId, $newBranchId, $transferDate): void {
            // Fetch the current open assignment with a write lock
            $stmt = $pdo->prepare(
                "SELECT branch_assignment_id, branch_id, effective_from
                   FROM employee_branch_assignment
                  WHERE employee_id   = :emp
                    AND effective_to  IS NULL
                  FOR UPDATE"
            );
            $stmt->execute([':emp' => $employeeId]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($current === false) {
                throw new \RuntimeException('No active branch assignment found for this employee.');
            }

            if ((int) $current['branch_id'] === $newBranchId) {
                throw new \RuntimeException('The employee is already assigned to that branch.');
            }

            if ($transferDate <= $current['effective_from']) {
                throw new \RuntimeException(
                    'Transfer date must be after the current assignment start date ('
                    . $current['effective_from'] . ').'
                );
            }

            // Check for any future closed assignment that would overlap
            $overlap = $pdo->prepare(
                "SELECT COUNT(*) FROM employee_branch_assignment
                  WHERE employee_id    = :emp
                    AND effective_from >= :date
                    AND effective_to   IS NOT NULL"
            );
            $overlap->execute([':emp' => $employeeId, ':date' => $transferDate]);
            if ((int) $overlap->fetchColumn() > 0) {
                throw new \RuntimeException(
                    'A future branch assignment already exists; resolve it before transferring.'
                );
            }

            // Close the current assignment one day before transfer date
            $closeDate = (new \DateTimeImmutable($transferDate))->modify('-1 day')->format('Y-m-d');
            $pdo->prepare(
                "UPDATE employee_branch_assignment
                    SET effective_to = :close,
                        updated_at   = NOW()
                  WHERE branch_assignment_id = :id"
            )->execute([':close' => $closeDate, ':id' => $current['branch_assignment_id']]);

            // Open the new assignment
            $pdo->prepare(
                "INSERT INTO employee_branch_assignment
                    (employee_id, branch_id, effective_from, effective_to)
                 VALUES (:emp, :branch, :from, NULL)"
            )->execute([
                ':emp'    => $employeeId,
                ':branch' => $newBranchId,
                ':from'   => $transferDate,
            ]);
        });
    }

    /**
     * Return all active branches for use in dropdown filters.
     *
     * @return list<array{id: int, name: string}>
     */
    public function activeBranches(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT branch_id AS id, branch_name AS name
             FROM branch
             WHERE status = 'Active'
             ORDER BY branch_name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // Detail-view read methods
    // -----------------------------------------------------------------------

    /**
     * Return a rich employee row for the read-only detail view.
     *
     * Includes linked user account, current schedule name, work hours,
     * current daily rate, and current biometric enrollment.
     *
     * @return array<string, mixed>|null
     */
    public function findDetail(int $employeeId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                e.employee_id                                   AS id,
                e.employee_number,
                e.employee_type,
                e.first_name,
                e.middle_initial,
                e.last_name,
                e.email,
                e.contact_number,
                e.birthdate,
                e.hire_date,
                e.position,
                e.status,
                e.philhealth_number,
                e.pagibig_number,
                e.tin_number,
                e.contract_review_date,
                e.created_at,
                -- Current branch
                COALESCE(b.branch_name, '—')                    AS branch_name,
                COALESCE(eba.effective_from, '—')               AS branch_since,
                -- Current schedule
                COALESCE(ws.schedule_name, '—')                 AS schedule_name,
                COALESCE(ws.work_start_time, '')                AS work_start_time,
                COALESCE(ws.work_end_time, '')                  AS work_end_time,
                COALESCE(ws.standard_minutes, 0)                AS standard_minutes,
                COALESCE(ws.break_minutes, 0)                   AS break_minutes,
                -- Current salary
                COALESCE(s.daily_rate, 0)                       AS daily_rate,
                s.effective_from                                AS salary_since,
                -- Biometric enrollment
                COALESCE(bd.device_name, bd.device_code, '—')  AS device_name,
                COALESCE(ebe.device_employee_code, '—')         AS enrollment_code,
                -- Linked user account
                u.user_id,
                u.username,
                u.status                                        AS account_status,
                u.requires_password_change
             FROM employee e
             LEFT JOIN employee_branch_assignment eba
                    ON eba.employee_id = e.employee_id
                   AND eba.effective_to IS NULL
             LEFT JOIN branch b ON b.branch_id = eba.branch_id
             LEFT JOIN employee_schedule_assignment esa
                    ON esa.employee_id = e.employee_id
                   AND esa.effective_to IS NULL
                   AND esa.status = 'Active'
             LEFT JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
             LEFT JOIN salary s
                    ON s.employee_id = e.employee_id
                   AND s.effective_to IS NULL
                   AND s.status = 'Active'
             LEFT JOIN employee_biometric_enrollment ebe
                    ON ebe.employee_id = e.employee_id
                   AND ebe.effective_to IS NULL
                   AND ebe.status = 'Active'
             LEFT JOIN biometric_device bd ON bd.device_id = ebe.device_id
             LEFT JOIN users u ON u.employee_id = e.employee_id
             WHERE e.employee_id = :id"
        );
        $stmt->execute([':id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Return the full branch assignment history for an employee, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function branchHistory(int $employeeId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                eba.effective_from,
                eba.effective_to,
                b.branch_name,
                eba.transfer_reason
             FROM employee_branch_assignment eba
             LEFT JOIN branch b ON b.branch_id = eba.branch_id
             WHERE eba.employee_id = :id
             ORDER BY eba.effective_from DESC"
        );
        $stmt->execute([':id' => $employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return the full salary history for an employee, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function salaryHistory(int $employeeId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                s.daily_rate,
                s.effective_from,
                s.effective_to,
                s.status
             FROM salary s
             WHERE s.employee_id = :id
             ORDER BY s.effective_from DESC"
        );
        $stmt->execute([':id' => $employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
