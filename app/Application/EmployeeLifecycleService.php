<?php

declare(strict_types=1);

namespace Wbpms\Application;

use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeLifecycleRepository;

/** Coordinates the all-or-nothing archive and rehire lifecycle workflows. */
final class EmployeeLifecycleService
{
    private EmployeeLifecycleRepository $repository;

    public function __construct(private Connection $connection)
    {
        $this->repository = new EmployeeLifecycleRepository($connection);
    }

    public function archive(int $employeeId, string $lastWorkingDate, string $reason, ?string $notes, int $actorId): void
    {
        $archiveDate = (new \DateTimeImmutable($lastWorkingDate))->modify('+1 day')->format('Y-m-d');
        $this->connection->transaction(function () use ($employeeId, $lastWorkingDate, $archiveDate, $reason, $notes, $actorId): void {
            $employee = $this->repository->employeeForUpdate($employeeId);
            if ($employee === null) {
                throw new \RuntimeException('Employee was not found.');
            }
            if ($employee['status'] === 'Archived') {
                throw new \RuntimeException('This employee is already archived.');
            }
            if ($lastWorkingDate < (string) $employee['hire_date']) {
                throw new \RuntimeException('Last working date cannot be before the employee hire date.');
            }
            $blockers = $this->repository->archiveBlockers($employeeId);
            if ($blockers !== []) {
                throw new \RuntimeException('Archive is blocked by: ' . implode(', ', $blockers) . '. Resolve these items first.');
            }
            $conflicts = $this->repository->archiveStartDateConflicts($employeeId, $archiveDate);
            if ($conflicts !== []) {
                throw new \RuntimeException('Archive date conflicts with a future or same-day ' . implode(', ', $conflicts) . '. Adjust the effective dates first.');
            }

            $this->repository->closeActiveRelationships($employeeId, $archiveDate);
            $this->repository->setEmployeeStatus($employeeId, 'Archived');
            $this->repository->deactivateEmployeeAccounts($employeeId);
            $this->repository->closeOrCreateEpisode($employeeId, (string) $employee['hire_date'], $lastWorkingDate, $reason, $notes, $actorId);
            $this->repository->addEvent($employeeId, 'Archive', (string) $employee['status'], 'Archived', $archiveDate, $reason, $notes, $actorId);
        });
    }

    /** @param array{rehire_date:string,branch_id:int,schedule_id:int,device_id:int,enrollment_code:string,daily_rate:float,position:string,employee_type:string,notes:?string,reactivate_account:bool} $data */
    public function rehire(int $employeeId, array $data, int $actorId): ?string
    {
        return $this->connection->transaction(function () use ($employeeId, $data, $actorId): ?string {
            $employee = $this->repository->employeeForUpdate($employeeId);
            if ($employee === null) {
                throw new \RuntimeException('Employee was not found.');
            }
            if ($employee['status'] !== 'Archived') {
                throw new \RuntimeException('Only an archived employee can be rehired through this workflow.');
            }
            if ($data['rehire_date'] < (string) $employee['hire_date']) {
                throw new \RuntimeException('Rehire date cannot be before the original hire date.');
            }
            if ($this->repository->hasApprovedPayrollOnDate($employeeId, $data['rehire_date'])) {
                throw new \RuntimeException('Rehire date falls within an approved payroll period for this employee. Choose a date outside that period.');
            }
            $this->repository->assertNoOpenRelationships($employeeId);
            $this->repository->assertRehireAssignmentsAvailable($employeeId, $data['branch_id'], $data['schedule_id'], $data['device_id'], $data['enrollment_code'], $data['rehire_date']);
            $this->repository->updateEmploymentDetails($employeeId, $data['position'], $data['employee_type']);
            $this->repository->addRehireRelationships($employeeId, $data['branch_id'], $data['schedule_id'], $data['device_id'], $data['enrollment_code'], $data['daily_rate'], $data['rehire_date']);
            $this->repository->addRehireEpisode($employeeId, $data['rehire_date'], $data['notes'], $actorId);
            $temporaryPassword = null;
            if ($data['reactivate_account']) {
                $candidate = (new UserService($this->connection))->generateTemporaryPassword();
                if ($this->repository->reactivateEmployeeAccounts($employeeId, $candidate)) {
                    $temporaryPassword = $candidate;
                }
            }
            $this->repository->addEvent($employeeId, 'Rehire', 'Archived', 'Active', $data['rehire_date'], 'Rehire', $data['notes'], $actorId);
            return $temporaryPassword;
        });
    }
}
