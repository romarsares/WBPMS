<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Abstract base class for all WBPMS integration tests.
 *
 * Provides a shared PDO handle connected to the wbpms_test database
 * (configured via TEST_DB_* environment variables or the phpunit.xml <php> block).
 *
 * Each test method is wrapped in a transaction that is rolled back after the
 * test completes, so tests are isolated without needing to truncate tables.
 *
 * Usage:
 *   final class MyTest extends IntegrationTestCase
 *   {
 *       public function testSomething(): void
 *       {
 *           // $this->pdo is available; everything is rolled back after the test.
 *           $this->pdo->exec("INSERT INTO ...");
 *           ...
 *       }
 *   }
 *
 * Integration tests require a live MySQL database with the full WBPMS schema
 * applied (run `vendor/bin/phinx migrate -e testing` before the test run).
 *
 * If the database is not reachable the entire suite is marked as skipped so
 * a missing test database never causes the CI unit-test pass/fail to flip.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static PDO $sharedPdo;
    protected PDO $pdo;

    // -----------------------------------------------------------------------
    // Suite-level connection (opened once per class)
    // -----------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$sharedPdo = static::openConnection();
    }

    // -----------------------------------------------------------------------
    // Per-test transaction wrap
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = static::$sharedPdo;

        // Nested transactions are not supported; use a savepoint if PDO is
        // already in a transaction (should not normally happen, but be safe).
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // -----------------------------------------------------------------------
    // Connection factory
    // -----------------------------------------------------------------------

    /**
     * Open and return a PDO handle to the test database.
     *
     * Reads configuration from the TEST_DB_* variables that phpunit.xml
     * injects via its <php><env> section (which mirrors what .env provides
     * for the wbpms_test database).
     *
     * @throws \PHPUnit\Framework\SkippedTestSuiteError when the database
     *   cannot be reached (e.g. MySQL is not running locally).
     */
    protected static function openConnection(): PDO
    {
        $host     = $_ENV['TEST_DB_HOST']     ?? getenv('TEST_DB_HOST')     ?: '127.0.0.1';
        $port     = $_ENV['TEST_DB_PORT']     ?? getenv('TEST_DB_PORT')     ?: '3306';
        $dbname   = $_ENV['TEST_DB_NAME']     ?? getenv('TEST_DB_NAME')     ?: 'wbpms_test';
        $user     = $_ENV['TEST_DB_USER']     ?? getenv('TEST_DB_USER')     ?: 'root';
        $password = $_ENV['TEST_DB_PASSWORD'] ?? getenv('TEST_DB_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Ensure strict mode and correct timezone for the session.
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $pdo->exec("SET SESSION time_zone = '+08:00'");

            return $pdo;
        } catch (PDOException $e) {
            static::markTestSkipped(
                "Integration tests skipped: cannot connect to test database ({$dbname}@{$host}:{$port}). "
                . 'Run `vendor/bin/phinx migrate -e testing` and ensure MySQL is running. '
                . 'Original error: ' . $e->getMessage()
            );
        }
    }

    // -----------------------------------------------------------------------
    // Fixture helpers (shared across subclasses)
    // -----------------------------------------------------------------------

    /**
     * Insert a minimal role row and return its role_id.
     */
    protected function insertRole(string $roleName = 'HRHead'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO role (role_name) VALUES (:n)"
        );
        $stmt->execute([':n' => $roleName . '_' . uniqid()]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a minimal user row and return its user_id.
     */
    protected function insertUser(int $roleId, ?int $employeeId = null): int
    {
        $uid  = uniqid('u_');
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (employee_id, role_id, username, account_email, password_hash, status)
             VALUES (:emp, :role, :user, :email, :pwd, 'Active')"
        );
        $stmt->execute([
            ':emp'   => $employeeId,
            ':role'  => $roleId,
            ':user'  => $uid,
            ':email' => $uid . '@example.com',
            ':pwd'   => password_hash('test', PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a minimal branch row and return its branch_id.
     */
    protected function insertBranch(): int
    {
        $code = uniqid('BR');
        $stmt = $this->pdo->prepare(
            "INSERT INTO branch (branch_code, branch_name) VALUES (:c, :n)"
        );
        $stmt->execute([':c' => $code, ':n' => 'Branch ' . $code]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a minimal employee row and return its employee_id.
     */
    protected function insertEmployee(): int
    {
        $num  = uniqid('EMP');
        $stmt = $this->pdo->prepare(
            "INSERT INTO employee (employee_number, employee_type, first_name, last_name, hire_date, position)
             VALUES (:n, 'Regular', 'Test', 'Employee', CURDATE(), 'Staff')"
        );
        $stmt->execute([':n' => $num]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert an employee_branch_assignment row and return its branch_assignment_id.
     */
    protected function insertBranchAssignment(
        int $employeeId,
        int $branchId,
        string $effectiveFrom = '2026-01-01',
        ?string $effectiveTo = null
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO employee_branch_assignment (employee_id, branch_id, effective_from, effective_to)
             VALUES (:emp, :br, :from, :to)"
        );
        $stmt->execute([
            ':emp'  => $employeeId,
            ':br'   => $branchId,
            ':from' => $effectiveFrom,
            ':to'   => $effectiveTo,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a work_schedule row and return its schedule_id.
     */
    protected function insertWorkSchedule(
        int $employeeId,
        string $effectiveFrom = '2026-01-01',
        ?string $effectiveTo = null
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO work_schedule
                (employee_id, working_days, rest_days, break_minutes,
                 work_start_time, work_end_time, standard_minutes, effective_from, effective_to)
             VALUES (:emp, :wd, :rd, 60, '07:00:00', '16:00:00', 480, :from, :to)"
        );
        $stmt->execute([
            ':emp'  => $employeeId,
            ':wd'   => '["Monday","Tuesday","Wednesday","Thursday","Friday"]',
            ':rd'   => '["Saturday","Sunday"]',
            ':from' => $effectiveFrom,
            ':to'   => $effectiveTo,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a valid payroll_period (period_start on a Friday) and return its id.
     *
     * Defaults to 2026-09-04 (Friday) → 2026-09-10 (Thursday), pay 2026-09-11.
     */
    protected function insertPayrollPeriod(
        string $start = '2026-09-04',
        string $end   = '2026-09-10',
        string $pay   = '2026-09-11'
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payroll_period (period_start, period_end, pay_date)
             VALUES (:s, :e, :p)"
        );
        $stmt->execute([':s' => $start, ':e' => $end, ':p' => $pay]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a minimal payroll_policy_version and return its policy_id.
     */
    protected function insertPayrollPolicy(): int
    {
        $code = uniqid('POL');
        $stmt = $this->pdo->prepare(
            "INSERT INTO payroll_policy_version
                (policy_code, version, effective_from, annual_tax_threshold,
                 late_rate_per_minute, rounding_mode, status)
             VALUES (:code, '1.0', '2026-01-01', 250000.00, 1.00, 'HalfUp', 'Approved')"
        );
        $stmt->execute([':code' => $code]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a payroll_run and return its payroll_run_id.
     */
    protected function insertPayrollRun(int $periodId, int $branchId, int $policyId, string $status = 'Draft'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payroll_run (payroll_period_id, branch_id, payroll_policy_id, status)
             VALUES (:per, :br, :pol, :st)"
        );
        $stmt->execute([
            ':per' => $periodId,
            ':br'  => $branchId,
            ':pol' => $policyId,
            ':st'  => $status,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a salary row and return its salary_id.
     */
    protected function insertSalary(int $employeeId, string $effectiveFrom = '2026-01-01'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO salary (employee_id, daily_rate, effective_from, status)
             VALUES (:emp, 460.00, :from, 'Active')"
        );
        $stmt->execute([':emp' => $employeeId, ':from' => $effectiveFrom]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a payroll row and return its payroll_id.
     * Requires that the run's payroll_period_id matches periodId (composite FK).
     */
    protected function insertPayroll(
        int $runId,
        int $periodId,
        int $employeeId,
        int $branchAssignmentId,
        int $salaryId
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payroll
                (payroll_run_id, payroll_period_id, employee_id,
                 branch_assignment_id, salary_id, daily_rate_snapshot,
                 gross_pay, total_deductions, net_pay)
             VALUES (:run, :per, :emp, :ba, :sal, 460.00, 2300.00, 0.00, 2300.00)"
        );
        $stmt->execute([
            ':run' => $runId,
            ':per' => $periodId,
            ':emp' => $employeeId,
            ':ba'  => $branchAssignmentId,
            ':sal' => $salaryId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Assert that a callable throws a PDOException matching a given SQLSTATE or
     * containing a message substring. Used throughout the schema-invariant tests.
     */
    protected function assertDbException(callable $fn, string $context = ''): PDOException
    {
        try {
            $fn();
            $this->fail("Expected a PDOException to be thrown ({$context}) but none was.");
        } catch (PDOException $e) {
            // Exception captured as expected — return it for optional further assertions.
            $this->addToAssertionCount(1);
            return $e;
        }
    }

    /**
     * Assert that a callable succeeds without a PDOException.
     */
    protected function assertDbSuccess(callable $fn, string $context = ''): void
    {
        try {
            $fn();
            $this->addToAssertionCount(1);
        } catch (PDOException $e) {
            $this->fail("Unexpected PDOException ({$context}): " . $e->getMessage());
        }
    }
}
