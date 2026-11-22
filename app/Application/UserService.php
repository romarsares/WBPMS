<?php

declare(strict_types=1);

namespace Wbpms\Application;

use PDO;
use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;

/**
 * UserService — application service for user account management.
 *
 * Implements REQ004–REQ008:
 *   REQ004  List users with role and status filters
 *   REQ005  Create a user account (HR Head or Business Owner initiates)
 *   REQ006  Update username, email, role assignment
 *   REQ007  Activate / Deactivate a user (toggle Active ↔ Inactive)
 *   REQ008  Archive a user (Archived — soft delete, never hard delete)
 *
 * Business rules:
 *   - Usernames and account_email must be unique across the users table.
 *   - Passwords are hashed with password_hash(PASSWORD_DEFAULT).
 *   - An Archived user cannot be re-activated; status transitions are
 *     Active → Inactive → Active (toggle) and any → Archived (one-way).
 *   - Employee-role accounts are provisioned and linked only through
 *     Employee Management; User Management cannot change that link.
 *   - All mutations are audited via audit_logs when an acting_user_id is supplied.
 *
 * No SQL belongs in controllers; all queries live here.
 */
final class UserService
{
    /**
     * Page size for paginated user lists.
     */
    private const PAGE_SIZE = 10;

    public function __construct(private Connection $connection) {}

    // -----------------------------------------------------------------------
    // REQ004 — List
    // -----------------------------------------------------------------------

    /**
     * Build WHERE clause and parameters from filter parameters.
     *
     * @param bool   $includeArchived  Include Archived rows (for admin view).
     * @param string $position         Restrict to users linked to employees with this position title.
     * @param string $username         LIKE filter on u.username (partial match).
     * @param string $status           Exact filter on u.status ('Active'|'Inactive'|'Archived'|'').
     * @return array{where: string, params: array<string, mixed>}
     */
    private function buildWhere(
        bool   $includeArchived = false,
        string $position        = '',
        string $username        = '',
        string $status          = '',
    ): array {
        $where  = [];
        $params = [];

        if (!$includeArchived && $status === '') {
            // No explicit status filter — hide archived by default
            $where[] = "u.status != 'Archived'";
        }

        if ($status !== '') {
            $where[]           = 'u.status = :status';
            $params[':status'] = ucfirst(strtolower($status));
        }

        if ($username !== '') {
            $where[]             = 'u.username LIKE :username';
            $params[':username'] = '%' . $username . '%';
        }

        if ($position !== '') {
            $where[]             = 'e.position = :position';
            $params[':position'] = $position;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        return ['where' => $whereClause, 'params' => $params];
    }

    /**
     * Return all users with their role name (non-paginated, for backward compatibility).
     *
     * @param bool   $includeArchived  Include Archived rows (for admin view).
     * @param string $position         Restrict to users linked to employees with this position title.
     * @param string $username         LIKE filter on u.username (partial match).
     * @param string $status           Exact filter on u.status ('Active'|'Inactive'|'Archived'|'').
     * @return list<array<string,mixed>>
     */
    public function list(
        bool   $includeArchived = false,
        string $position        = '',
        string $username        = '',
        string $status          = '',
    ): array {
        $built = $this->buildWhere($includeArchived, $position, $username, $status);
        $whereClause = $built['where'];
        $params = $built['params'];

        $sql = "SELECT u.user_id, u.username, u.account_email, u.status,
                       u.employee_id, u.created_at, u.updated_at,
                       r.role_id, r.role_name,
                       (SELECT GROUP_CONCAT(r2.role_name ORDER BY r2.role_name SEPARATOR ', ')
                          FROM user_role ur2
                          JOIN role r2 ON r2.role_id = ur2.role_id
                         WHERE ur2.user_id = u.user_id) AS role_names,
                       CONCAT(e.last_name, ', ', e.first_name) AS employee_name
                  FROM users u
                  JOIN role r   ON r.role_id = u.role_id
                  LEFT JOIN employee e ON e.employee_id = u.employee_id
                {$whereClause}
                 ORDER BY u.created_at DESC";

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Return a paginated list of users with their role name.
     *
     * @param int    $page             1-based page number
     * @param bool   $includeArchived  Include Archived rows (for admin view).
     * @param string $position         Restrict to users linked to employees with this position title.
     * @param string $username         LIKE filter on u.username (partial match).
     * @param string $status           Exact filter on u.status ('Active'|'Inactive'|'Archived'|'').
     * @return list<array<string,mixed>>
     */
    public function findPaginated(
        int    $page = 1,
        int    $pageSize = self::PAGE_SIZE,
        bool   $includeArchived = false,
        string $position        = '',
        string $username        = '',
        string $status          = '',
    ): array {
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;

        $built = $this->buildWhere($includeArchived, $position, $username, $status);
        $whereClause = $built['where'];
        $params = $built['params'];

        $sql = "SELECT u.user_id, u.username, u.account_email, u.status,
                       u.employee_id, u.created_at, u.updated_at,
                       r.role_id, r.role_name,
                       (SELECT GROUP_CONCAT(r2.role_name ORDER BY r2.role_name SEPARATOR ', ')
                          FROM user_role ur2
                          JOIN role r2 ON r2.role_id = ur2.role_id
                         WHERE ur2.user_id = u.user_id) AS role_names,
                       CONCAT(e.last_name, ', ', e.first_name) AS employee_name
                  FROM users u
                  JOIN role r   ON r.role_id = u.role_id
                  LEFT JOIN employee e ON e.employee_id = u.employee_id
                {$whereClause}
                 ORDER BY u.created_at DESC
                 LIMIT {$offset}, {$pageSize}";

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Count total users matching the given filters (for pagination).
     *
     * @param bool   $includeArchived  Include Archived rows (for admin view).
     * @param string $position         Restrict to users linked to employees with this position title.
     * @param string $username         LIKE filter on u.username (partial match).
     * @param string $status           Exact filter on u.status ('Active'|'Inactive'|'Archived'|'').
     * @return int
     */
    public function countFiltered(
        bool   $includeArchived = false,
        string $position        = '',
        string $username        = '',
        string $status          = '',
    ): int {
        $built = $this->buildWhere($includeArchived, $position, $username, $status);
        $whereClause = $built['where'];
        $params = $built['params'];

        $sql = "SELECT COUNT(*) FROM users u
                JOIN role r ON r.role_id = u.role_id
                LEFT JOIN employee e ON e.employee_id = u.employee_id
                {$whereClause}";

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Return the configured page size.
     *
     * @return int
     */
    public function pageSize(): int
    {
        return self::PAGE_SIZE;
    }

    // -----------------------------------------------------------------------
    // Requirement 13 — Auto-provision an Employee user account
    // -----------------------------------------------------------------------

    /**
     * Derive a unique username from first and last name.
     *
     * Format: firstname.lastname (lowercase ASCII, spaces/hyphens stripped).
     * If the base candidate is taken, appends an incrementing suffix:
     *   juan.delacruz → juan.delacruz2 → juan.delacruz3 …
     *
     * Called by EmployeeController::store() before the transaction opens.
     */
    public function deriveUsername(string $firstName, string $lastName): string
    {
        $slug = static fn(string $s): string =>
            preg_replace(
                '/[^a-z0-9.]/',
                '',
                str_replace([' ', '-', "'"], '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s))
            ) ?? '';

        $base = $slug($firstName) . '.' . $slug($lastName);
        if ($base === '.') {
            $base = 'employee';
        }

        // Trim to fit column limit (50) leaving room for a suffix
        $base = substr($base, 0, 45);

        $pdo  = $this->connection->pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");

        $candidate = $base;
        $suffix    = 2;
        while (true) {
            $stmt->execute([':u' => $candidate]);
            if ((int) $stmt->fetchColumn() === 0) {
                break;
            }
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Generate a cryptographically random temporary password.
     *
     * Produces a 12-character mixed-case alphanumeric string that satisfies
     * the minimum 8-character policy enforced by validateCreateInput().
     */
    public function generateTemporaryPassword(): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len   = strlen($chars);
        $out   = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $chars[random_int(0, $len - 1)];
        }
        return $out;
    }

    /**
     * Provision an Employee user account inside an already-open transaction.
     *
     * Must be called with the *same* PDO connection that owns the outer
     * transaction so that employee creation + account creation are atomic.
     *
     * Returns the new user_id.
     *
     * Requirement 13, AC1–AC4, AC7, AC11.
     *
     * @throws RuntimeException on role lookup failure or duplicate username
     */
    public function provisionForEmployee(
        PDO    $pdo,
        int    $employeeId,
        string $username,
        string $temporaryPassword,
        int    $actingUserId,
        string $accountEmail = ''
    ): int {
        // Resolve the Employee role_id
        $roleStmt = $pdo->prepare("SELECT role_id FROM role WHERE role_name = 'Employee' LIMIT 1");
        $roleStmt->execute();
        $roleId = $roleStmt->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException("Role 'Employee' not found in the role table.");
        }
        $roleId = (int) $roleId;

        // Guard: employee must not already have an active account
        $dupStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE employee_id = :emp AND status != 'Archived'"
        );
        $dupStmt->execute([':emp' => $employeeId]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            throw new RuntimeException('This employee already has an active user account.');
        }

        $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $ins = $pdo->prepare(
            "INSERT INTO users
                (employee_id, role_id, username, account_email,
                 password_hash, status, requires_password_change)
             VALUES
                (:emp, :role, :user, :email,
                 :pwd, 'Active', 1)"
        );
        $ins->execute([
            ':emp'   => $employeeId,
            ':role'  => $roleId,
            ':user'  => $username,
            // Use the employee's real email as the login credential.
            // Falls back to a unique system placeholder only if somehow empty.
            ':email' => $accountEmail !== '' ? $accountEmail : ('noemail+emp' . $employeeId . '@lde.local'),
            ':pwd'   => $hash,
        ]);

        $newUserId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute([':user_id' => $newUserId, ':role_id' => $roleId]);

        // Audit inside the same transaction
        $pdo->prepare(
            "INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected,
                 record_id, description, action_at)
             VALUES
                (:uid, 'user_provisioned', 'user_provisioned', 'users',
                 :rid, :desc, NOW())"
        )->execute([
            ':uid'  => $actingUserId,
            ':rid'  => $newUserId,
            ':desc' => "Auto-provisioned Employee account '{$username}' for employee_id={$employeeId}",
        ]);

        return $newUserId;
    }

    /**
     * Clear the requires_password_change flag after the employee sets their
     * own password.  Called by AuthController::changePassword().
     *
     * Requirement 13, AC6.
     */
    public function clearPasswordChangeFlag(int $userId): void
    {
        $this->connection->pdo()
            ->prepare("UPDATE users SET requires_password_change = 0 WHERE user_id = :id")
            ->execute([':id' => $userId]);
    }

    // -----------------------------------------------------------------------
    // REQ005 — Create
    // -----------------------------------------------------------------------

    /**
     * Create a new user account.
     *
     * @param array{
     *   username:       string,
     *   account_email:  string,
     *   password:       string,
     *   role_id:        int,
     * } $data
     *
     * @throws RuntimeException on duplicate username/email or invalid role
     */
    public function create(array $data, int $actingUserId): int
    {
        $username     = trim($data['username'] ?? '');
        $email        = trim($data['account_email'] ?? '');
        $password     = $data['password'] ?? '';
        $roleId       = (int) ($data['role_id'] ?? 0);
        $employeeId   = isset($data['employee_id']) && $data['employee_id'] !== ''
                        ? (int) $data['employee_id'] : null;

        $this->validateCreateInput($username, $email, $password, $roleId);
        $roleName = $this->roleNameForId($roleId);
        if ($roleName === 'Employee') {
            throw new RuntimeException('Employee accounts are created automatically from Employee Management.');
        }

        // A matching email represents the same person.  Grant the requested
        // role to that one account rather than creating a second ambiguous
        // login record with a separate password and account state.
        $existingUserId = $this->userIdByEmail($email);
        if ($existingUserId !== null) {
            $this->grantRole($existingUserId, $roleId, $actingUserId);
            return $existingUserId;
        }

        $this->assertUniqueUsername($username);
        $this->assertUniqueEmail($email);
        if ($employeeId !== null) {
            throw new RuntimeException('Only automatically provisioned Employee accounts can be linked to an employee profile.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $userId = $this->connection->transaction(function (PDO $pdo) use (
            $username, $email, $hash, $roleId, $employeeId, $actingUserId
        ): int {
            $stmt = $pdo->prepare(
                "INSERT INTO users (employee_id, role_id, username, account_email, password_hash, status)
                 VALUES (:emp, :role, :user, :email, :pwd, 'Active')"
            );
            $stmt->execute([
                ':emp'   => $employeeId,
                ':role'  => $roleId,
                ':user'  => $username,
                ':email' => $email,
                ':pwd'   => $hash,
            ]);
            $newId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)')
                ->execute([':user_id' => $newId, ':role_id' => $roleId]);

            $this->insertAudit($pdo, $actingUserId, 'user_created', 'users', $newId,
                "Created user '{$username}' with role_id={$roleId}");

            return $newId;
        });

        return $userId;
    }

    // -----------------------------------------------------------------------
    // REQ006 — Update
    // -----------------------------------------------------------------------

    /**
     * Update username, email, and/or role for an existing user.
     *
     * @param array{
     *   username?:      string,
     *   account_email?: string,
     *   role_id?:       int,
     * } $data
     *
     * @throws RuntimeException when user not found, duplicate, or invalid role
     */
    public function update(int $userId, array $data, int $actingUserId): void
    {
        $user = $this->findOrFail($userId);

        $username   = trim($data['username']      ?? $user['username']);
        $email      = trim($data['account_email'] ?? $user['account_email']);
        $roleId     = isset($data['role_id']) ? (int) $data['role_id'] : (int) $user['role_id'];
        $currentEmployeeId = $user['employee_id'] !== null ? (int) $user['employee_id'] : null;
        $employeeId        = $currentEmployeeId;

        if ($username === '') {
            throw new RuntimeException('Username cannot be empty.');
        }
        if ($email === '') {
            throw new RuntimeException('Email cannot be empty.');
        }

        if ($username !== $user['username']) {
            $this->assertUniqueUsername($username, $userId);
        }
        if ($email !== $user['account_email']) {
            $this->assertUniqueEmail($email, $userId);
        }
        $roleName = $this->roleNameForId($roleId);
        $status   = (string) $user['status'];
        if ($roleName === 'Employee' && (string) $user['role_name'] !== 'Employee') {
            throw new RuntimeException('Employee accounts are created automatically from Employee Management.');
        }
        if ((string) $user['role_name'] === 'Employee' && $roleName !== 'Employee') {
            throw new RuntimeException('Employee account roles are managed automatically with the employee profile.');
        }
        if (array_key_exists('employee_id', $data)
            && ($data['employee_id'] === '' || $data['employee_id'] === null
                ? null : (int) $data['employee_id']) !== $currentEmployeeId) {
            throw new RuntimeException('Employee account links are managed automatically from Employee Management.');
        }

        $this->connection->transaction(function (PDO $pdo) use (
            $userId, $username, $email, $roleId, $employeeId, $status, $user, $actingUserId
        ): void {
            $stmt = $pdo->prepare(
                "UPDATE users
                    SET username = :user, account_email = :email,
                        role_id  = :role, employee_id   = :emp, status = :status
                  WHERE user_id  = :id"
            );
            $stmt->execute([
                ':user'  => $username,
                ':email' => $email,
                ':role'  => $roleId,
                ':emp'   => $employeeId,
                ':status'=> $status,
                ':id'    => $userId,
            ]);

            // Keep the legacy primary-role column for backward-compatible
            // reporting, while making the selected role an actual membership.
            $pdo->prepare('INSERT IGNORE INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)')
                ->execute([':user_id' => $userId, ':role_id' => $roleId]);

            // Sync users.account_email → employee.email for the linked employee profile.
            // When HR changes the login email from User Management the employee's
            // contact email must also be updated so both records stay consistent.
            if ($employeeId !== null && $email !== (string) $user['account_email']) {
                $pdo->prepare(
                    "UPDATE employee SET email = :email WHERE employee_id = :emp"
                )->execute([':email' => $email, ':emp' => $employeeId]);
            }

            // Email OTP gate: when the account_email changes, reset is_email_verified
            // so the employee must re-confirm the new address on their next login.
            if ($email !== (string) $user['account_email']) {
                $pdo->prepare(
                    "UPDATE users SET is_email_verified = 0, updated_at = UTC_TIMESTAMP()
                      WHERE user_id = :id"
                )->execute([':id' => $userId]);
            }

            $this->insertAudit($pdo, $actingUserId, 'user_updated', 'users', $userId,
                "Updated user_id={$userId}: username='{$username}', role_id={$roleId}, status='{$status}'");
        });
    }

    // -----------------------------------------------------------------------
    // REQ007 — Toggle status (Active ↔ Inactive)
    // -----------------------------------------------------------------------

    /**
     * Toggle a user's status between Active and Inactive.
     * Archived users cannot be toggled.
     *
     * @throws RuntimeException when user is Archived or not found
     */
    public function toggleStatus(int $userId, int $actingUserId): string
    {
        $user = $this->findOrFail($userId);

        if ($user['status'] === 'Archived') {
            throw new RuntimeException('Archived users cannot be activated or deactivated.');
        }

        $newStatus = $user['status'] === 'Active' ? 'Inactive' : 'Active';

        if (
            $newStatus === 'Active'
            && AuthenticationPolicy::isUnlinkedEmployee(
                (string) $user['role_name'],
                $user['employee_id'] !== null ? (int) $user['employee_id'] : null
            )
        ) {
            throw new RuntimeException('Link this Employee account to an employee profile before activating it.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($userId, $newStatus, $actingUserId): void {
            $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :id")
                ->execute([':s' => $newStatus, ':id' => $userId]);

            $this->insertAudit($pdo, $actingUserId, 'user_status_changed', 'users', $userId,
                "Status changed to '{$newStatus}' for user_id={$userId}");
        });

        return $newStatus;
    }

    // -----------------------------------------------------------------------
    // REQ008 — Archive
    // -----------------------------------------------------------------------

    /**
     * Archive a user account (soft delete — reversible via unarchive()).
     *
     * @throws RuntimeException when user is already Archived
     */
    public function archive(int $userId, int $actingUserId): void
    {
        if ($userId === $actingUserId) {
            throw new RuntimeException('You cannot archive your own account.');
        }

        $user = $this->findOrFail($userId);

        if ($user['status'] === 'Archived') {
            throw new RuntimeException('User is already archived.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($userId, $actingUserId, $user): void {
            $pdo->prepare("UPDATE users SET status = 'Archived', updated_at = NOW() WHERE user_id = :id")
                ->execute([':id' => $userId]);

            // If this user is linked to an employee, mirror the Archived status
            // on the employee record so it reflects correctly in Employee Management.
            // (Full lifecycle closure — branch/schedule/salary — requires the
            // controlled Employee archive workflow; this keeps statuses in sync.)
            if (!empty($user['employee_id'])) {
                $pdo->prepare(
                    "UPDATE employee SET status = 'Archived', updated_at = NOW()
                      WHERE employee_id = :emp_id AND status != 'Archived'"
                )->execute([':emp_id' => $user['employee_id']]);
            }

            $this->insertAudit($pdo, $actingUserId, 'user_archived', 'users', $userId,
                "Archived user_id={$userId}");
        });
    }

    // -----------------------------------------------------------------------
    // REQ008-ext — Unarchive (reverse of archive, Business Owner only)
    // -----------------------------------------------------------------------

    /**
     * Restore an Archived user to Inactive status.
     *
     * The linked employee record (if any) is also restored to Inactive so it
     * appears in Employee Management again. Full reactivation (Active) still
     * requires an explicit Activate action.
     *
     * @throws RuntimeException when user is not Archived or not found
     */
    public function unarchive(int $userId, int $actingUserId): void
    {
        $user = $this->findOrFail($userId);

        if ($user['status'] !== 'Archived') {
            throw new RuntimeException('Only archived users can be unarchived.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($userId, $actingUserId, $user): void {
            $pdo->prepare("UPDATE users SET status = 'Inactive', updated_at = NOW() WHERE user_id = :id")
                ->execute([':id' => $userId]);

            // Mirror the restore on the linked employee record if it is still Archived.
            if (!empty($user['employee_id'])) {
                $pdo->prepare(
                    "UPDATE employee SET status = 'Inactive', updated_at = NOW()
                      WHERE employee_id = :emp_id AND status = 'Archived'"
                )->execute([':emp_id' => $user['employee_id']]);
            }

            $this->insertAudit($pdo, $actingUserId, 'user_unarchived', 'users', $userId,
                "Unarchived user_id={$userId} — status restored to Inactive");
        });
    }

    // -----------------------------------------------------------------------
    // Lookup helpers
    // -----------------------------------------------------------------------

    /**
     * Return a single user row or throw if not found.
     *
     * @return array<string,mixed>
     * @throws RuntimeException
     */
    public function findOrFail(int $userId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT u.user_id, u.username, u.account_email, u.status,
                    u.employee_id, u.role_id, u.created_at,
                    r.role_name
               FROM users u
               JOIN role r ON r.role_id = u.role_id
              WHERE u.user_id = :id"
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException("User #{$userId} not found.");
        }

        return $row;
    }

    /**
     * Return all roles for select dropdowns.
     *
     * @return list<array{role_id: int, role_name: string}>
     */
    public function roles(): array
    {
        return $this->connection->pdo()
            ->query("SELECT role_id, role_name FROM role ORDER BY role_name")
            ->fetchAll();
    }

    /** @return list<string> */
    public function roleNamesForUser(int $userId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT r.role_name\n'
            . '  FROM user_role ur\n'
            . '  JOIN role r ON r.role_id = ur.role_id\n'
            . ' WHERE ur.user_id = :user_id\n'
            . ' ORDER BY r.role_name'
        );
        $stmt->execute([':user_id' => $userId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** Add a role grant to an existing account; no duplicate email/account is created. */
    public function grantRole(int $userId, int $roleId, int $actingUserId): void
    {
        $roleName = $this->roleNameForId($roleId);
        if ($roleName === 'Employee') {
            throw new RuntimeException('Employee roles are granted automatically through Employee Management.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($userId, $roleId, $roleName, $actingUserId): void {
            $exists = $pdo->prepare('SELECT 1 FROM users WHERE user_id = :user_id');
            $exists->execute([':user_id' => $userId]);
            if ($exists->fetchColumn() === false) {
                throw new RuntimeException('User account was not found.');
            }

            $stmt = $pdo->prepare('INSERT IGNORE INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)');
            $stmt->execute([':user_id' => $userId, ':role_id' => $roleId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('This account already has the ' . $roleName . ' role.');
            }
            $this->insertAudit($pdo, $actingUserId, 'user_role_granted', 'user_role', $userId,
                "Granted {$roleName} role to user_id={$userId}");
        });
    }

    /**
     * Return employees that are not yet linked to a user account (for create form).
     *
     * @return list<array<string,mixed>>
     */
    public function unlinkedEmployees(): array
    {
        return $this->connection->pdo()->query(
            "SELECT e.employee_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number
               FROM employee e
              WHERE e.status != 'Archived'
                AND NOT EXISTS (
                    SELECT 1 FROM users u
                    WHERE u.employee_id = e.employee_id
                      AND u.status != 'Archived'
                )
              ORDER BY e.last_name, e.first_name"
        )->fetchAll();
    }

    // -----------------------------------------------------------------------
    // Private guards
    // -----------------------------------------------------------------------

    private function validateCreateInput(
        string $username, string $email, string $password, int $roleId
    ): void {
        if ($username === '') {
            throw new RuntimeException('Username is required.');
        }
        if (strlen($username) > 50) {
            throw new RuntimeException('Username must be 50 characters or fewer.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }
        if ($roleId <= 0) {
            throw new RuntimeException('A valid role must be selected.');
        }
    }

    private function assertUniqueUsername(string $username, ?int $excludeId = null): void
    {
        $sql  = "SELECT COUNT(*) FROM users WHERE username = :u";
        $bind = [':u' => $username];
        if ($excludeId !== null) {
            $sql  .= " AND user_id != :id";
            $bind[':id'] = $excludeId;
        }
        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($bind);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException("Username '{$username}' is already taken.");
        }
    }

    private function assertUniqueEmail(string $email, ?int $excludeId = null): void
    {
        $sql  = "SELECT COUNT(*) FROM users WHERE account_email = :e";
        $bind = [':e' => $email];
        if ($excludeId !== null) {
            $sql  .= " AND user_id != :id";
            $bind[':id'] = $excludeId;
        }
        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($bind);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException("Email '{$email}' is already registered.");
        }
    }

    private function userIdByEmail(string $email): ?int
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT user_id FROM users WHERE account_email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $userId = $stmt->fetchColumn();
        return $userId === false ? null : (int) $userId;
    }

    private function roleNameForId(int $roleId): string
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT role_name FROM role WHERE role_id = :id"
        );
        $stmt->execute([':id' => $roleId]);
        $roleName = $stmt->fetchColumn();
        if ($roleName === false) {
            throw new RuntimeException("Role #{$roleId} does not exist.");
        }

        return (string) $roleName;
    }

    // -----------------------------------------------------------------------
    // Audit helper
    // -----------------------------------------------------------------------

    private function insertAudit(
        PDO    $pdo,
        int    $actingUserId,
        string $eventType,
        string $table,
        int    $recordId,
        string $description
    ): void {
        $pdo->prepare(
            "INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected,
                 record_id, description, action_at)
             VALUES
                (:uid, :evt, :act, :tbl, :rid, :desc, NOW())"
        )->execute([
            ':uid'  => $actingUserId,
            ':evt'  => $eventType,
            ':act'  => $eventType,
            ':tbl'  => $table,
            ':rid'  => $recordId,
            ':desc' => $description,
        ]);
    }
}
