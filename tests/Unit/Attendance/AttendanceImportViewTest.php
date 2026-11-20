<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Attendance;

use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Http\Controllers\AttendanceController;

final class AttendanceImportViewTest extends TestCase
{
    public function testRecentImportsUsesAReadableFallbackWhenLegacyPeriodDataIsMissing(): void
    {
        $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/hr/attendance/import.php');

        self::assertIsString($view);
        self::assertStringContainsString("'Period not recorded'", $view);
        self::assertStringNotContainsString("?? '?',", $view);
    }

    public function testSurallahBranchesAndDevicesAreNotOfferedForAttendanceOperations(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE branch (branch_id INTEGER PRIMARY KEY, branch_name TEXT NOT NULL, status TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE biometric_device (device_id INTEGER PRIMARY KEY, device_code TEXT NOT NULL, device_name TEXT NOT NULL, status TEXT NOT NULL)");
        $pdo->exec("INSERT INTO branch VALUES
            (1, 'Banga Construction', 'Active'),
            (2, 'Surallah Branch', 'Active'),
            (3, 'Archived Branch', 'Archived')");
        $pdo->exec("INSERT INTO biometric_device VALUES
            (1, 'DEVICE-BANGA', 'Banga Biometric Terminal', 'Active'),
            (2, 'DEVICE-SURALLAH', 'Surallah Biometric Terminal', 'Active'),
            (3, 'DEVICE-OLD', 'Old Terminal', 'Inactive')");

        $controller = new AttendanceController();
        $branches = (new ReflectionMethod(AttendanceController::class, 'loadAttendanceBranches'))
            ->invoke($controller, $pdo);
        $devices = (new ReflectionMethod(AttendanceController::class, 'loadDevices'))
            ->invoke($controller, $pdo);

        self::assertSame(['Banga Construction'], array_column($branches, 'branch_name'));
        self::assertSame(['Banga Biometric Terminal'], array_column($devices, 'device_name'));
    }
}
