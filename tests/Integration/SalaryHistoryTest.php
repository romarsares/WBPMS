<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Application\SalaryService;
use Wbpms\Infrastructure\Database\Connection;

/**
 * SalaryHistoryTest — Task 8.4
 *
 * Verifies that SalaryService::update() inserts a new salary row and supersedes
 * (but never mutates) the previous history row, per REQ055.
 */
final class SalaryHistoryTest extends IntegrationTestCase
{
    private SalaryService $service;
    private int $employeeId;
    private int $actorUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service     = new SalaryService(new Connection(['pdo' => $this->pdo]));
        $this->employeeId  = $this->insertEmployee();
        $branchId          = $this->insertBranch();
        $this->insertBranchAssignment($this->employeeId, $branchId, '2026-01-01');
        $roleId            = $this->insertRole('HRHead');
        $this->actorUserId = $this->insertUser($roleId);
    }

    // -----------------------------------------------------------------------
    // Task 8.4 — rate update must not mutate history rows
    // -----------------------------------------------------------------------

    public function testUpdateRateDoesNotMutateHistoryRows(): void
    {
        // Create the initial salary record
        $originalId = $this->service->create([
            'employee_id'   => $this->employeeId,
            'daily_rate'    => '460.00',
            'effective_from' => '2026-01-01',
        ], $this->actorUserId);

        // Snapshot the original row's values before the update
        $before = $this->fetchSalary($originalId);
        self::assertSame('Active', $before['status']);
        self::assertSame('460.00', number_format((float) $before['daily_rate'], 2, '.', ''));
        self::assertSame('2026-01-01', $before['effective_from']);
        self::assertNull($before['effective_to']);

        // Perform a rate update (inserts a new row, closes the old one)
        $newId = $this->service->update($originalId, [
            'daily_rate'    => '500.00',
            'effective_from' => '2026-09-01',
        ], $this->actorUserId);

        self::assertNotSame($originalId, $newId, 'update() must return a new salary_id');

        // The new row is Active with the updated rate
        $newRow = $this->fetchSalary($newId);
        self::assertSame('Active', $newRow['status']);
        self::assertSame('500.00', number_format((float) $newRow['daily_rate'], 2, '.', ''));
        self::assertSame('2026-09-01', $newRow['effective_from']);
        self::assertNull($newRow['effective_to']);

        // The original row must be Superseded — rate and effective_from UNCHANGED
        $after = $this->fetchSalary($originalId);
        self::assertSame('Superseded', $after['status'],
            'Previous row must be Superseded, not mutated');
        self::assertSame('460.00', number_format((float) $after['daily_rate'], 2, '.', ''),
            'Previous daily_rate must not be changed');
        self::assertSame('2026-01-01', $after['effective_from'],
            'Previous effective_from must not be changed');
        // effective_to is set to one day before the new effective_from
        self::assertSame('2026-08-31', $after['effective_to'],
            'Previous effective_to must be set to the day before new effective_from');
    }

    public function testUpdateRatePreservesMultipleHistoryRows(): void
    {
        // Create initial salary
        $id1 = $this->service->create([
            'employee_id'   => $this->employeeId,
            'daily_rate'    => '400.00',
            'effective_from' => '2026-01-01',
        ], $this->actorUserId);

        // First update
        $id2 = $this->service->update($id1, [
            'daily_rate'    => '460.00',
            'effective_from' => '2026-06-01',
        ], $this->actorUserId);

        // Second update
        $id3 = $this->service->update($id2, [
            'daily_rate'    => '520.00',
            'effective_from' => '2026-10-01',
        ], $this->actorUserId);

        // All three rows must exist
        $row1 = $this->fetchSalary($id1);
        $row2 = $this->fetchSalary($id2);
        $row3 = $this->fetchSalary($id3);

        // row1: rate still 400.00 (not 460 or 520)
        self::assertSame('400.00', number_format((float) $row1['daily_rate'], 2, '.', ''));
        self::assertSame('Superseded', $row1['status']);

        // row2: rate still 460.00 (not 520)
        self::assertSame('460.00', number_format((float) $row2['daily_rate'], 2, '.', ''));
        self::assertSame('Superseded', $row2['status']);

        // row3: current active
        self::assertSame('520.00', number_format((float) $row3['daily_rate'], 2, '.', ''));
        self::assertSame('Active', $row3['status']);
        self::assertNull($row3['effective_to']);
    }

    public function testUpdateRateRequiresFutureDateRelativeToExisting(): void
    {
        $this->service->create([
            'employee_id'   => $this->employeeId,
            'daily_rate'    => '460.00',
            'effective_from' => '2026-09-01',
        ], $this->actorUserId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/effective date must be after/i');

        // Same date as the existing active row — must be rejected
        $this->service->update(
            $this->service->currentRate($this->employeeId)['salary_id'],
            ['daily_rate' => '500.00', 'effective_from' => '2026-09-01'],
            $this->actorUserId
        );
    }

    public function testCurrentRateReturnsLatestActiveRow(): void
    {
        $this->service->create([
            'employee_id'   => $this->employeeId,
            'daily_rate'    => '400.00',
            'effective_from' => '2026-01-01',
        ], $this->actorUserId);

        $id2 = $this->service->update(
            $this->service->currentRate($this->employeeId)['salary_id'],
            ['daily_rate' => '500.00', 'effective_from' => '2026-07-01'],
            $this->actorUserId
        );

        $current = $this->service->currentRate($this->employeeId);
        self::assertNotNull($current);
        self::assertSame($id2, (int) $current['salary_id']);
        self::assertSame('500.00', number_format((float) $current['daily_rate'], 2, '.', ''));
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function fetchSalary(int $salaryId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT salary_id, daily_rate, effective_from, effective_to, status FROM salary WHERE salary_id = :id'
        );
        $stmt->execute([':id' => $salaryId]);
        $row = $stmt->fetch();
        self::assertNotFalse($row, "salary_id={$salaryId} not found");
        return $row;
    }
}
