<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wbpms\Application\AuthService;
use Wbpms\Infrastructure\Database\Connection;

/**
 * Unit tests for AuthService.
 *
 * REQ001: login with username and password
 * REQN011: password_hash/password_verify with PASSWORD_DEFAULT
 * ADR-0002 §10: audit rows work with user_id = NULL for failed login
 *
 * These tests mock PDO so no database is required for the unit suite.
 */
final class AuthServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a Connection stub backed by a PDO mock.
     */
    private function makeConnection(PDO $pdo): Connection
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('pdo')->willReturn($pdo);

        return $connection;
    }

    /**
     * Build a PDO mock that:
     *   1. Returns $userRow for the SELECT query (first prepare/execute)
     *   2. Returns a no-op statement for the audit INSERT (second prepare/execute)
     */
    private function makePdoWithUser(?array $userRow): PDO
    {
        /** @var PDOStatement&MockObject $userStmt */
        $userStmt = $this->createMock(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetch')->willReturn($userRow ?? false);

        /** @var PDOStatement&MockObject $auditStmt */
        $auditStmt = $this->createMock(PDOStatement::class);
        $auditStmt->method('execute')->willReturn(true);

        /** @var PDO&MockObject $pdo */
        $pdo = $this->createMock(PDO::class);

        // First prepare() = SELECT users+role; second = INSERT audit_logs
        $pdo->expects($this->atLeast(1))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($userStmt, $auditStmt, $auditStmt, $auditStmt);

        return $pdo;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    /**
     * @test
     * REQ001: valid credentials return an identity array
     */
    public function it_returns_identity_on_valid_credentials(): void
    {
        $hash = password_hash('correct-password', PASSWORD_DEFAULT);

        $userRow = [
            'user_id'       => 1,
            'username'      => 'hrhead',
            'password_hash' => $hash,
            'status'        => 'Active',
            'employee_id'   => null,
            'role_name'     => 'HRHead',
        ];

        $pdo        = $this->makePdoWithUser($userRow);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('hrhead', 'correct-password');

        $this->assertNotNull($identity, 'Expected a valid identity on correct credentials');
        $this->assertSame(1, $identity['user_id']);
        $this->assertSame('hrhead', $identity['username']);
        $this->assertSame('HRHead', $identity['role_name']);
        $this->assertNull($identity['employee_id']);
    }

    /**
     * @test
     * REQ001: Business Owner (no employee_id) can log in
     */
    public function it_returns_identity_for_business_owner_with_null_employee_id(): void
    {
        $hash = password_hash('owner-pass', PASSWORD_DEFAULT);

        $userRow = [
            'user_id'       => 2,
            'username'      => 'owner',
            'password_hash' => $hash,
            'status'        => 'Active',
            'employee_id'   => null,
            'role_name'     => 'BusinessOwner',
        ];

        $pdo        = $this->makePdoWithUser($userRow);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('owner', 'owner-pass');

        $this->assertNotNull($identity);
        $this->assertNull($identity['employee_id']);
        $this->assertSame('BusinessOwner', $identity['role_name']);
    }

    /**
     * @test
     * REQN011: identity includes employee_id when the user is linked to an employee
     */
    public function it_returns_employee_id_when_user_has_employee_link(): void
    {
        $hash = password_hash('emp-pass', PASSWORD_DEFAULT);

        $userRow = [
            'user_id'       => 3,
            'username'      => 'employee',
            'password_hash' => $hash,
            'status'        => 'Active',
            'employee_id'   => 42,
            'role_name'     => 'Employee',
        ];

        $pdo        = $this->makePdoWithUser($userRow);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('employee', 'emp-pass');

        $this->assertNotNull($identity);
        $this->assertSame(42, $identity['employee_id']);
    }

    // -----------------------------------------------------------------------
    // Failure paths
    // -----------------------------------------------------------------------

    /**
     * @test
     * REQ001: wrong password returns null
     */
    public function it_returns_null_on_wrong_password(): void
    {
        $hash = password_hash('correct-password', PASSWORD_DEFAULT);

        $userRow = [
            'user_id'       => 1,
            'username'      => 'hrhead',
            'password_hash' => $hash,
            'status'        => 'Active',
            'employee_id'   => null,
            'role_name'     => 'HRHead',
        ];

        $pdo        = $this->makePdoWithUser($userRow);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('hrhead', 'wrong-password');

        $this->assertNull($identity, 'Expected null for wrong password');
    }

    /**
     * @test
     * REQ001: unknown username returns null
     */
    public function it_returns_null_when_user_does_not_exist(): void
    {
        $pdo        = $this->makePdoWithUser(null); // fetch returns false
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('no-such-user', 'any-password');

        $this->assertNull($identity, 'Expected null for non-existent user');
    }

    /**
     * @test
     * REQ001: inactive account returns null even with correct credentials
     */
    public function it_returns_null_for_inactive_user(): void
    {
        $hash = password_hash('correct-password', PASSWORD_DEFAULT);

        $userRow = [
            'user_id'       => 1,
            'username'      => 'hrhead',
            'password_hash' => $hash,
            'status'        => 'Inactive', // <-- inactive
            'employee_id'   => null,
            'role_name'     => 'HRHead',
        ];

        $pdo        = $this->makePdoWithUser($userRow);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('hrhead', 'correct-password');

        $this->assertNull($identity, 'Expected null for inactive account');
    }

    /**
     * @test
     * REQ001: archived account returns null
     */
    public function it_returns_null_for_archived_user(): void
    {
        $hash = password_hash('pass', PASSWORD_DEFAULT);

        $userRow = [
            'user_id'       => 5,
            'username'      => 'archived-user',
            'password_hash' => $hash,
            'status'        => 'Archived',
            'employee_id'   => null,
            'role_name'     => 'Employee',
        ];

        $pdo        = $this->makePdoWithUser($userRow);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('archived-user', 'pass');

        $this->assertNull($identity);
    }

    /**
     * @test
     * REQ001: empty credentials return null (no DB call needed; service handles gracefully)
     */
    public function it_returns_null_for_empty_credentials(): void
    {
        // PDO will return false for the empty-string username query
        $pdo        = $this->makePdoWithUser(null);
        $connection = $this->makeConnection($pdo);
        $service    = new AuthService($connection);

        $identity = $service->attemptLogin('', '');

        $this->assertNull($identity);
    }
}
