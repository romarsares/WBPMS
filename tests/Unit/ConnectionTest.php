<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbpms\Infrastructure\Database\Connection;

/**
 * Unit tests for the Connection PDO factory.
 *
 * Tests that do not require a live MySQL connection verify the class
 * structure and transaction helper semantics.
 *
 * ADR-0003: PDO connection is the only place a PDO handle is created.
 */
final class ConnectionTest extends TestCase
{
    /**
     * @test
     * Connection instantiates without error given a valid-looking config.
     */
    public function it_instantiates_with_a_config_array(): void
    {
        $connection = new Connection([
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => 'wbpms_test',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ]);

        $this->assertInstanceOf(Connection::class, $connection);
    }

    /**
     * @test
     * Connection::transaction() commits on success and returns the callback result.
     */
    public function it_commits_transaction_on_success(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $pdo->expects($this->never())->method('rollBack');

        /** @var Connection|\PHPUnit\Framework\MockObject\MockObject $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['pdo'])
            ->getMock();
        $connection->method('pdo')->willReturn($pdo);

        $result = $connection->transaction(static function (\PDO $p): string {
            return 'success';
        });

        $this->assertSame('success', $result);
    }

    /**
     * @test
     * Connection::transaction() rolls back and re-throws on exception.
     */
    public function it_rolls_back_and_rethrows_on_exception(): void
    {
        $pdo = $this->createMock(\PDO::class);
        // inTransaction() returns false initially (not nested), then true after beginTransaction
        // (so the catch block guard passes and rollBack is called).
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);
        $pdo->expects($this->never())->method('commit');

        /** @var Connection|\PHPUnit\Framework\MockObject\MockObject $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['pdo'])
            ->getMock();
        $connection->method('pdo')->willReturn($pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test-rollback');

        $connection->transaction(static function (\PDO $p): void {
            throw new \RuntimeException('test-rollback');
        });
    }

    /**
     * @test
     * Attempting to open a connection with invalid credentials throws RuntimeException
     * (not PDOException, so credentials are not exposed in the message).
     */
    public function it_throws_runtime_exception_on_connection_failure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Database connection failed/');

        $connection = new Connection([
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => 19999, // Non-existent port
            'database' => 'no_such_db',
            'username' => 'no_user',
            'password' => 'wrong',
            'charset'  => 'utf8mb4',
            'options'  => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        ]);

        // Trigger connection (lazy)
        $connection->pdo();
    }
}
