<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeder A3b: Demo Branches, Attendance Sites, Biometric Devices, and Coverage.
 *
 * ADR-0001 topology (configurable; display names are demo data):
 *   - 3 branches: Banga Construction, Banga Auto Supplies, Surallah
 *   - 2 attendance sites: Banga Site, Surallah Site
 *   - 2 biometric devices: one at Banga (covers both Banga branches), one at Surallah
 *   - biometric_device_branch: Banga device → Banga Construction + Banga Auto Supplies
 *                              Surallah device → Surallah branch
 *
 * This demonstrates that a single device can serve multiple organizational branches.
 * Production master data (official names) must be confirmed by HR before deployment.
 */
class DemoBranchesSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $from = '2024-01-01'; // Demo effective-from date

        // -------------------------------------------------------------------
        // branch
        // -------------------------------------------------------------------
        $this->execute("
            INSERT IGNORE INTO `branch` (branch_code, branch_name, location, status, created_at, updated_at)
            VALUES
                ('BANGA-CON', 'Banga Construction Supplies', 'Banga, South Cotabato', 'Active', '{$now}', '{$now}'),
                ('BANGA-AUT', 'Banga Auto Supplies',         'Banga, South Cotabato', 'Active', '{$now}', '{$now}'),
                ('SURALLAH',  'Surallah Branch',             'Surallah, South Cotabato', 'Active', '{$now}', '{$now}')
        ");

        // -------------------------------------------------------------------
        // attendance_site
        // -------------------------------------------------------------------
        $this->execute("
            INSERT IGNORE INTO `attendance_site` (site_code, site_name, address, timezone, status, created_at, updated_at)
            VALUES
                ('BANGA-SITE',    'Banga Attendance Site',    'Banga, South Cotabato',    'Asia/Manila', 'Active', '{$now}', '{$now}'),
                ('SURALLAH-SITE', 'Surallah Attendance Site', 'Surallah, South Cotabato', 'Asia/Manila', 'Active', '{$now}', '{$now}')
        ");

        // -------------------------------------------------------------------
        // biometric_device
        // -------------------------------------------------------------------
        // The Banga device serves both Banga branches; the Surallah device serves Surallah only.
        // serial_number values are synthetic demo data.
        $bangaSiteId    = $this->fetchSiteId('BANGA-SITE');
        $surallahSiteId = $this->fetchSiteId('SURALLAH-SITE');

        $this->execute("
            INSERT IGNORE INTO `biometric_device`
                (site_id, device_code, device_name, serial_number, file_format, timezone, status, installed_at, created_at, updated_at)
            VALUES
                ({$bangaSiteId},    'DEVICE-BANGA',    'Banga Biometric Terminal',    'SN-BANGA-001',    'LDE_XLS_DAILY_LOG_V1', 'Asia/Manila', 'Active', '2024-01-01', '{$now}', '{$now}'),
                ({$surallahSiteId}, 'DEVICE-SURALLAH', 'Surallah Biometric Terminal', 'SN-SURALLAH-001', 'LDE_XLS_DAILY_LOG_V1', 'Asia/Manila', 'Active', '2024-01-01', '{$now}', '{$now}')
        ");

        // -------------------------------------------------------------------
        // biometric_device_branch  (device-to-branch effective coverage)
        // -------------------------------------------------------------------
        $bangaDeviceId    = $this->fetchDeviceId('DEVICE-BANGA');
        $surallahDeviceId = $this->fetchDeviceId('DEVICE-SURALLAH');
        $bangaConBranchId = $this->fetchBranchId('BANGA-CON');
        $bangaAutBranchId = $this->fetchBranchId('BANGA-AUT');
        $surallahBranchId = $this->fetchBranchId('SURALLAH');

        $this->execute("
            INSERT IGNORE INTO `biometric_device_branch`
                (device_id, branch_id, effective_from, effective_to, status, created_at, updated_at)
            VALUES
                ({$bangaDeviceId},    {$bangaConBranchId}, '{$from}', NULL, 'Active', '{$now}', '{$now}'),
                ({$bangaDeviceId},    {$bangaAutBranchId}, '{$from}', NULL, 'Active', '{$now}', '{$now}'),
                ({$surallahDeviceId}, {$surallahBranchId}, '{$from}', NULL, 'Active', '{$now}', '{$now}')
        ");
    }

    private function fetchSiteId(string $siteCode): int
    {
        $row = $this->fetchRow("SELECT site_id FROM attendance_site WHERE site_code = '{$siteCode}'");
        if ($row === false || !isset($row['site_id'])) {
            throw new \RuntimeException("attendance_site '{$siteCode}' not found after insert.");
        }

        return (int) $row['site_id'];
    }

    private function fetchDeviceId(string $deviceCode): int
    {
        $row = $this->fetchRow("SELECT device_id FROM biometric_device WHERE device_code = '{$deviceCode}'");
        if ($row === false || !isset($row['device_id'])) {
            throw new \RuntimeException("biometric_device '{$deviceCode}' not found after insert.");
        }

        return (int) $row['device_id'];
    }

    private function fetchBranchId(string $branchCode): int
    {
        $row = $this->fetchRow("SELECT branch_id FROM branch WHERE branch_code = '{$branchCode}'");
        if ($row === false || !isset($row['branch_id'])) {
            throw new \RuntimeException("branch '{$branchCode}' not found after insert.");
        }

        return (int) $row['branch_id'];
    }
}
