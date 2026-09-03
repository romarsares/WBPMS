<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;

/** Persistence for reusable schedule templates and employee assignments. */
final class ScheduleRepository extends AbstractRepository
{
    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT ws.schedule_id AS id, ws.schedule_name, ws.working_days, ws.rest_days,
                    ws.work_start_time AS time_in, ws.work_end_time AS time_out,
                    ws.break_minutes, ws.grace_minutes, ws.overtime_allowed,
                    ws.standard_minutes, ws.effective_from, ws.effective_to,
                    LOWER(ws.status) AS status
             FROM work_schedule ws
             ORDER BY ws.schedule_name ASC, ws.work_start_time ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $scheduleId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT ws.schedule_id AS id, ws.schedule_name, ws.working_days, ws.rest_days,
                    ws.work_start_time AS time_in, ws.work_end_time AS time_out,
                    ws.break_minutes, ws.grace_minutes, ws.overtime_allowed,
                    ws.break_start_time, ws.break_end_time, ws.notes,
                    ws.standard_minutes, ws.effective_from, ws.effective_to,
                    LOWER(ws.status) AS status
             FROM work_schedule ws
             WHERE ws.schedule_id = :id"
        );
        $stmt->execute([':id' => $scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO work_schedule (
                schedule_name, working_days, rest_days, break_minutes, grace_minutes,
                overtime_allowed, break_start_time, break_end_time, notes,
                work_start_time, work_end_time, standard_minutes,
                effective_from, effective_to, status
             ) VALUES (
                :schedule_name, :working_days, :rest_days, :break_minutes, :grace_minutes,
                :overtime_allowed, :break_start_time, :break_end_time, :notes,
                :work_start_time, :work_end_time, :standard_minutes,
                :effective_from, :effective_to, :status
             )"
        );
        $stmt->execute([
            ':schedule_name' => $attributes['schedule_name'],
            ':working_days' => $attributes['working_days'],
            ':rest_days' => $attributes['rest_days'] ?? '["Saturday","Sunday"]',
            ':break_minutes' => (int) ($attributes['break_minutes'] ?? 60),
            ':grace_minutes' => (int) ($attributes['grace_minutes'] ?? 0),
            ':overtime_allowed' => (int) ($attributes['overtime_allowed'] ?? 1),
            ':break_start_time' => $attributes['break_start_time'] ?? null,
            ':break_end_time' => $attributes['break_end_time'] ?? null,
            ':notes' => $attributes['notes'] ?? null,
            ':work_start_time' => $attributes['work_start_time'],
            ':work_end_time' => $attributes['work_end_time'],
            ':standard_minutes' => (int) ($attributes['standard_minutes'] ?? 480),
            ':effective_from' => $attributes['effective_from'],
            ':effective_to' => $attributes['effective_to'] ?? null,
            ':status' => $attributes['status'] ?? 'Active',
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $attributes */
    public function update(int $scheduleId, array $attributes): void
    {
        $allowed = [
            'schedule_name', 'working_days', 'rest_days', 'work_start_time',
            'work_end_time', 'standard_minutes', 'break_minutes', 'grace_minutes',
            'overtime_allowed', 'break_start_time', 'break_end_time', 'notes',
            'effective_from', 'effective_to', 'status',
        ];
        $setClauses = [];
        $params = [':id' => $scheduleId];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $attributes)) {
                $setClauses[] = "{$field} = :{$field}";
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

    /** @return list<array{id: int, name: string}> */
    public function dropdownList(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT schedule_id AS id,
                    CONCAT(schedule_name, ' - ', work_start_time, '-', work_end_time) AS name
             FROM work_schedule
             WHERE status = 'Active'
             ORDER BY schedule_name ASC, work_start_time ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function findAllAssignments(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT esa.assignment_id AS id, esa.employee_id, esa.schedule_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number, ws.schedule_name,
                    ws.work_start_time AS time_in, ws.work_end_time AS time_out,
                    esa.effective_from, esa.effective_to, LOWER(esa.status) AS status, esa.notes
             FROM employee_schedule_assignment esa
             JOIN employee e ON e.employee_id = esa.employee_id
             JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
             ORDER BY e.last_name ASC, e.first_name ASC, esa.effective_from DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function findAssignmentsByEmployee(int $employeeId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT esa.assignment_id AS id, esa.schedule_id, ws.schedule_name,
                    ws.working_days, ws.rest_days, ws.work_start_time AS time_in,
                    ws.work_end_time AS time_out, ws.break_minutes, ws.standard_minutes,
                    esa.effective_from, esa.effective_to, LOWER(esa.status) AS status, esa.notes
             FROM employee_schedule_assignment esa
             JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
             WHERE esa.employee_id = :emp_id
             ORDER BY esa.effective_from DESC"
        );
        $stmt->execute([':emp_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $attributes */
    public function assign(array $attributes): int
    {
        $employeeId = (int) $attributes['employee_id'];
        $scheduleId = (int) $attributes['schedule_id'];
        $effectiveFrom = (string) $attributes['effective_from'];
        if ($this->findById($scheduleId) === null) {
            throw new \RuntimeException('Schedule template not found.');
        }

        return $this->transaction(function (PDO $pdo) use ($attributes, $employeeId, $scheduleId, $effectiveFrom): int {
            $current = $pdo->prepare(
                "SELECT assignment_id, effective_from
                   FROM employee_schedule_assignment
                  WHERE employee_id = :employee_id AND effective_to IS NULL AND status = 'Active'
                  ORDER BY effective_from DESC LIMIT 1 FOR UPDATE"
            );
            $current->execute([':employee_id' => $employeeId]);
            $existing = $current->fetch(PDO::FETCH_ASSOC);

            if ($existing !== false) {
                if ((string) $existing['effective_from'] > $effectiveFrom) {
                    throw new \RuntimeException('The new effective date cannot be before the current schedule assignment.');
                }
                if ((string) $existing['effective_from'] === $effectiveFrom) {
                    $pdo->prepare(
                        "UPDATE employee_schedule_assignment
                            SET schedule_id = :schedule_id, notes = :notes, updated_at = NOW()
                          WHERE assignment_id = :assignment_id"
                    )->execute([
                        ':schedule_id' => $scheduleId,
                        ':notes' => $attributes['notes'] ?? null,
                        ':assignment_id' => $existing['assignment_id'],
                    ]);
                    return (int) $existing['assignment_id'];
                }
                $pdo->prepare(
                    "UPDATE employee_schedule_assignment
                        SET effective_to = DATE_SUB(:effective_from, INTERVAL 1 DAY),
                            status = 'Archived', updated_at = NOW()
                      WHERE assignment_id = :assignment_id"
                )->execute([
                    ':effective_from' => $effectiveFrom,
                    ':assignment_id' => $existing['assignment_id'],
                ]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO employee_schedule_assignment
                    (employee_id, schedule_id, effective_from, effective_to, status, notes)
                 VALUES
                    (:employee_id, :schedule_id, :effective_from, :effective_to, 'Active', :notes)"
            );
            $stmt->execute([
                ':employee_id' => $employeeId,
                ':schedule_id' => $scheduleId,
                ':effective_from' => $effectiveFrom,
                ':effective_to' => $attributes['effective_to'] ?? null,
                ':notes' => $attributes['notes'] ?? null,
            ]);
            return (int) $pdo->lastInsertId();
        });
    }

    /** @return list<array{id: int, name: string, employee_number: string}> */
    public function employeeList(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT employee_id AS id, CONCAT(last_name, ', ', first_name) AS name, employee_number
             FROM employee WHERE status = 'Active' ORDER BY last_name ASC, first_name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function upcomingHolidays(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT holiday_id, holiday_date, COALESCE(description, '') AS holiday_name, holiday_type
             FROM holiday_calendar
             WHERE holiday_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status = 'Active'
             ORDER BY holiday_date ASC LIMIT 30"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function findAllHolidays(): array
    {
        return $this->pdo()->query(
            "SELECT holiday_id, holiday_date, description, holiday_type, pay_multiplier, status
             FROM holiday_calendar ORDER BY holiday_date DESC LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findHolidayById(int $holidayId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT holiday_id, holiday_date, description, holiday_type, pay_multiplier, status
             FROM holiday_calendar WHERE holiday_id = :id"
        );
        $stmt->execute([':id' => $holidayId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** @param array{holiday_date: string, description: string, holiday_type: string} $attrs */
    public function createHoliday(array $attrs): int
    {
        $multiplier = $attrs['holiday_type'] === 'Regular' ? '2.00' : '1.30';
        try {
            $stmt = $this->pdo()->prepare(
                "INSERT INTO holiday_calendar (holiday_date, description, holiday_type, pay_multiplier, status)
                 VALUES (:date, :desc, :type, :mult, 'Active')"
            );
            $stmt->execute([
                ':date' => $attrs['holiday_date'], ':desc' => $attrs['description'],
                ':type' => $attrs['holiday_type'], ':mult' => $multiplier,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                throw new \RuntimeException('A holiday already exists on ' . $attrs['holiday_date'] . '.');
            }
            throw $e;
        }
        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array{holiday_date: string, description: string, holiday_type: string, status: string} $attrs */
    public function updateHoliday(int $holidayId, array $attrs): void
    {
        $multiplier = $attrs['holiday_type'] === 'Regular' ? '2.00' : '1.30';
        try {
            $this->pdo()->prepare(
                "UPDATE holiday_calendar
                    SET holiday_date = :date, description = :desc, holiday_type = :type,
                        pay_multiplier = :mult, status = :status, updated_at = NOW()
                  WHERE holiday_id = :id"
            )->execute([
                ':date' => $attrs['holiday_date'], ':desc' => $attrs['description'],
                ':type' => $attrs['holiday_type'], ':mult' => $multiplier,
                ':status' => $attrs['status'], ':id' => $holidayId,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                throw new \RuntimeException('A holiday already exists on ' . $attrs['holiday_date'] . '.');
            }
            throw $e;
        }
    }

    public function deactivateHoliday(int $holidayId): void
    {
        $this->pdo()->prepare(
            "UPDATE holiday_calendar SET status = 'Inactive', updated_at = NOW() WHERE holiday_id = :id"
        )->execute([':id' => $holidayId]);
    }
}
