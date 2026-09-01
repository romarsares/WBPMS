<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Employee self-service dashboard.
 *
 * @var string $employeeName        Full name of the authenticated employee
 * @var string $branchName          Current branch name
 * @var string $scheduleName        Current work schedule name
 * @var int    $sickLeaveBalance    Remaining sick leave days this year
 * @var int    $pendingRequests     Own requests not yet actioned
 * @var list<array{id:int,period:string,status:string,net_pay:string}> $recentPayslips
 * @var list<array{id:int,type:string,submitted_at:string,status:string}> $recentRequests
 */

$employeeName     ??= '';
$branchName       ??= '';
$scheduleName     ??= '';
$sickLeaveBalance ??= 0;
$pendingRequests  ??= 0;
$recentPayslips   ??= [];
$recentRequests   ??= [];

$reqStatusBadge = static function (string $status): string {
    $map = ['Pending' => 'badge-yellow', 'Approved' => 'badge-green', 'Rejected' => 'badge-red', 'Cancelled' => 'badge-gray'];
    return '<span class="badge ' . ($map[$status] ?? 'badge-gray') . '">'
        . htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
};
?>

<div class="page-header">
    <h1>Welcome, <?= Formatter::escape($employeeName) ?></h1>
    <span style="font-size:.875rem;color:#6b7280"><?= date('l, F j, Y') ?></span>
</div>

<!-- Employee info + stat row -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-label">Branch</div>
        <div style="font-size:1rem;font-weight:600;color:#111827;margin-top:.25rem"><?= Formatter::escape($branchName) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Schedule</div>
        <div style="font-size:1rem;font-weight:600;color:#111827;margin-top:.25rem"><?= Formatter::escape($scheduleName) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sick Leave Balance</div>
        <div class="stat-value" style="<?= $sickLeaveBalance < 2 ? 'color:#dc2626' : '' ?>"><?= $sickLeaveBalance ?></div>
        <div class="stat-sub">days remaining</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Requests</div>
        <div class="stat-value" style="<?= $pendingRequests > 0 ? 'color:#b45309' : '' ?>"><?= $pendingRequests ?></div>
        <div class="stat-sub"><a href="<?= $base ?>/employee/requests">View →</a></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

    <!-- Recent payslips -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
            <strong style="font-size:.95rem">Recent Payslips</strong>
            <a href="<?= $base ?>/employee/payslips" class="btn btn-secondary btn-sm">All payslips</a>
        </div>
        <?php if (empty($recentPayslips)): ?>
        <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">No payslips yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Net Pay</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentPayslips as $ps): ?>
            <tr>
                <td><?= Formatter::escape($ps['period']) ?></td>
                <td>₱<?= Formatter::escape($ps['net_pay']) ?></td>
                <td><span class="badge badge-green">Approved</span></td>
                <td>
                    <a href="<?= $base ?>/employee/payslips/<?= (int) $ps['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Recent requests -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
            <strong style="font-size:.95rem">My Requests</strong>
            <a href="<?= $base ?>/employee/requests/new" class="btn btn-primary btn-sm">New request</a>
        </div>
        <?php if (empty($recentRequests)): ?>
        <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">No requests submitted yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Submitted</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentRequests as $req): ?>
            <tr>
                <td><?= Formatter::escape($req['type']) ?></td>
                <td><?= Formatter::date($req['submitted_at']) ?></td>
                <td><?= $reqStatusBadge($req['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<!-- Quick links -->
<div class="card" style="margin-top:1.5rem">
    <strong style="font-size:.9rem;display:block;margin-bottom:.75rem;color:#374151">Quick Actions</strong>
    <div style="display:flex;flex-wrap:wrap;gap:.65rem">
        <a href="<?= $base ?>/employee/attendance"  class="btn btn-secondary">View Attendance</a>
        <a href="<?= $base ?>/employee/requests/new" class="btn btn-primary">Submit Request</a>
        <a href="<?= $base ?>/employee/payslips"    class="btn btn-secondary">My Payslips</a>
    </div>
</div>
