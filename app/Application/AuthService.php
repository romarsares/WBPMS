<?php

declare(strict_types=1);

namespace Wbpms\Application;

use PDO;
use Wbpms\Infrastructure\Database\Connection;

/**
 * Authentication application service.
 *
 * Responsibilities:
 *   - Verify username+password against the users table.
 *   - Return an authenticated identity DTO on success.
 *   - Write audit_logs rows for login success and failure.
 *   - Never expose password hashes; never log credentials.
 *
 * REQ001: login with username and password
 * REQN011: passwords hashed with password_hash(PASSWORD_DEFAULT)
 * ADR-0002 §10: audit rows work with user_id = NULL for failed login events.
 */
final class AuthService
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Attempt to authenticate a user by username and password.
     *
     * Returns an array with the authenticated user's data on success,
     * or null on failure. Writes an audit row in both cases.
     *
     * @param  string $username          The submitted username
     * @param  string $plainPassword     The submitted password (never stored/logged)
     * @param  string $requestId         Request UUID for audit correlation
     * @param  string $ipAddress         Remote IP address
     * @param  string $userAgent         HTTP user agent string
     * @return array{user_id: int, username: string, role_name: string, employee_id: int|null}|null
     */
    public function attemptLogin(
        string $username,
        string $plainPassword,
        string $requestId = '',
        string $ipAddress = '',
        string $userAgent = ''
    ): ?array {
        $pdo = $this->connection->pdo();

        // Fetch user + role in a single query
        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.username, u.password_hash, u.status,
                    u.employee_id, r.role_name
             FROM users u
             JOIN role r ON r.role_id = u.role_id
             WHERE u.username = :username
             LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $actionAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        if (
            $user === false
            || $user['status'] !== 'Active'
            || !password_verify($plainPassword, (string) $user['password_hash'])
        ) {
            // Audit: failed login — user_id is NULL per ADR-0002 §10
            $this->writeAudit(
                $pdo,
                null,
                'login_failure',
                'login_attempt',
                null,
                null,
                $username,          // attempted_identifier
                $requestId,
                $ipAddress,
                $userAgent,
                'Login failed: invalid credentials or inactive account.',
                $actionAt
            );

            return null;
        }

        // Audit: successful login
        $this->writeAudit(
            $pdo,
            (int) $user['user_id'],
            'login_success',
            'login',
            'users',
            (int) $user['user_id'],
            null,
            $requestId,
            $ipAddress,
            $userAgent,
            'User logged in successfully.',
            $actionAt
        );

        // Upgrade password hash if needed (e.g., bcrypt cost changed)
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id');
            $upd->execute([':hash' => $newHash, ':id' => $user['user_id']]);
        }

        return [
            'user_id'     => (int)    $user['user_id'],
            'username'    => (string) $user['username'],
            'role_name'   => (string) $user['role_name'],
            'employee_id' => $user['employee_id'] !== null ? (int) $user['employee_id'] : null,
        ];
    }

    /**
     * Write an entry to audit_logs.
     *
     * @param PDO         $pdo
     * @param int|null    $userId
     * @param string      $eventType
     * @param string      $action
     * @param string|null $tableAffected
     * @param int|null    $recordId
     * @param string|null $attemptedIdentifier
     * @param string      $requestId
     * @param string      $ipAddress
     * @param string      $userAgent
     * @param string|null $description
     * @param string      $actionAt  UTC datetime string
     */
    private function writeAudit(
        PDO     $pdo,
        ?int    $userId,
        string  $eventType,
        string  $action,
        ?string $tableAffected,
        ?int    $recordId,
        ?string $attemptedIdentifier,
        string  $requestId,
        string  $ipAddress,
        string  $userAgent,
        ?string $description,
        string  $actionAt
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected, record_id,
                 attempted_identifier, request_id, ip_address, user_agent, description,
                 action_at, created_at)
             VALUES
                (:user_id, :event_type, :action, :table, :record_id,
                 :attempted, :req_id, :ip, :ua, :desc,
                 :action_at, :created_at)'
        );

        $stmt->execute([
            ':user_id'    => $userId,
            ':event_type' => $eventType,
            ':action'     => $action,
            ':table'      => $tableAffected,
            ':record_id'  => $recordId,
            ':attempted'  => $attemptedIdentifier !== '' ? $attemptedIdentifier : null,
            ':req_id'     => $requestId !== '' ? $requestId : null,
            ':ip'         => $ipAddress !== '' ? $ipAddress : null,
            ':ua'         => $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            ':desc'       => $description,
            ':action_at'  => $actionAt,
            ':created_at' => $actionAt,
        ]);
    }
}
