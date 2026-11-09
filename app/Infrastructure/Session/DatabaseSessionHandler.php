<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Session;

use PDO;

/**
 * MySQL-backed PHP session handler.
 *
 * Stores sessions in the `sessions` table (migration 001 + 025).
 * Enforces 30-minute idle TTL and 12-hour absolute TTL per ADR-0001/ADR-0003.
 * The absolute expiry is stored as a Unix timestamp in `expires_at`.
 *
 * Session data contains a serialized PHP payload written by session_write_close().
 * Idle timeout is implemented at read time: if the stored idle_expires_at
 * has passed we return '' (empty) which PHP treats as an invalid/expired session.
 *
 * Cookie attributes: HttpOnly, SameSite=Lax, and Secure in production.
 *
 * Single-active-session: the `user_id` column is populated on write when the
 * session payload contains an authenticated identity, enabling
 * invalidateByUserId() to delete all prior sessions for the same account
 * before a new login is committed.
 */
final class DatabaseSessionHandler implements \SessionHandlerInterface
{
    private PDO    $pdo;
    private int    $idleTtl;
    private int    $absoluteTtl;

    public function __construct(PDO $pdo, int $idleTtl = 1800, int $absoluteTtl = 43200)
    {
        $this->pdo          = $pdo;
        $this->idleTtl      = $idleTtl;
        $this->absoluteTtl  = $absoluteTtl;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data.
     *
     * Returns '' if the session does not exist or has expired (idle or absolute).
     */
    public function read(string $sessionId): string|false
    {
        $now = time();

        $stmt = $this->pdo->prepare(
            'SELECT data, expires_at FROM sessions WHERE session_id = :id'
        );
        $stmt->execute([':id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return '';
        }

        // expires_at stores the absolute expiry as a Unix timestamp.
        if ((int) $row['expires_at'] < $now) {
            $this->destroy($sessionId);

            return '';
        }

        return (string) $row['data'];
    }

    /**
     * Write session data.
     *
     * Stores the absolute expiry and, when an authenticated identity is present
     * in the payload, the associated user_id so that invalidateByUserId() can
     * target this row during single-active-session enforcement.
     */
    public function write(string $sessionId, string $data): bool
    {
        $expiresAt = time() + $this->absoluteTtl;
        $userId    = $this->extractUserIdFromData($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (session_id, user_id, expires_at, data)
             VALUES (:id, :user_id, :expires, :data)
             ON DUPLICATE KEY UPDATE
                 user_id    = :user_id2,
                 expires_at = :expires2,
                 data       = :data2'
        );

        return $stmt->execute([
            ':id'       => $sessionId,
            ':user_id'  => $userId,
            ':expires'  => $expiresAt,
            ':data'     => $data,
            ':user_id2' => $userId,
            ':expires2' => $expiresAt,
            ':data2'    => $data,
        ]);
    }

    public function destroy(string $sessionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE session_id = :id');

        return $stmt->execute([':id' => $sessionId]);
    }

    /**
     * Remove expired sessions.
     */
    public function gc(int $maxLifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE expires_at < :now');
        $stmt->execute([':now' => time()]);

        return $stmt->rowCount();
    }

    /**
     * Delete all existing sessions for the given user.
     *
     * Called by AuthController immediately before setIdentity() so that only
     * the brand-new session remains active after a login.  Any concurrent
     * request from a displaced session will find its row gone and be redirected
     * to /login with an appropriate flash message by AuthMiddleware.
     *
     * The current (newly-regenerated) session ID is excluded so that the
     * caller's own session is never deleted — the exclusion is meaningful only
     * when the method is invoked after session_regenerate_id(true), which is
     * what AuthMiddleware::setIdentity() does internally.
     *
     * @param int    $userId           The authenticated user's primary key.
     * @param string $keepSessionId    The session ID to preserve (the new one).
     */
    public function invalidateByUserId(int $userId, string $keepSessionId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions
              WHERE user_id = :user_id
                AND session_id != :keep'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':keep'    => $keepSessionId,
        ]);
    }

    /**
     * Extract the user_id from a serialized PHP session payload.
     *
     * PHP serializes session data in its own format (not standard PHP
     * serialize()).  The _auth key holds a sub-array that always contains
     * `user_id` when the user is authenticated.  We parse it out with a
     * targeted regex rather than calling session_decode() (which would
     * overwrite the live $_SESSION superglobal).
     *
     * Returns null for anonymous or unreadable payloads.
     */
    private function extractUserIdFromData(string $data): ?int
    {
        // PHP session format for an integer: `user_id|i:42;`
        // Within a nested array under _auth it looks like:
        // `_auth|a:N:{s:7:"user_id";i:42;...}`
        // We look for the user_id integer immediately following the key.
        if (!preg_match('/s:7:"user_id";i:(\d+);/', $data, $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }
}
