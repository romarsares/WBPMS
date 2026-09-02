<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;

/**
 * ScheduleRepository — PDO persistence for work_schedule and
 * employee_schedule_assignment.
 *
 * work_schedule is now a reusable template (no employee_id column).
 * employee_schedule_assignment links employees to schedules with
 * effective dates.
 *
 * ADR-0003: all SQL lives here.
 */
final class ScheduleRepository extends AbstractRepository
{
    // =========================================================================
    // work_schedule — reusable schedule templates
    // =========================================================================

    /**
     * Return all work schedule templates ordered by name.
     *
     * @return list<array{
     *   id: int,
     *   schedule_name: string,
     *   working_days: string,
     *   rest_days: string,
     *   time_in: string,
     *   time_out: string,
     *   break_minutes: int,
     *   grace_minutes: int,
     *   overtime_allowed: bool,
     *   standard_minutes: int,
     *   effective_from: string,
     *   status: string
     * }>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT
                schedule_id       AS id,
                schedule_name,
                working_days,
                rest_days,
                work_start_time   AS time_in,
                work_end_time     AS time_out,
                break_minutes,
                grace_minutes,
                overtime_allowed,
                standard_minutes,
                effective_from,
                LOWER(status)     AS status
             FROM work_schedule
             ORDER BY schedule_name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a single schedule template by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $scheduleId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                schedule_id       AS id,
                schedule_name,
                working_days,
                rest_days,
                work_start_time   AS time_in,
                work_end_time     AS time_out,
                break_minutes,
                grace_minutes,
                overtime_allowed,
                standard_minutes,
                break_start_time,
                break_end_time,
                notes,
                effective_from,
                effective_to,
                LOWER(status)     AS status
             FROM work_schedule
             WHERE schedule_id = :id"
        );
        $stmt->execute([':id' => $scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a new reusable work schedule template.
     *
     * Required attributes:
     *   schedule_name (string), working_days (JSON), rest_days (JSON),
     *   work_start_time ('HH:mm'), work_end_time ('HH:mm'),
     *   standard_minutes (int), effective_from (Y-m-d)
     *
     * Optional:
     *   break_minutes (int, default 60), grace_minutes (int, default 0),
     *   overtime_allowed (bool, default true), break_start_time, break_end_time,
     *   notes, status ('Active'|'Archived', default 'Active')
     *
     * Returns the generated schedule_id.
     *
     * @param  array<string, mixed> $attributes
     */
    public function create(array $attributes): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO work_schedule (
                schedule_name, working_days, rest_days,
                break_minutes, grace_minutes, overtime_allowed,
                work_start_time, work_end_time, standard_minutes,
                break_start_time, break_end_time, notes,
                effective_from, effective_to, status
             ) VALUES (
                :schedule_name, :working_days, :rest_days,
                :break_minutes, :grace_minutes, :overtime_allowed,
                :work_start_time, :work_end_time, :standard_minutes,
                :break_start_time, :break_end_time, :notes,
                :effective_from, :effective_to, :status
             )'
        );

        $stmt->execute([
            ':schedule_name'    => $attributes['schedule_name'],
            ':working_days'     => $attributes['working_days'],
            ':rest_days'        => $attributes['rest_days']         ?? '["Saturday","Sunday"]',
            ':break_minutes'    => (int) ($attributes['break_minutes']   ?? 60),
            ':grace_minutes'    => (int) ($attributes['grace_minutes']   ?? 0),
            ':overtime_allowed' => (int) ($attributes['overtime_allowed'] ?? 1),
            ':work_start_time'  => $attributes['work_start_time'],
            ':work_end_time'    => $attributes['work_end_time'],
            ':standard_minutes' => (int) ($attributes['standard_minutes'] ?? 480),
            ':break_start_time' => $attributes['break_start_time']  ?? null,
            ':break_end_time'   => $attributes['break_end_time']    ?? null,
            ':notes'            => $attributes['notes']             ?? null,
            ':effective_from'   => $attributes['effective_from'],
            ':effective_to'     => $attributes['effective_to']      ?? null,
            ':status'           => $attributes['status']            ?? 'Active',
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Update a work schedule template.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(int $scheduleId, array $attributes): void
    {
        $allowed = [
            'schedule_name', 'working_days', 'rest_days',
            'work_start_time', 'work_end_time', 'standard_minutes',
            'break_minutes', 'grace_minutes', 'overtime_allowed',
            'break_start_time', 'break_end_time', 'notes',
            'effective_from', 'effective_to', 'status',
        ];

        $setClauses = [];
        $params     = [':id' => $scheduleId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $attributes)) {
                $setClauses[]        = "{$field} = :{$field}";
                $params[":{$field}"] = $attributes[$field];
            }
        }

        if ($setClauses === []) {
            return;
        }

        $this->pdo()->prepare(
            'UPDATE work_schedule SET ' . implode(', ', $setClauses) . ' WHERE schedule_id = :id'
        )->execute($params);
    }

    /**
     * Return schedules suitable for a dropdown (id + label).
     *
     * @return list<array{id: int, name: string}>
     */
    public function dropdownList(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT schedule_id AS id, schedule_name AS name
             FROM work_schedule
             WHERE status = 'Active'
             ORDER BY schedule_name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // employee_schedule_assignment
    // =========================================================================

    /**
     * Return all assignments for a given employee, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function findAssignmentsByEmployee(int $employeeId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                esa.assignment_id,
                esa.employee_id,
                CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                esa.schedule_id,
                ws.schedule_name,
                ws.work_start_time AS time_in,
                ws.work_end_time   AS time_out,
                esa.effective_from,
                esa.effective_to,
                LOWER(esa.status)  AS status
             FROM employee_schedule_assignment esa
             JOIN employee     e  ON e.employee_id  = esa.employee_id
             JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
             WHERE esa.employee_id = :emp_id
             ORDER BY esa.effective_from DESC"
        );
        $stmt->execute([':emp_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return all assignments (all employees), for the assignment list view.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllAssignments(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT
                esa.assignment_id,
                CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                e.employee_number,
                ws.schedule_name,
                ws.work_start_time AS time_in,
                ws.work_end_time   AS time_out,
                esa.effective_from,
                esa.effective_to,
                LOWER(esa.status)  AS status
             FROM employee_schedule_assignment esa
             JOIN employee      e  ON e.employee_id  = esa.employee_id
             JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
             ORDER BY e.last_name ASC, esa.effective_from DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign a work schedule to an employee.
     *
     * Closes any open (effective_to IS NULL) assignment for the same employee
     * before inserting the new one — ensuring only one active assignment at a
     * time per employee.
     *
     * @param  array<string, mixed> $attributes  employee_id, schedule_id, effective_from, [effective_to], [notes]
     */
    public function assign(array $attributes): int
    {
        $employeeId   = (int) $attributes['employee_id'];
        $scheduleId   = (int) $attributes['schedule_id'];
        $effectiveFrom = $attributes['effective_from'];

        // Close any open current assignment one day before the new one starts
        $this->pdo()->prepare(
            "UPDATE employee_schedule_assignment
             SET effective_to = DATE_SUB(:new_from, INTERVAL 1 DAY),
                 updated_at   = NOW()
             WHERE employee_id   = :emp_id
               AND effective_to IS NULL
               AND status        = 'Active'"
        )->execute([':new_from' => $effectiveFrom, ':emp_id' => $employeeId]);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO employee_schedule_assignment
                (employee_id, schedule_id, effective_from, effective_to, status, notes)
             VALUES
                (:employee_id, :schedule_id, :effective_from, :effective_to, :status, :notes)'
        );

        $stmt->execute([
            ':employee_id'   => $employeeId,
            ':schedule_id'   => $scheduleId,
            ':effective_from' => $effectiveFrom,
            ':effective_to'  => $attributes['effective_to'] ?? null,
            ':status'        => 'Active',
            ':notes'         => $attributes['notes'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Return a list of active employees for a dropdown.
     *
     * @return list<array{id: int, name: string, employee_number: string}>
     */
    public function employeeList(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT employee_id AS id,
                    CONCAT(last_name, ', ', first_name) AS name,
                    employee_number
             FROM employee
             WHERE status = 'Active'
             ORDER BY last_name ASC, first_name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return holidays for the schedule page (upcoming and past 30 days).
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingHolidays(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT holiday_id, holiday_date,
                    COALESCE(description, '') AS holiday_name,
                    holiday_type
             FROM holiday_calendar
             WHERE holiday_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND status = 'Active'
             ORDER BY holiday_date ASC
             LIMIT 30"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // holiday_calendar CRUD
    // =========================================================================

    /**
     * Return all holidays, newest first, for the management list.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllHolidays(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT holiday_id, holiday_date, description, holiday_type, pay_multiplier, status
               FROM holiday_calendar
              ORDER BY holiday_date DESC
              LIMIT 200"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a single holiday by ID, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findHolidayById(int $holidayId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT holiday_id, holiday_date, description, holiday_type, pay_multiplier, status
               FROM holiday_calendar
              WHERE holiday_id = :id"
        );
        $stmt->execute([':id' => $holidayId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a holiday entry.
     * pay_multiplier is derived from type (Regular=2.00, Special=1.30) per ADR-0001.
     *
     * @param array{holiday_date: string, description: string, holiday_type: string} $attrs
     * @throws \RuntimeException on duplicate date
     */
    public function createHoliday(array $attrs): int
    {
        $multiplier = $attrs['holiday_type'] === 'Regular' ? '2.00' : '1.30';
        try {
            $stmt = $this->pdo()->prepare(
                "INSERT INTO holiday_calendar
                    (holiday_date, description, holiday_type, pay_multiplier, status)
                 VALUES
                    (:date, :desc, :type, :mult, 'Active')"
            );
            $stmt->execute([
                ':date' => $attrs['holiday_date'],
                ':desc' => $attrs['description'],
                ':type' => $attrs['holiday_type'],
                ':mult' => $multiplier,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                throw new \RuntimeException('A holiday already exists on ' . $attrs['holiday_date'] . '.');
            }
            throw $e;
        }
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Update a holiday entry.
     *
     * @param array{holiday_date: string, description: string, holiday_type: string, status: string} $attrs
     */
    public function updateHoliday(int $holidayId, array $attrs): void
    {
        $multiplier = $attrs['holiday_type'] === 'Regular' ? '2.00' : '1.30';
        try {
            $this->pdo()->prepare(
                "UPDATE holiday_calendar
                    SET holiday_date   = :date,
                        description    = :desc,
                        holiday_type   = :type,
                        pay_multiplier = :mult,
                        status         = :status,
                        updated_at     = NOW()
                  WHERE holiday_id     = :id"
            )->execute([
                ':date'   => $attrs['holiday_date'],
                ':desc'   => $attrs['description'],
                ':type'   => $attrs['holiday_type'],
                ':mult'   => $multiplier,
                ':status' => $attrs['status'],
                ':id'     => $holidayId,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                throw new \RuntimeException('A holiday already exists on ' . $attrs['holiday_date'] . '.');
            }
            throw $e;
        }
    }

    /**
     * Soft-deactivate (soft delete) a holiday by setting status = 'Inactive'.
     */
    public function deactivateHoliday(int $holidayId): void
    {
        $this->pdo()->prepare(
            "UPDATE holiday_calendar SET status = 'Inactive', updated_at = NOW() WHERE holiday_id = :id"
        )->execute([':id' => $holidayId]);
    }
}
