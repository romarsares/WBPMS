<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;

/** SQL persistence for controlled employee archive and rehire operations. */
final class EmployeeLifecycleRepository extends AbstractRepository
{
    /** @return array<string,mixed>|null */
    public function employeeForUpdate(int $employeeId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT employee_id, status, hire_date FROM employee WHERE employee_id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<string> */
    public function archiveBlockers(int $employeeId): array
    {
        $pdo = $this->pdo();
        $blockers = [];

        $checks = [
            'unapproved payroll' => "SELECT COUNT(*) FROM payroll p JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id WHERE p.employee_id = :id AND pr.status IN ('Draft', 'Computed', 'PendingOwnerApproval')",
            'pending request' => "SELECT COUNT(*) FROM request WHERE employee_id = :id AND status = 'Pending'",
            'open cash advance' => "SELECT COUNT(*) FROM cash_advance_history WHERE employee_id = :id AND status = 'Active' AND remaining_balance > 0",
        ];
        foreach ($checks as $label => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $employeeId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $blockers[] = $label;
            }
        }

        return $blockers;
    }

    /** @return list<string> */
    public function archiveStartDateConflicts(int $employeeId, string $archiveEffectiveDate): array
    {
        $tables = [
            'branch assignment' => 'employee_branch_assignment',
            'work schedule' => 'employee_schedule_assignment',
            'salary rate' => 'salary',
            'biometric enrollment' => 'employee_biometric_enrollment',
        ];
        $conflicts = [];
        foreach ($tables as $label => $table) {
            $stmt = $this->pdo()->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE employee_id = :id AND effective_to IS NULL AND effective_from >= :date"
            );
            $stmt->execute([':id' => $employeeId, ':date' => $archiveEffectiveDate]);
            if ((int) $stmt->fetchColumn() > 0) {
                $conflicts[] = $label;
            }
        }

        return $conflicts;
    }

    public function closeActiveRelationships(int $employeeId, string $archiveEffectiveDate): void
    {
        $pdo = $this->pdo();
        $statements = [
            "UPDATE employee_branch_assignment SET effective_to = :date WHERE employee_id = :id AND effective_to IS NULL",
            "UPDATE employee_schedule_assignment SET effective_to = :date, status = 'Archived', updated_at = NOW() WHERE employee_id = :id AND effective_to IS NULL AND status = 'Active'",
            "UPDATE salary SET effective_to = :date, status = 'Archived', updated_at = NOW() WHERE employee_id = :id AND effective_to IS NULL AND status = 'Active'",
            "UPDATE employee_biometric_enrollment SET effective_to = :date, status = 'Inactive', updated_at = NOW() WHERE employee_id = :id AND effective_to IS NULL AND status = 'Active'",
        ];
        foreach ($statements as $sql) {
            $pdo->prepare($sql)->execute([':id' => $employeeId, ':date' => $archiveEffectiveDate]);
        }
    }

    public function setEmployeeStatus(int $employeeId, string $status): void
    {
        $this->pdo()->prepare('UPDATE employee SET status = :status, updated_at = NOW() WHERE employee_id = :id')
            ->execute([':id' => $employeeId, ':status' => $status]);
    }

    public function closeOrCreateEpisode(int $employeeId, string $hireDate, string $lastWorkingDate, string $reason, ?string $notes, int $actorId): void
    {
        $stmt = $this->pdo()->prepare(
            'SELECT episode_id FROM employee_employment_episode WHERE employee_id = :id AND ended_on IS NULL ORDER BY started_on DESC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':id' => $employeeId]);
        $episodeId = $stmt->fetchColumn();
        if ($episodeId !== false) {
            $this->pdo()->prepare('UPDATE employee_employment_episode SET ended_on = :end, end_reason = :reason, notes = :notes WHERE episode_id = :id')
                ->execute([':end' => $lastWorkingDate, ':reason' => $reason, ':notes' => $notes, ':id' => $episodeId]);
            return;
        }

        $this->pdo()->prepare(
            "INSERT INTO employee_employment_episode (employee_id, started_on, ended_on, start_reason, end_reason, notes, created_by) VALUES (:employee, :start, :end, 'Hire', :reason, :notes, :actor)"
        )->execute([':employee' => $employeeId, ':start' => $hireDate, ':end' => $lastWorkingDate, ':reason' => $reason, ':notes' => $notes, ':actor' => $actorId]);
    }

    public function addRehireEpisode(int $employeeId, string $rehireDate, ?string $notes, int $actorId): void
    {
        $this->pdo()->prepare(
            "INSERT INTO employee_employment_episode (employee_id, started_on, start_reason, notes, created_by) VALUES (:employee, :start, 'Rehire', :notes, :actor)"
        )->execute([':employee' => $employeeId, ':start' => $rehireDate, ':notes' => $notes, ':actor' => $actorId]);
    }

    public function addEvent(int $employeeId, string $eventType, string $previousStatus, string $newStatus, string $effectiveDate, string $reason, ?string $notes, int $actorId): void
    {
        $this->pdo()->prepare(
            'INSERT INTO employee_lifecycle_event (employee_id, event_type, previous_status, new_status, effective_date, reason, notes, acted_by) VALUES (:employee, :event, :previous, :new, :date, :reason, :notes, :actor)'
        )->execute([':employee' => $employeeId, ':event' => $eventType, ':previous' => $previousStatus, ':new' => $newStatus, ':date' => $effectiveDate, ':reason' => $reason, ':notes' => $notes, ':actor' => $actorId]);
    }

    public function deactivateEmployeeAccounts(int $employeeId): void
    {
        $this->pdo()->prepare("UPDATE users SET status = 'Inactive', updated_at = NOW() WHERE employee_id = :id AND status = 'Active'")
            ->execute([':id' => $employeeId]);
    }

    public function assertRehireAssignmentsAvailable(int $employeeId, int $branchId, int $scheduleId, int $deviceId, string $enrollmentCode, string $rehireDate): void
    {
        $pdo = $this->pdo();
        foreach ([
            ['branch', 'branch_id', $branchId],
            ['work schedule', 'schedule_id', $scheduleId],
            ['biometric device', 'device_id', $deviceId],
        ] as [$label, $column, $id]) {
            $table = $label === 'branch' ? 'branch' : ($label === 'work schedule' ? 'work_schedule' : 'biometric_device');
            $stmt = $pdo->prepare("SELECT status FROM {$table} WHERE {$column} = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->fetchColumn() !== 'Active') {
                throw new \RuntimeException('Selected ' . $label . ' is no longer active.');
            }
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM employee_biometric_enrollment WHERE device_id = :device AND device_employee_code = :code AND employee_id <> :employee AND effective_from <= :date_from AND (effective_to IS NULL OR effective_to > :date_to)"
        );
        $stmt->execute([':device' => $deviceId, ':code' => $enrollmentCode, ':employee' => $employeeId, ':date_from' => $rehireDate, ':date_to' => $rehireDate]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('This biometric enrollment ID is already assigned to another active employee.');
        }
    }

    public function assertNoOpenRelationships(int $employeeId): void
    {
        foreach (['employee_branch_assignment', 'employee_schedule_assignment', 'salary', 'employee_biometric_enrollment'] as $table) {
            $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM {$table} WHERE employee_id = :id AND effective_to IS NULL");
            $stmt->execute([':id' => $employeeId]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new \RuntimeException('The employee still has an open ' . str_replace('_', ' ', $table) . '. Resolve it before rehiring.');
            }
        }
    }

    public function hasApprovedPayrollOnDate(int $employeeId, string $date): bool
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*)
               FROM payroll p
               JOIN payroll_period pp ON pp.payroll_period_id = p.payroll_period_id
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
              WHERE p.employee_id = :employee
                AND pr.status = 'Approved'
                AND :period_date BETWEEN pp.period_start AND pp.period_end"
        );
        $stmt->execute([':employee' => $employeeId, ':period_date' => $date]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addRehireRelationships(int $employeeId, int $branchId, int $scheduleId, int $deviceId, string $enrollmentCode, float $dailyRate, string $rehireDate): void
    {
        $pdo = $this->pdo();
        $pdo->prepare('INSERT INTO employee_branch_assignment (employee_id, branch_id, effective_from) VALUES (:employee, :branch, :date)')
            ->execute([':employee' => $employeeId, ':branch' => $branchId, ':date' => $rehireDate]);
        $pdo->prepare("INSERT INTO employee_schedule_assignment (employee_id, schedule_id, effective_from, status) VALUES (:employee, :schedule, :date, 'Active')")
            ->execute([':employee' => $employeeId, ':schedule' => $scheduleId, ':date' => $rehireDate]);
        $pdo->prepare("INSERT INTO salary (employee_id, daily_rate, effective_from, status, created_at, updated_at) VALUES (:employee, :rate, :date, 'Active', NOW(), NOW())")
            ->execute([':employee' => $employeeId, ':rate' => $dailyRate, ':date' => $rehireDate]);
        $pdo->prepare("INSERT INTO employee_biometric_enrollment (employee_id, device_id, device_employee_code, effective_from, status, created_at, updated_at) VALUES (:employee, :device, :code, :date, 'Active', NOW(), NOW())")
            ->execute([':employee' => $employeeId, ':device' => $deviceId, ':code' => $enrollmentCode, ':date' => $rehireDate]);
    }

    public function updateEmploymentDetails(int $employeeId, string $position, string $employeeType): void
    {
        $this->pdo()->prepare('UPDATE employee SET position = :position, employee_type = :type, status = \'Active\', updated_at = NOW() WHERE employee_id = :id')
            ->execute([':position' => $position, ':type' => $employeeType, ':id' => $employeeId]);
    }

    public function reactivateEmployeeAccounts(int $employeeId, string $temporaryPassword): bool
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE users
                SET status = 'Active', password_hash = :password, requires_password_change = 1, updated_at = NOW()
              WHERE employee_id = :id AND status IN ('Inactive', 'Archived')"
        );
        $stmt->execute([':id' => $employeeId, ':password' => password_hash($temporaryPassword, PASSWORD_DEFAULT)]);

        return $stmt->rowCount() > 0;
    }
}
