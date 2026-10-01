<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\View\ViewRenderer;

/**
 * StubController — renders a "module coming soon" page for routes that are
 * registered and protected but not yet fully implemented.
 *
 * Each action passes its own title/description so the sidebar active state
 * and page header are correct.
 */
final class StubController
{
    /** @param array<string, string> $params */
    public function users(array $params = []): void
    {
        $this->render('User Management', 'users', 'Create, update, activate, deactivate, and assign roles.');
    }

    /** @param array<string, string> $params */
    public function employees(array $params = []): void
    {
        $this->render('Employee Management', 'employees', 'Manage employee records per branch.');
    }

    /** @param array<string, string> $params */
    public function attendance(array $params = []): void
    {
        $this->render('Attendance Management', 'attendance', 'Import biometric XLS logs and manage timesheets.');
    }

    /** @param array<string, string> $params */
    public function schedule(array $params = []): void
    {
        $this->render('Work Schedule', 'schedule', 'Manage calendar-based schedules and holidays.');
    }

    /** @param array<string, string> $params */
    public function requests(array $params = []): void
    {
        $this->render('Request Management', 'requests', 'Leave, overtime, and cash advance requests.');
    }

    /** @param array<string, string> $params */
    public function payroll(array $params = []): void
    {
        $this->render('Payroll', 'payroll', 'Payroll computation and owner approval workflow.');
    }

    /** @param array<string, string> $params */
    public function salary(array $params = []): void
    {
        $this->render('Salary Management', 'salary', 'Salary structures, daily rates, and historical rates.');
    }

    /** @param array<string, string> $params */
    public function benefits(array $params = []): void
    {
        $this->render('Benefits & Deductions', 'benefits', 'SSS, PhilHealth, Pag-IBIG contributions and deductions.');
    }

    /** @param array<string, string> $params */
    public function reports(array $params = []): void
    {
        $this->render('Reports', 'reports', 'Payroll, attendance, request, and contribution reports.');
    }

    /** @param array<string, string> $params */
    public function myAttendance(array $params = []): void
    {
        $this->render('My Attendance', 'my-attendance', 'View your biometric attendance records.');
    }

    /** @param array<string, string> $params */
    public function myRequests(array $params = []): void
    {
        $this->render('My Requests', 'my-requests', 'Submit and track your leave, overtime, and cash advance requests.');
    }

    /** @param array<string, string> $params */
    public function myPayslips(array $params = []): void
    {
        $this->render('My Payslips', 'my-payslips', 'View and download your approved payslips.');
    }

    // -----------------------------------------------------------------------

    private function render(string $title, string $activePage, string $description): void
    {
        ViewRenderer::render('stub', [
            'stubTitle'       => $title,
            'stubDescription' => $description,
            'activePage'      => $activePage,
        ], $title);
    }
}
