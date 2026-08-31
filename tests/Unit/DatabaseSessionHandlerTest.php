<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wbpms\Infrastructure\Session\DatabaseSessionHandler;

/**
 * Unit tests for DatabaseSessionHandler.
 *
 * ADR-0001: MySQL-backed sessions, 30-minute idle + 12-hour absolute expiry.
 * ADR-0003: project-owned SessionHandlerInterface.
 *
 * Uses a PDO mock so no database is required.
 */
final class DatabaseSessionHandlerTest extends TestCase
{
    private function makePdo(): PDO
    {
        /** @var PDO&MockObject $pdo */
        return $this->createMock(PDO::class);
    }

    private function makeStmt(mixed $fetchReturn = false, bool $executeReturn = true): PDOStatement
    {
        /** @var PDOStatement&MockObject $stmt */
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('rowCount')->willReturn(1);

        return $stmt;
    }

    // -----------------------------------------------------------------------
    // open / close
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function open_returns_true(): void
    {
        $handler = new DatabaseSessionHandler($this->makePdo());
        $this->assertTrue($handler->open('', 'PHPSESSID'));
    }

    /**
     * @test
     */
    public function close_returns_true(): void
    {
        $handler = new DatabaseSessionHandler($this->makePdo());
        $this->assertTrue($handler->close());
    }

    // -----------------------------------------------------------------------
    // read
    // -----------------------------------------------------------------------

    /**
     * @test
     * read() returns empty string when session row does not exist.
     */
    public function read_returns_empty_string_when_row_missing(): void
    {
        $stmt = $this->makeStmt(false);
        $pdo  = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $handler = new DatabaseSessionHandler($pdo);
        $result  = $handler->read('non-existent-session');

        $this->assertSame('', $result);
    }

    /**
     * @test
     * read() returns empty string when the session has expired (absolute TTL).
     */
    public function read_returns_empty_string_when_session_expired(): void
    {
        $expiredAt = time() - 1; // one second in the past

        $stmt = $this->makeStmt([
            'data'       => 'some-serialized-data',
            'expires_at' => $expiredAt,
        ]);

        $destroyStmt = $this->makeStmt();

        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmt, $destroyStmt);

        $handler = new DatabaseSessionHandler($pdo);
        $result  = $handler->read('expired-session');

        $this->assertSame('', $result);
    }

    /**
     * @test
     * read() returns the session data when not expired.
     */
    public function read_returns_data_when_session_is_valid(): void
    {
        $futureExpiry = time() + 3600;
        $data         = 'serialized-session-data';

        $stmt = $this->makeStmt([
            'data'       => $data,
            'expires_at' => $futureExpiry,
        ]);

        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $handler = new DatabaseSessionHandler($pdo);
        $result  = $handler->read('valid-session');

        $this->assertSame($data, $result);
    }

    // -----------------------------------------------------------------------
    // write
    // -----------------------------------------------------------------------

    /**
     * @test
     * write() executes an UPSERT and returns true.
     */
    public function write_returns_true_on_success(): void
    {
        $stmt = $this->makeStmt(false, true);
        $pdo  = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $handler = new DatabaseSessionHandler($pdo);
        $result  = $handler->write('session-id', 'data');

        $this->assertTrue($result);
    }

    // -----------------------------------------------------------------------
    // destroy
    // -----------------------------------------------------------------------

    /**
     * @test
     * destroy() executes a DELETE and returns true.
     */
    public function destroy_returns_true(): void
    {
        $stmt = $this->makeStmt(false, true);
        $pdo  = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $handler = new DatabaseSessionHandler($pdo);
        $result  = $handler->destroy('session-id');

        $this->assertTrue($result);
    }

    // -----------------------------------------------------------------------
    // gc
    // -----------------------------------------------------------------------

    /**
     * @test
     * gc() deletes expired sessions and returns the affected row count.
     */
    public function gc_returns_deleted_row_count(): void
    {
        $stmt = $this->makeStmt(false, true);
        $stmt->method('rowCount')->willReturn(5);

        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $handler = new DatabaseSessionHandler($pdo);
        $result  = $handler->gc(1800);

        $this->assertSame(5, $result);
    }

    // -----------------------------------------------------------------------
    // Expiry TTL
    // -----------------------------------------------------------------------

    /**
     * @test
     * The absolute expiry stored in expires_at is time() + absoluteTtl.
     */
    public function write_stores_correct_absolute_expiry(): void
    {
        $absoluteTtl = 43200; // 12 hours
        $before      = time();

        $capturedParams = null;
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(
            function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            }
        );

        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $handler = new DatabaseSessionHandler($pdo, 1800, $absoluteTtl);
        $handler->write('sid', 'data');

        $after = time();

        $this->assertNotNull($capturedParams);
        $expiresAt = $capturedParams[':expires'] ?? $capturedParams[':expires2'] ?? null;
        $this->assertNotNull($expiresAt);
        $this->assertGreaterThanOrEqual($before + $absoluteTtl, (int) $expiresAt);
        $this->assertLessThanOrEqual($after  + $absoluteTtl, (int) $expiresAt);
    }
}
