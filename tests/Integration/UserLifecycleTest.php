<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Application\UserService;
use Wbpms\Infrastructure\Database\Connection;

/**
 * UserLifecycleTest
 *
 * Integration test for the User Management module (REQ004–REQ008).
 *
 * Lifecycle proven:
 *   1. create — new Active user is persisted (REQ005)
 *   2. list   — user appears in the listing (REQ004)
 *   3. update — username, email, and role change is persisted (REQ006)
 *   4. deactivate — toggleStatus Active → Inactive (REQ007)
 *   5. reactivate — toggleStatus Inactive → Active (REQ007)
 *   6. archive — status becomes Archived; cannot be toggled again (REQ008)
 *
 * Guard tests:
 *   - Duplicate username rejected
 *   - Duplicate email rejected
 *   - Archived user toggle rejected
 *   - Archive already-archived user rejected
 *
 * Each test is wrapped in a transaction rolled back by IntegrationTestCase,
 * so the live wbpms_test database is not permanently modified.
 *
 * Requirement traceability: REQ004, REQ005, REQ006, REQ007, REQ008
 */
final class UserLifecycleTest extends IntegrationTestCase
{
    private UserService $service;

    /** Actor for all audit log inserts — a seeded HR Head user. */
    private int $actorId;

    // -----------------------------------------------------------------------
    // Per-test setup
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp(); // begins the rollback transaction

        $conn          = new Connection($this->testDbConfig());
        $conn->pdo(); // forces the connection to use the shared PDO handle
        $this->service = $this->buildService();
        $this->actorId = $this->insertUser($this->insertRole('HRHead'));
    }

    // -----------------------------------------------------------------------
    // REQ004 — List
    // -----------------------------------------------------------------------

    /**
     * REQ004: list() returns rows and a newly created user appears in it.
     */
    public function testListReturnsUsers(): void
    {
        $roleId  = $this->insertRole('BusinessOwner');
        $uid     = uniqid('listuser_');
        $service = $this->buildService();

        $service->create([
            'username'      => $uid,
            'account_email' => $uid . '@example.com',
            'password'      => 'Secret123!',
            'role_id'       => $roleId,
        ], $this->actorId);

        $list = $service->list();
        $usernames = array_column($list, 'username');

        $this->assertContains($uid, $usernames, 'Newly created user should appear in list()');
    }

    // -----------------------------------------------------------------------
    // REQ005 — Create
    // -----------------------------------------------------------------------

    /**
     * REQ005: create() inserts a row with status=Active and returns a positive ID.
     */
    public function testCreateInsertsActiveUser(): void
    {
        $roleId  = $this->insertRole('Employee');
        $uid     = uniqid('newuser_');
        $service = $this->buildService();

        $userId = $service->create([
            'username'      => $uid,
            'account_email' => $uid . '@example.com',
            'password'      => 'Password1!',
            'role_id'       => $roleId,
        ], $this->actorId);

        $this->assertGreaterThan(0, $userId);

        $user = $service->findOrFail($userId);
        $this->assertSame('Active', $user['status']);
        $this->assertSame($uid, $user['username']);
    }

    /**
     * REQ005: create() with a linked employee_id persists the link.
     */
    public function testCreateWithEmployeeLink(): void
    {
        $roleId     = $this->insertRole('Employee');
        $employeeId = $this->insertEmployee();
        $uid        = uniqid('empuser_');
        $service    = $this->buildService();

        $userId = $service->create([
            'username'      => $uid,
            'account_email' => $uid . '@example.com',
            'password'      => 'Password1!',
            'role_id'       => $roleId,
            'employee_id'   => $employeeId,
        ], $this->actorId);

        $user = $service->findOrFail($userId);
        $this->assertSame($employeeId, (int) $user['employee_id']);
    }

    /**
     * REQ005 guard: duplicate username is rejected.
     */
    public function testCreateDuplicateUsernameThrows(): void
    {
        $roleId  = $this->insertRole('Employee');
        $uid     = uniqid('dupuser_');
        $service = $this->buildService();

        $service->create([
            'username'      => $uid,
            'account_email' => $uid . '_1@example.com',
            'password'      => 'Password1!',
            'role_id'       => $roleId,
        ], $this->actorId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already taken/i');

        $service->create([
            'username'      => $uid,           // same username
            'account_email' => $uid . '_2@example.com',
            'password'      => 'Password1!',
            'role_id'       => $roleId,
        ], $this->actorId);
    }

    /**
     * REQ005 guard: duplicate email is rejected.
     */
    public function testCreateDuplicateEmailThrows(): void
    {
        $roleId  = $this->insertRole('Employee');
        $email   = uniqid('dupemail_') . '@example.com';
        $service = $this->buildService();

        $service->create([
            'username'      => uniqid('u1_'),
            'account_email' => $email,
            'password'      => 'Password1!',
            'role_id'       => $roleId,
        ], $this->actorId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already registered/i');

        $service->create([
            'username'      => uniqid('u2_'),
            'account_email' => $email,         // same email
            'password'      => 'Password1!',
            'role_id'       => $roleId,
        ], $this->actorId);
    }

    /**
     * REQ005 guard: short password is rejected.
     */
    public function testCreateShortPasswordThrows(): void
    {
        $roleId  = $this->insertRole('Employee');
        $service = $this->buildService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/8 characters/i');

        $service->create([
            'username'      => uniqid('shortpwd_'),
            'account_email' => uniqid('shortpwd_') . '@example.com',
            'password'      => 'abc',           // too short
            'role_id'       => $roleId,
        ], $this->actorId);
    }

    // -----------------------------------------------------------------------
    // REQ006 — Update
    // -----------------------------------------------------------------------

    /**
     * REQ006: update() persists new username, email, and role.
     */
    public function testUpdateChangesUsernameEmailRole(): void
    {
        $roleA   = $this->insertRole('Employee');
        $roleB   = $this->insertRole('HRHead');
        $uid     = uniqid('upd_');
        $service = $this->buildService();

        $userId = $service->create([
            'username'      => $uid,
            'account_email' => $uid . '@example.com',
            'password'      => 'Password1!',
            'role_id'       => $roleA,
        ], $this->actorId);

        $newUid   = uniqid('updated_');
        $newEmail = $newUid . '@example.com';

        $service->update($userId, [
            'username'      => $newUid,
            'account_email' => $newEmail,
            'role_id'       => $roleB,
        ], $this->actorId);

        $user = $service->findOrFail($userId);
        $this->assertSame($newUid,   $user['username']);
        $this->assertSame($newEmail, $user['account_email']);
        $this->assertSame($roleB,    (int) $user['role_id']);
    }

    // -----------------------------------------------------------------------
    // REQ007 — Activate / Deactivate
    // -----------------------------------------------------------------------

    /**
     * REQ007: toggleStatus Active → Inactive.
     */
    public function testToggleActiveToInactive(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        $newStatus = $service->toggleStatus($userId, $this->actorId);

        $this->assertSame('Inactive', $newStatus);
        $this->assertSame('Inactive', $service->findOrFail($userId)['status']);
    }

    /**
     * REQ007: toggleStatus Inactive → Active.
     */
    public function testToggleInactiveToActive(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        $service->toggleStatus($userId, $this->actorId); // → Inactive
        $newStatus = $service->toggleStatus($userId, $this->actorId); // → Active

        $this->assertSame('Active', $newStatus);
        $this->assertSame('Active', $service->findOrFail($userId)['status']);
    }

    /**
     * REQ007 guard: cannot toggle an Archived user.
     */
    public function testToggleArchivedUserThrows(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        $service->archive($userId, $this->actorId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/archived/i');

        $service->toggleStatus($userId, $this->actorId);
    }

    // -----------------------------------------------------------------------
    // REQ008 — Archive
    // -----------------------------------------------------------------------

    /**
     * REQ008: archive() sets status to Archived.
     */
    public function testArchiveSetsStatusArchived(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        $service->archive($userId, $this->actorId);

        $this->assertSame('Archived', $service->findOrFail($userId)['status']);
    }

    /**
     * REQ008 guard: archiving an already-archived user throws.
     */
    public function testArchiveAlreadyArchivedThrows(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        $service->archive($userId, $this->actorId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already archived/i');

        $service->archive($userId, $this->actorId);
    }

    /**
     * REQ008: archived user does NOT appear in list() (default, no archived).
     */
    public function testArchivedUserExcludedFromDefaultList(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        // Get the username before archiving
        $user = $service->findOrFail($userId);
        $service->archive($userId, $this->actorId);

        $list      = $service->list(includeArchived: false);
        $usernames = array_column($list, 'username');

        $this->assertNotContains($user['username'], $usernames,
            'Archived user must not appear in the default list');
    }

    /**
     * REQ008: archived user DOES appear in list(includeArchived: true).
     */
    public function testArchivedUserIncludedInFullList(): void
    {
        $userId  = $this->createActiveUser();
        $service = $this->buildService();

        $user = $service->findOrFail($userId);
        $service->archive($userId, $this->actorId);

        $list      = $service->list(includeArchived: true);
        $usernames = array_column($list, 'username');

        $this->assertContains($user['username'], $usernames,
            'Archived user must appear in includeArchived=true list');
    }

    // -----------------------------------------------------------------------
    // Full lifecycle: create → update role → deactivate → archive
    // -----------------------------------------------------------------------

    /**
     * REQ004–REQ008: full lifecycle in one sequential test.
     */
    public function testFullUserLifecycle(): void
    {
        $roleA   = $this->insertRole('Employee');
        $roleB   = $this->insertRole('HRHead');
        $uid     = uniqid('lifecycle_');
        $service = $this->buildService();

        // 1. Create
        $userId = $service->create([
            'username'      => $uid,
            'account_email' => $uid . '@example.com',
            'password'      => 'Lifecycle1!',
            'role_id'       => $roleA,
        ], $this->actorId);

        $this->assertSame('Active', $service->findOrFail($userId)['status'], 'step 1: Active after create');

        // 2. Update role
        $service->update($userId, ['role_id' => $roleB], $this->actorId);
        $this->assertSame($roleB, (int) $service->findOrFail($userId)['role_id'], 'step 2: role updated');

        // 3. Deactivate
        $service->toggleStatus($userId, $this->actorId);
        $this->assertSame('Inactive', $service->findOrFail($userId)['status'], 'step 3: Inactive after toggle');

        // 4. Archive
        $service->archive($userId, $this->actorId);
        $this->assertSame('Archived', $service->findOrFail($userId)['status'], 'step 4: Archived');

        // 5. Toggle after archive must throw
        try {
            $service->toggleStatus($userId, $this->actorId);
            $this->fail('Expected RuntimeException when toggling archived user');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1); // confirmed exception
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function buildService(): UserService
    {
        // Reuse the shared PDO from IntegrationTestCase (same transaction).
        $config = $this->testDbConfig();
        $conn   = new class($config, $this->pdo) extends Connection {
            private \PDO $sharedPdo;
            public function __construct(array $cfg, \PDO $pdo) {
                parent::__construct($cfg);
                $this->sharedPdo = $pdo;
            }
            public function pdo(): \PDO { return $this->sharedPdo; }
        };

        return new UserService($conn);
    }

    /**
     * Build a minimal db config array matching IntegrationTestCase.
     * @return array<string,mixed>
     */
    private function testDbConfig(): array
    {
        return [
            'driver'   => 'mysql',
            'host'     => $_ENV['TEST_DB_HOST']     ?? getenv('TEST_DB_HOST')     ?: '127.0.0.1',
            'port'     => (int) ($_ENV['TEST_DB_PORT'] ?? getenv('TEST_DB_PORT')  ?: 3306),
            'database' => $_ENV['TEST_DB_NAME']     ?? getenv('TEST_DB_NAME')     ?: 'wbpms_test',
            'username' => $_ENV['TEST_DB_USER']     ?? getenv('TEST_DB_USER')     ?: 'root',
            'password' => $_ENV['TEST_DB_PASSWORD'] ?? getenv('TEST_DB_PASSWORD') ?: '',
            'charset'  => 'utf8mb4',
            'options'  => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ];
    }

    /** Create a fresh active user and return its user_id. */
    private function createActiveUser(): int
    {
        $roleId  = $this->insertRole('Employee');
        $uid     = uniqid('activeuser_');
        $service = $this->buildService();

        return $service->create([
            'username'      => $uid,
            'account_email' => $uid . '@example.com',
            'password'      => 'Password1!',
            'role_id'       => $roleId,
        ], $this->actorId);
    }
}
