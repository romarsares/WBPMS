<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeRepository;

final class EmployeeBiometricEnrollmentTest extends IntegrationTestCase
{
    private EmployeeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EmployeeRepository(new Connection([], static::$sharedPdo));
    }

    public function testEnrollIdCannotBeAssignedToTwoActiveEmployeesOnOneDevice(): void
    {
        $deviceId = $this->insertBiometricDevice();
        $firstEmployeeId = $this->insertEmployee();
        $secondEmployeeId = $this->insertEmployee();

        $this->repo->enrollBiometricCode($firstEmployeeId, $deviceId, '00123', '2026-01-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already assigned to another active employee');

        $this->repo->enrollBiometricCode($secondEmployeeId, $deviceId, '00123', '2026-02-01');
    }

    public function testEnrollIdCanBeReusedOnAnotherDevice(): void
    {
        $firstDeviceId = $this->insertBiometricDevice();
        $secondDeviceId = $this->insertBiometricDevice();
        $firstEmployeeId = $this->insertEmployee();
        $secondEmployeeId = $this->insertEmployee();

        $this->repo->enrollBiometricCode($firstEmployeeId, $firstDeviceId, '00123', '2026-01-01');
        $this->repo->enrollBiometricCode($secondEmployeeId, $secondDeviceId, '00123', '2026-01-01');

        $this->assertSame(2, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM employee_biometric_enrollment WHERE device_employee_code = '00123'"
        )->fetchColumn());
    }

    private function insertBiometricDevice(): int
    {
        $code = uniqid('SITE');
        $site = $this->pdo->prepare(
            'INSERT INTO attendance_site (site_code, site_name) VALUES (:code, :name)'
        );
        $site->execute([':code' => $code, ':name' => 'Site ' . $code]);

        $device = $this->pdo->prepare(
            'INSERT INTO biometric_device (site_id, device_code, file_format, timezone)
             VALUES (:site_id, :device_code, :file_format, :timezone)'
        );
        $device->execute([
            ':site_id' => (int) $this->pdo->lastInsertId(),
            ':device_code' => uniqid('DEVICE'),
            ':file_format' => 'LDE_XLS_DAILY_LOG_V1',
            ':timezone' => 'Asia/Manila',
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
