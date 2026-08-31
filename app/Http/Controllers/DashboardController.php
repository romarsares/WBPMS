<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

/**
 * DashboardController — role-scoped landing page after login.
 *
 * GET /dashboard → index()
 *
 * Each role sees its own summary cards and quick-action panel.
 * All data is read-only; no mutations happen here.
 */
final class DashboardController
{
    /**
     * GET /dashboard
     *
     * @param array<string, string> $params
     */
    public function index(array $params = []): void
    {
        $identity = AuthMiddleware::identity();
        if ($identity === null) {
            $this->redirect('/login');
            return;
        }

        $config     = require APP_ROOT . '/config/database.php';
        $connection = new Connection($config);
        $pdo        = $connection->pdo();

        $role        = $identity['role_name'];
        $displayName = $identity['display_name'] ?? $identity['username'];
        $base        = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        $csrfField   = CsrfMiddleware::field();

        // ---- Role-specific stats ----
        $stats       = [];
        $notifCount  = 0;

        if ($role === 'BusinessOwner') {
            $pendingRequests = (int) $pdo
                ->query("SELECT COUNT(*) FROM request WHERE status = 'Pending'")
                ->fetchColumn();

            $pendingPayroll = (int) $pdo
                ->query("SELECT COUNT(*) FROM payroll_run WHERE status = 'PendingOwnerApproval'")
                ->fetchColumn();

            $approvedNetPay = (float) $pdo
                ->query("SELECT COALESCE(SUM(net_pay), 0) FROM payroll_run WHERE status = 'Approved'")
                ->fetchColumn();

            $branchCount = (int) $pdo
                ->query("SELECT COUNT(*) FROM branch WHERE status = 'Active'")
                ->fetchColumn();

            $stats = [
                ['value' => $branchCount,                             'label' => 'Active Branches'],
                ['value' => $pendingRequests,                         'label' => 'Pending Requests'],
                ['value' => $pendingPayroll,                          'label' => 'Payroll Pending Approval'],
                ['value' => '₱' . number_format($approvedNetPay, 2), 'label' => 'Approved Payroll'],
            ];
            $notifCount = $pendingRequests;
        }

        elseif ($role === 'HRHead') {
            $activeEmployees = (int) $pdo
                ->query("SELECT COUNT(*) FROM employee WHERE status = 'Active'")
                ->fetchColumn();

            $pendingRequests = (int) $pdo
                ->query("SELECT COUNT(*) FROM request WHERE status = 'Pending'")
                ->fetchColumn();

            $missingPunches = (int) $pdo
                ->query("SELECT COUNT(*) FROM attendance WHERE time_in IS NULL OR time_out IS NULL")
                ->fetchColumn();

            $onLeave = (int) $pdo
                ->query("SELECT COUNT(*) FROM request r
                          JOIN leave_request_detail l ON l.request_id = r.request_id
                          WHERE r.status = 'Approved'
                            AND CURDATE() BETWEEN l.start_date AND l.end_date")
                ->fetchColumn();

            $stats = [
                ['value' => $activeEmployees, 'label' => 'Active Employees'],
                ['value' => $onLeave,         'label' => 'Employees on Leave'],
                ['value' => $pendingRequests, 'label' => 'Pending Requests'],
                ['value' => $missingPunches,  'label' => 'Missing Attendance'],
            ];
            $notifCount = $missingPunches;
        }

        else { // Employee
            $employeeId = $identity['employee_id'] ?? null;

            $pendingRequests = 0;
            $payslipCount    = 0;
            $dailyRate       = '—';

            if ($employeeId !== null) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM request WHERE employee_id = ? AND status = 'Pending'"
                );
                $stmt->execute([$employeeId]);
                $pendingRequests = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM payroll WHERE employee_id = ?"
                );
                $stmt->execute([$employeeId]);
                $payslipCount = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "SELECT daily_rate FROM salary
                      WHERE employee_id = ? AND status = 'Active'
                      ORDER BY effective_from DESC LIMIT 1"
                );
                $stmt->execute([$employeeId]);
                $rate = $stmt->fetchColumn();
                if ($rate !== false) {
                    $dailyRate = '₱' . number_format((float) $rate, 2);
                }
            }

            $stats = [
                ['value' => $dailyRate,       'label' => 'Daily Rate'],
                ['value' => $pendingRequests, 'label' => 'Pending Requests'],
                ['value' => $payslipCount,    'label' => 'Available Payslips'],
                ['value' => 4,                'label' => 'Paid Sick Leaves / Year'],
            ];

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM request
                  WHERE employee_id = ? AND status IN ('Approved','Rejected')"
            );
            $stmt->execute([$employeeId ?? 0]);
            $notifCount = (int) $stmt->fetchColumn();
        }

        // ---- Render ----
        $title      = 'Dashboard';
        $activePage = 'dashboard';
        $flash      = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        ob_start();
        require APP_ROOT . '/resources/views/dashboard/' . strtolower($role) . '.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
