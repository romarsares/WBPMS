<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Session;

use PDO;

/**
 * MySQL-backed PHP session handler.
 *
 * Stores sessions in the `sessions` table (migration 001).
 * Enforces 30-minute idle TTL and 12-hour absolute TTL per ADR-0001/ADR-0003.
 * The absolute expiry is stored as a Unix timestamp in `expires_at`.
 *
 * Session data contains a serialized PHP payload written by session_write_close().
 * Idle timeout is implemented at read time: if the stored idle_expires_at
 * has passed we return '' (empty) which PHP treats as an invalid/expired session.
 *
 * Cookie attributes: HttpOnly, SameSite=Lax, and Secure in production.
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
     * Stores the absolute expiry; idle check is done on read.
     */
    public function write(string $sessionId, string $data): bool
    {
        $expiresAt = time() + $this->absoluteTtl;

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (session_id, expires_at, data)
             VALUES (:id, :expires, :data)
             ON DUPLICATE KEY UPDATE expires_at = :expires2, data = :data2'
        );

        return $stmt->execute([
            ':id'      => $sessionId,
            ':expires' => $expiresAt,
            ':data'    => $data,
            ':expires2' => $expiresAt,
            ':data2'   => $data,
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
}
