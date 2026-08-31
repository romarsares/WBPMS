<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;

/**
 * ScheduleRepository — PDO persistence for work_schedule.
 *
 * P0 subset of task 5.1–5.2: list, find, create.
 * Full schedule administration (overlap validation, holiday CRUD, archive) is
 * deferred beyond P0.
 *
 * ADR-0003: all SQL lives here.
 */
final class ScheduleRepository extends AbstractRepository
{
    /**
     * Return all work schedules (active and archived) ordered by employee then date.
     *
     * The view expects rows with the keys listed below.
     *
     * @return list<array{
     *   id: int,
     *   employee_id: int,
     *   employee_name: string,
     *   name: string,
     *   work_days: string,
     *   time_in: string,
     *   time_out: string,
     *   grace_minutes: int,
     *   effective_from: string,
     *   status: string
     * }>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT
                ws.schedule_id                              AS id,
                ws.employee_id,
                CONCAT(e.last_name, ', ', e.first_name)    AS employee_name,
                CONCAT(ws.work_start_time, ' – ', ws.work_end_time) AS name,
                ws.working_days                             AS work_days,
                ws.work_start_time                          AS time_in,
                ws.work_end_time                            AS time_out,
                0                                           AS grace_minutes,
                ws.effective_from,
                LOWER(ws.status)                            AS status
             FROM work_schedule ws
             JOIN employee e ON e.employee_id = ws.employee_id
             ORDER BY e.last_name ASC, ws.effective_from DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a single work_schedule row by its primary key.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $scheduleId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT ws.*,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name
             FROM work_schedule ws
             JOIN employee e ON e.employee_id = ws.employee_id
             WHERE ws.schedule_id = :id"
        );
        $stmt->execute([':id' => $scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new work_schedule row for an employee.
     *
     * Required attributes:
     *   employee_id (int), working_days (JSON string e.g. '["Monday","Tuesday"]'),
     *   rest_days (JSON string), work_start_time ('HH:mm:ss'),
     *   work_end_time ('HH:mm:ss'), standard_minutes (int),
     *   effective_from (Y-m-d)
     *
     * Optional:
     *   break_minutes (int, default 60), status (Active|Archived, default Active),
     *   effective_to (Y-m-d|null)
     *
     * Returns the generated schedule_id.
     *
     * @param  array<string, mixed> $attributes
     * @return int
     */
    public function create(array $attributes): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO work_schedule (
                employee_id, working_days, rest_days,
                break_minutes, work_start_time, work_end_time,
                standard_minutes, effective_from, effective_to, status
             ) VALUES (
                :employee_id, :working_days, :rest_days,
                :break_minutes, :work_start_time, :work_end_time,
                :standard_minutes, :effective_from, :effective_to, :status
             )'
        );

        $stmt->execute([
            ':employee_id'      => (int) $attributes['employee_id'],
            ':working_days'     => $attributes['working_days'],
            ':rest_days'        => $attributes['rest_days']        ?? '[]',
            ':break_minutes'    => (int) ($attributes['break_minutes'] ?? 60),
            ':work_start_time'  => $attributes['work_start_time'],
            ':work_end_time'    => $attributes['work_end_time'],
            ':standard_minutes' => (int) ($attributes['standard_minutes'] ?? 480),
            ':effective_from'   => $attributes['effective_from'],
            ':effective_to'     => $attributes['effective_to'] ?? null,
            ':status'           => $attributes['status']        ?? 'Active',
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Return a list of employees suitable for a dropdown selector.
     *
     * @return list<array{id: int, name: string}>
     */
    public function employeeList(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT employee_id AS id,
                    CONCAT(last_name, ', ', first_name) AS name
             FROM employee
             WHERE status = 'Active'
             ORDER BY last_name ASC, first_name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
