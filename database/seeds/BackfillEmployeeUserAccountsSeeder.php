<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Backfill seeder: provision login accounts for employees that pre-date
 * the auto-provisioning feature (Requirement 13).
 *
 * Behaviour
 * ---------
 * - Finds every Active/Inactive employee that has no linked user account.
 * - Derives a username following the same firstname.lastname rule as
 *   UserService::deriveUsername().  Appends a numeric suffix on collision.
 * - Creates a users row with the shared temporary password `user12345`
 *   and requires_password_change = 1 so each employee must set their own
 *   password on first login.
 * - Uses a system placeholder for account_email (noemail+empN@lde.local)
 *   that HR can update later via User Management.
 * - Idempotent: already-linked employees are silently skipped.
 * - Prints a credential report to stdout for HR to distribute securely.
 *
 * Run once:
 *   vendor/bin/phinx seed:run -s BackfillEmployeeUserAccountsSeeder
 *
 * DO NOT run in production without distributing the credential list to HR
 * immediately afterwards.
 */
class BackfillEmployeeUserAccountsSeeder extends AbstractSeed
{
    /** Shared temporary password — every employee is forced to change it. */
    private const TEMP_PASSWORD = 'user12345';

    public function getDependencies(): array
    {
        return ['RolesAndRequestTypesSeeder', 'DemoUserSeeder'];
    }

    public function run(): void
    {
        $employeeRoleId = $this->fetchRoleId('Employee');
        $passwordHash   = password_hash(self::TEMP_PASSWORD, PASSWORD_DEFAULT);
        $now            = date('Y-m-d H:i:s');

        // Fetch all employees that have no active user account
        $employees = $this->fetchAll(
            "SELECT e.employee_id, e.first_name, e.last_name, e.status
               FROM employee e
              WHERE e.status IN ('Active', 'Inactive')
                AND NOT EXISTS (
                    SELECT 1 FROM users u
                     WHERE u.employee_id = e.employee_id
                       AND u.status != 'Archived'
                )
              ORDER BY e.employee_id"
        );

        if (empty($employees)) {
            echo "\n  All employees already have user accounts. Nothing to do.\n\n";
            return;
        }

        $provisioned = [];
        $skipped     = [];

        foreach ($employees as $emp) {
            $employeeId = (int) $emp['employee_id'];
            $username   = $this->deriveUniqueUsername(
                (string) $emp['first_name'],
                (string) $emp['last_name']
            );
            $email = 'noemail+emp' . $employeeId . '@lde.local';

            // Final guard: username must not exist (race-safe check)
            $conflict = $this->fetchRow(
                "SELECT user_id FROM users WHERE username = '" . $this->escape($username) . "'"
            );
            if ($conflict !== false) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'name'        => $emp['first_name'] . ' ' . $emp['last_name'],
                    'reason'      => "username '{$username}' still conflicted after derivation",
                ];
                continue;
            }

            $this->execute("
                INSERT INTO users
                    (employee_id, role_id, username, account_email,
                     password_hash, status, requires_password_change,
                     created_at, updated_at)
                VALUES
                    ({$employeeId}, {$employeeRoleId},
                     '" . $this->escape($username) . "',
                     '" . $this->escape($email) . "',
                     '" . $this->escape($passwordHash) . "',
                     'Active', 1,
                     '{$now}', '{$now}')
            ");

            $provisioned[] = [
                'employee_id' => $employeeId,
                'name'        => $emp['first_name'] . ' ' . $emp['last_name'],
                'status'      => $emp['status'],
                'username'    => $username,
            ];
        }

        // ---------------------------------------------------------------
        // Credential report
        // ---------------------------------------------------------------
        $count = count($provisioned);
        echo "\n";
        echo "  ╔══════════════════════════════════════════════════════════════╗\n";
        echo "  ║       EMPLOYEE ACCOUNT BACKFILL — CREDENTIAL REPORT         ║\n";
        echo "  ╠══════════════════════════════════════════════════════════════╣\n";
        echo "  ║  Temp password (all accounts): " . str_pad(self::TEMP_PASSWORD, 31) . "║\n";
        echo "  ║  Each employee MUST change their password on first login.    ║\n";
        echo "  ╠══════════╦═══════════════════════╦══════════════════════════╣\n";
        echo "  ║  Emp ID  ║  Name                 ║  Username                ║\n";
        echo "  ╠══════════╬═══════════════════════╬══════════════════════════╣\n";

        foreach ($provisioned as $row) {
            printf(
                "  ║  %-8s║  %-21s║  %-24s║\n",
                $row['employee_id'],
                mb_substr($row['name'], 0, 21),
                mb_substr($row['username'], 0, 24)
            );
        }

        echo "  ╚══════════╩═══════════════════════╩══════════════════════════╝\n";
        echo "  Provisioned: {$count} account(s)\n";

        if (!empty($skipped)) {
            echo "\n  SKIPPED (manual review required):\n";
            foreach ($skipped as $row) {
                echo "    - Emp #{$row['employee_id']} {$row['name']}: {$row['reason']}\n";
            }
        }

        echo "\n  IMPORTANT: Distribute credentials to employees securely.\n";
        echo "  Account emails are placeholders — update via User Management.\n\n";
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Derive a unique username from first and last name.
     * Mirrors UserService::deriveUsername() logic exactly.
     */
    private function deriveUniqueUsername(string $firstName, string $lastName): string
    {
        $slug = static function (string $s): string {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($transliterated === false) {
                $transliterated = $s;
            }
            return preg_replace(
                '/[^a-z0-9.]/',
                '',
                str_replace([' ', '-', "'"], '', strtolower($transliterated))
            ) ?? '';
        };

        $base = $slug($firstName) . '.' . $slug($lastName);
        if ($base === '.') {
            $base = 'employee';
        }
        $base = substr($base, 0, 45);

        $candidate = $base;
        $suffix    = 2;
        while (true) {
            $exists = $this->fetchRow(
                "SELECT user_id FROM users WHERE username = '" . $this->escape($candidate) . "'"
            );
            if ($exists === false) {
                break;
            }
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function fetchRoleId(string $roleName): int
    {
        $row = $this->fetchRow(
            "SELECT role_id FROM `role` WHERE role_name = '" . $this->escape($roleName) . "'"
        );
        if ($row === false || !isset($row['role_id'])) {
            throw new \RuntimeException("Role '{$roleName}' not found. Run RolesAndRequestTypesSeeder first.");
        }

        return (int) $row['role_id'];
    }

    /** Minimal SQL-safe escaping for string values in raw queries. */
    private function escape(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }
}
