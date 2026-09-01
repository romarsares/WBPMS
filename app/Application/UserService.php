<?php

declare(strict_types=1);

namespace Wbpms\Application;

use PDO;
use PDOException;
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
 *   - employee_id is optional (NULL for the Business Owner account).
 *   - All mutations are audited via audit_logs when an acting_user_id is supplied.
 *
 * No SQL belongs in controllers; all queries live here.
 */
final class UserService
{
    public function __construct(private Connection $connection) {}

    // -----------------------------------------------------------------------
    // REQ004 — List
    // -----------------------------------------------------------------------

    /**
     * Return all non-archived users with their role name.
     * Pass $includeArchived = true to include Archived rows (for admin view).
     *
     * @return list<array<string,mixed>>
     */
    public function list(bool $includeArchived = false): array
    {
        $sql = "SELECT u.user_id, u.username, u.account_email, u.status,
                       u.employee_id, u.created_at, u.updated_at,
                       r.role_id, r.role_name,
                       CONCAT(e.last_name, ', ', e.first_name) AS employee_name
                  FROM users u
                  JOIN role r   ON r.role_id = u.role_id
                  LEFT JOIN employee e ON e.employee_id = u.employee_id";

        if (!$includeArchived) {
            $sql .= " WHERE u.status != 'Archived'";
        }

        $sql .= " ORDER BY u.created_at DESC";

        return $this->connection->pdo()->query($sql)->fetchAll();
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
     *   employee_id?:   int|null,
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
        $this->assertUniqueUsername($username);
        $this->assertUniqueEmail($email);
        $this->assertValidRole($roleId);
        if ($employeeId !== null) {
            $this->assertEmployeeNotLinked($employeeId);
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
     *   employee_id?:   int|null,
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
        $employeeId = array_key_exists('employee_id', $data)
                      ? ($data['employee_id'] !== '' && $data['employee_id'] !== null
                         ? (int) $data['employee_id'] : null)
                      : ($user['employee_id'] !== null ? (int) $user['employee_id'] : null);

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
        if ($roleId !== (int) $user['role_id']) {
            $this->assertValidRole($roleId);
        }
        if ($employeeId !== null && $employeeId !== ($user['employee_id'] !== null ? (int) $user['employee_id'] : null)) {
            $this->assertEmployeeNotLinked($employeeId, $userId);
        }

        $this->connection->transaction(function (PDO $pdo) use (
            $userId, $username, $email, $roleId, $employeeId, $actingUserId
        ): void {
            $stmt = $pdo->prepare(
                "UPDATE users
                    SET username = :user, account_email = :email,
                        role_id  = :role, employee_id   = :emp
                  WHERE user_id  = :id"
            );
            $stmt->execute([
                ':user'  => $username,
                ':email' => $email,
                ':role'  => $roleId,
                ':emp'   => $employeeId,
                ':id'    => $userId,
            ]);

            $this->insertAudit($pdo, $actingUserId, 'user_updated', 'users', $userId,
                "Updated user_id={$userId}: username='{$username}', role_id={$roleId}");
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
     * Archive a user account (soft delete — one-way, cannot be undone via UI).
     *
     * @throws RuntimeException when user is already Archived
     */
    public function archive(int $userId, int $actingUserId): void
    {
        $user = $this->findOrFail($userId);

        if ($user['status'] === 'Archived') {
            throw new RuntimeException('User is already archived.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($userId, $actingUserId): void {
            $pdo->prepare("UPDATE users SET status = 'Archived' WHERE user_id = :id")
                ->execute([':id' => $userId]);

            $this->insertAudit($pdo, $actingUserId, 'user_archived', 'users', $userId,
                "Archived user_id={$userId}");
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
                    u.employee_id, u.role_id, u.created_at
               FROM users u
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
        $count = (int) $this->connection->pdo()->prepare($sql)->execute($bind) &&
                 ($this->connection->pdo()->prepare($sql)->execute($bind) ?: 0);

        // Re-query cleanly
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

    private function assertValidRole(int $roleId): void
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT COUNT(*) FROM role WHERE role_id = :id"
        );
        $stmt->execute([':id' => $roleId]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException("Role #{$roleId} does not exist.");
        }
    }

    private function assertEmployeeNotLinked(int $employeeId, ?int $excludeUserId = null): void
    {
        $sql  = "SELECT COUNT(*) FROM users WHERE employee_id = :emp AND status != 'Archived'";
        $bind = [':emp' => $employeeId];
        if ($excludeUserId !== null) {
            $sql  .= " AND user_id != :uid";
            $bind[':uid'] = $excludeUserId;
        }
        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($bind);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('This employee already has an active user account.');
        }
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
