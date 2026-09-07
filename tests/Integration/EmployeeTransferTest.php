<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeRepository;

/**
 * Integration test: employee branch transfer (task 4.5)
 *
 * REQ009, REQ017, ADR-0002 §8:
 *   - transferEmployee closes the current assignment and opens a new one.
 *   - The two assignments must not overlap.
 *   - Transferring to the same branch is rejected.
 *   - A transfer date before or on the current assignment start is rejected.
 */
final class EmployeeTransferTest extends IntegrationTestCase
{
    private EmployeeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        // Wrap the shared PDO (already in a rolled-back transaction) in a Connection.
        $this->repo = new EmployeeRepository(
            new Connection([], static::$sharedPdo)
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testTransferClosesOldAndOpensNewAssignment(): void
    {
        $branchA    = $this->insertBranch();
        $branchB    = $this->insertBranch();
        $employeeId = $this->insertEmployee();
        $this->insertBranchAssignment($employeeId, $branchA, '2026-01-01');

        $this->repo->transferEmployee($employeeId, $branchB, '2026-06-01');

        // The old period ends exclusively on the transfer date, so it covers
        // dates before transfer but not the transfer date itself.
        $stmt = $this->pdo->prepare(
            "SELECT effective_to FROM employee_branch_assignment
              WHERE employee_id = :emp AND branch_id = :br"
        );
        $stmt->execute([':emp' => $employeeId, ':br' => $branchA]);
        $old = $stmt->fetch();
        $this->assertSame('2026-06-01', $old['effective_to'],
            'Old assignment effective_to should equal the transfer date.');

        // New assignment must be open (effective_to IS NULL)
        $stmt->execute([':emp' => $employeeId, ':br' => $branchB]);
        $new = $stmt->fetch();
        $this->assertNotFalse($new, 'New branch assignment should exist.');
        $this->assertNull($new['effective_to'],
            'New assignment effective_to should be NULL (open).');
        $this->assertSame('2026-06-01', $new['effective_from']);
    }

    public function testAssignmentsDoNotOverlap(): void
    {
        $branchA    = $this->insertBranch();
        $branchB    = $this->insertBranch();
        $employeeId = $this->insertEmployee();
        $this->insertBranchAssignment($employeeId, $branchA, '2026-01-01');

        $this->repo->transferEmployee($employeeId, $branchB, '2026-06-01');

        // No two open assignments for the same employee should exist
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt
               FROM employee_branch_assignment
              WHERE employee_id = :emp AND effective_to IS NULL"
        );
        $stmt->execute([':emp' => $employeeId]);
        $this->assertSame(1, (int) $stmt->fetchColumn(),
            'Exactly one open assignment should exist after transfer.');
    }

    public function testTransferBoundaryResolvesOldBranchBeforeAndNewBranchOnEffectiveDate(): void
    {
        $branchA    = $this->insertBranch();
        $branchB    = $this->insertBranch();
        $employeeId = $this->insertEmployee();
        $this->insertBranchAssignment($employeeId, $branchA, '2026-01-01');

        $this->repo->transferEmployee($employeeId, $branchB, '2026-06-01');

        $branchForDate = $this->pdo->prepare(
            "SELECT branch_id FROM employee_branch_assignment
              WHERE employee_id = :employee_id
                AND effective_from <= :from_date
                AND (effective_to IS NULL OR effective_to > :to_date)
              ORDER BY effective_from DESC
              LIMIT 1"
        );

        $branchForDate->execute([
            ':employee_id' => $employeeId,
            ':from_date' => '2026-05-31',
            ':to_date' => '2026-05-31',
        ]);
        $this->assertSame($branchA, (int) $branchForDate->fetchColumn());

        $branchForDate->execute([
            ':employee_id' => $employeeId,
            ':from_date' => '2026-06-01',
            ':to_date' => '2026-06-01',
        ]);
        $this->assertSame($branchB, (int) $branchForDate->fetchColumn());
    }

    // -----------------------------------------------------------------------
    // Guard: same branch
    // -----------------------------------------------------------------------

    public function testTransferToSameBranchThrows(): void
    {
        $branchA    = $this->insertBranch();
        $employeeId = $this->insertEmployee();
        $this->insertBranchAssignment($employeeId, $branchA, '2026-01-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already assigned/i');

        $this->repo->transferEmployee($employeeId, $branchA, '2026-06-01');
    }

    // -----------------------------------------------------------------------
    // Guard: transfer date before assignment start
    // -----------------------------------------------------------------------

    public function testTransferDateBeforeStartThrows(): void
    {
        $branchA    = $this->insertBranch();
        $branchB    = $this->insertBranch();
        $employeeId = $this->insertEmployee();
        $this->insertBranchAssignment($employeeId, $branchA, '2026-06-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be after/i');

        $this->repo->transferEmployee($employeeId, $branchB, '2026-05-01');
    }

    // -----------------------------------------------------------------------
    // Guard: no active assignment
    // -----------------------------------------------------------------------

    public function testTransferWithNoActiveAssignmentThrows(): void
    {
        $branchB    = $this->insertBranch();
        $employeeId = $this->insertEmployee();
        // No assignment inserted

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No active branch assignment/i');

        $this->repo->transferEmployee($employeeId, $branchB, '2026-06-01');
    }

    // -----------------------------------------------------------------------
    // No private helpers needed — uses IntegrationTestCase fixture helpers
    // -----------------------------------------------------------------------
}
