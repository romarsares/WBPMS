<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Application\RequestService;
use Wbpms\Infrastructure\Database\Connection;

/**
 * RequestLifecycleTest — Task 7.6
 *
 * Integration test for the full request lifecycle:
 *   submit → insufficient-balance rejection → valid submission → HR approve
 *   → Owner approve (leave debit side-effect) → archive
 *
 * REQ076, REQ077, REQ078, REQ079, REQ080
 */
final class RequestLifecycleTest extends IntegrationTestCase
{
    private RequestService $service;
    private int $employeeId;
    private int $hrUserId;
    private int $ownerUserId;
    private int $leaveTypeId;
    private int $overtimeTypeId;
    private int $cashAdvanceTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RequestService(new Connection(['pdo' => $this->pdo]));

        $this->employeeId = $this->insertEmployee();
        $branchId         = $this->insertBranch();
        $this->insertBranchAssignment($this->employeeId, $branchId, '2026-01-01');

        $hrRoleId          = $this->insertRole('HRHead');
        $ownerRoleId       = $this->insertRole('BusinessOwner');
        $this->hrUserId    = $this->insertUser($hrRoleId);
        $this->ownerUserId = $this->insertUser($ownerRoleId);

        // Seed request_type rows for this transaction
        $this->leaveTypeId       = $this->insertRequestType('Leave');
        $this->overtimeTypeId    = $this->insertRequestType('Overtime');
        $this->cashAdvanceTypeId = $this->insertRequestType('CashAdvance');
    }

    // -----------------------------------------------------------------------
    // Leave submission + balance guard (REQ076, REQ078)
    // -----------------------------------------------------------------------

    public function testLeaveSubmissionBlockedWhenBalanceInsufficient(): void
    {
        // Provision a minimal entitlement (0.5 days — not enough for 3 days)
        $this->insertLeaveEntitlement($this->employeeId, (int) date('Y'), 0.5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/insufficient|balance|not enough/i');

        $this->service->submit($this->employeeId, [
            'request_type_id' => $this->leaveTypeId,
            'reason'          => 'Medical check-up',
            'start_date'      => '2026-11-03',
            'end_date'        => '2026-11-05', // 3 days requested
        ]);
    }

    public function testLeaveSubmissionSucceedsWithSufficientBalance(): void
    {
        // Default entitlement is 4 days (auto-provisioned)
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->leaveTypeId,
            'reason'          => 'Rest and recovery',
            'start_date'      => '2026-11-03',
            'end_date'        => '2026-11-04', // 2 days
        ]);

        self::assertGreaterThan(0, $requestId);

        $row = $this->service->findOrFail($requestId);
        self::assertSame('Pending', $row['status']);
        self::assertSame('Leave', $row['type_name']);
    }

    // -----------------------------------------------------------------------
    // HR pre-approve flow (REQ034)
    // -----------------------------------------------------------------------

    public function testHrApproveMovesToHrApproved(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->leaveTypeId,
            'reason'          => 'Annual leave',
            'start_date'      => '2026-11-10',
            'end_date'        => '2026-11-10',
        ]);

        $this->service->approve($requestId, $this->hrUserId);

        $row = $this->service->findOrFail($requestId);
        self::assertSame('HRApproved', $row['status']);
        self::assertSame((string) $this->hrUserId, (string) $row['reviewed_by']);
    }

    public function testHrApproveRejectsNonPendingRequest(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->overtimeTypeId,
            'reason'          => 'Extra work needed',
            'overtime_date'   => '2026-11-03',
            'start_time'      => '17:00',
            'end_time'        => '19:00',
        ]);

        // Cancel it first
        $this->service->cancel($requestId, $this->employeeId);

        $this->expectException(RuntimeException::class);
        $this->service->approve($requestId, $this->hrUserId);
    }

    // -----------------------------------------------------------------------
    // Owner final approve + leave debit side-effect (REQ034b, REQ078)
    // -----------------------------------------------------------------------

    public function testOwnerApproveDebitsLeaveBalance(): void
    {
        $year      = (int) date('Y');
        $startBal  = $this->service->leaveBalance($this->employeeId, $year);

        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->leaveTypeId,
            'reason'          => 'Personal leave',
            'start_date'      => '2026-11-17',
            'end_date'        => '2026-11-17', // 1 day
        ]);

        $this->service->approve($requestId, $this->hrUserId);
        $this->service->ownerApprove($requestId, $this->ownerUserId);

        $row = $this->service->findOrFail($requestId);
        self::assertSame('Approved', $row['status']);

        $afterBal = $this->service->leaveBalance($this->employeeId, $year);
        self::assertEqualsWithDelta($startBal - 1.0, $afterBal, 0.01,
            'Leave balance must be debited by 1 day after Owner approval');
    }

    public function testOwnerApproveRequiresHrApprovedStatus(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->leaveTypeId,
            'reason'          => 'Leave request',
            'start_date'      => '2026-11-20',
            'end_date'        => '2026-11-20',
        ]);

        // Still Pending — Owner cannot approve without HR pre-approval
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/HR-approved/i');
        $this->service->ownerApprove($requestId, $this->ownerUserId);
    }

    // -----------------------------------------------------------------------
    // Cash advance side-effect (REQ034b, REQ079)
    // -----------------------------------------------------------------------

    public function testOwnerApproveCreatesCashAdvanceObligation(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->cashAdvanceTypeId,
            'reason'          => 'Emergency expense',
            'amount_requested' => '2000.00',
        ]);

        $this->service->approve($requestId, $this->hrUserId);
        $this->service->ownerApprove($requestId, $this->ownerUserId);

        $stmt = $this->pdo->prepare(
            "SELECT original_amount, remaining_balance, status
               FROM cash_advance_history
              WHERE request_id = :id"
        );
        $stmt->execute([':id' => $requestId]);
        $obligation = $stmt->fetch();

        self::assertNotFalse($obligation, 'cash_advance_history row must be created on Owner approval');
        self::assertSame('2000.00', number_format((float) $obligation['original_amount'], 2, '.', ''));
        self::assertSame('2000.00', number_format((float) $obligation['remaining_balance'], 2, '.', ''));
        self::assertSame('Active', $obligation['status']);
    }

    // -----------------------------------------------------------------------
    // Archive (REQ036)
    // -----------------------------------------------------------------------

    public function testArchiveRequest(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->overtimeTypeId,
            'reason'          => 'Extended project',
            'overtime_date'   => '2026-11-05',
            'start_time'      => '18:00',
            'end_time'        => '20:00',
        ]);

        $this->service->approve($requestId, $this->hrUserId);
        $this->service->ownerApprove($requestId, $this->ownerUserId);
        $this->service->archive($requestId, $this->hrUserId);

        $row = $this->service->findOrFail($requestId);
        self::assertNotNull($row['archived_at'], 'archived_at must be set after archive()');
    }

    public function testArchiveAlreadyArchivedRequestThrows(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->overtimeTypeId,
            'reason'          => 'Overtime',
            'overtime_date'   => '2026-11-06',
            'start_time'      => '17:00',
            'end_time'        => '19:00',
        ]);

        $this->service->approve($requestId, $this->hrUserId);
        $this->service->ownerApprove($requestId, $this->ownerUserId);
        $this->service->archive($requestId, $this->hrUserId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already archived/i');
        $this->service->archive($requestId, $this->hrUserId);
    }

    // -----------------------------------------------------------------------
    // Employee cancel (REQ080)
    // -----------------------------------------------------------------------

    public function testEmployeeCancelsPendingRequest(): void
    {
        $requestId = $this->service->submit($this->employeeId, [
            'request_type_id' => $this->leaveTypeId,
            'reason'          => 'Cancel this',
            'start_date'      => '2026-12-01',
            'end_date'        => '2026-12-01',
        ]);

        $this->service->cancel($requestId, $this->employeeId);

        $row = $this->service->findOrFail($requestId);
        self::assertSame('Cancelled', $row['status']);
    }

    // -----------------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------------

    private function insertRequestType(string $typeName): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO request_type (type_name, status) VALUES (:n, 'Active')"
        );
        $stmt->execute([':n' => $typeName . '_' . uniqid()]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertLeaveEntitlement(int $employeeId, int $year, float $daysGranted): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO leave_entitlement (employee_id, leave_type, year, days_granted)
             VALUES (:emp, 'Sick', :year, :days)
             ON DUPLICATE KEY UPDATE days_granted = :days2"
        );
        $stmt->execute([
            ':emp'   => $employeeId,
            ':year'  => $year,
            ':days'  => $daysGranted,
            ':days2' => $daysGranted,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            // ON DUPLICATE KEY — fetch existing
            $stmt2 = $this->pdo->prepare(
                "SELECT entitlement_id FROM leave_entitlement WHERE employee_id = :emp AND leave_type = 'Sick' AND year = :year"
            );
            $stmt2->execute([':emp' => $employeeId, ':year' => $year]);
            $id = (int) $stmt2->fetchColumn();
        }
        // Delete any existing ledger credits so the balance starts from daysGranted
        $this->pdo->prepare("DELETE FROM leave_ledger WHERE entitlement_id = :id")->execute([':id' => $id]);
        // Insert the Grant credit row
        $this->pdo->prepare(
            "INSERT INTO leave_ledger (entitlement_id, employee_id, ledger_type, days_delta, description)
             VALUES (:eid, :emp, 'Grant', :days, 'Test entitlement')"
        )->execute([':eid' => $id, ':emp' => $employeeId, ':days' => $daysGranted]);
        return $id;
    }
}
