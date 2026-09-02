<?php


use Wbpms\Http\View\Formatter;

/**
 * HR Head dashboard.
 *
 * @var int    $totalEmployees        Total active employees
 * @var int    $pendingRequests       Requests awaiting HR action
 * @var int    $incompleteAttendance  Employees with incomplete attendance this period
 * @var int    $payrollDrafts         Payroll runs in Draft/Computed state
 * @var list<array{id:int,employee_name:string,type:string,submitted_at:string,status:string}> $recentRequests
 * @var list<array{id:int,branch_name:string,period:string,status:string,employee_count:int}>  $payrollRuns
 */

$totalEmployees       ??= 0;
$pendingRequests      ??= 0;
$incompleteAttendance ??= 0;
$payrollDrafts        ??= 0;
$recentRequests       ??= [];
$payrollRuns          ??= [];

$statusBadge = static function (string $status): string {
    $map = [
        'Draft'                 => 'badge-gray',
        'Computed'              => 'badge-blue',
        'PendingOwnerApproval'  => 'badge-yellow',
        'Approved'              => 'badge-green',
        'Returned'              => 'badge-red',
        'Pending'               => 'badge-yellow',
        'Approved_req'          => 'badge-green',
        'Rejected'              => 'badge-red',
    ];
    $label = match ($status) {
        'PendingOwnerApproval' => 'Pending Approval',
        'Approved_req'         => 'Approved',
        default                => $status,
    };
    $cls = $map[$status] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
};
?>

<div class="page-header">
    <h1>HR Dashboard</h1>
    <span style="font-size:.875rem;color:#6b7280"><?= date('l, F j, Y') ?></span>
</div>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Active Employees</div>
        <div class="stat-value"><?= $totalEmployees ?></div>
        <div class="stat-sub"><a href="<?= $base ?>/hr/employees">View all →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Requests</div>
        <div class="stat-value" style="<?= $pendingRequests > 0 ? 'color:#b45309' : '' ?>"><?= $pendingRequests ?></div>
        <div class="stat-sub"><a href="<?= $base ?>/hr/requests">Review →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Incomplete Attendance</div>
        <div class="stat-value" style="<?= $incompleteAttendance > 0 ? 'color:#dc2626' : '' ?>"><?= $incompleteAttendance ?></div>
        <div class="stat-sub"><a href="<?= $base ?>/hr/attendance">Review →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Payroll Drafts</div>
        <div class="stat-value"><?= $payrollDrafts ?></div>
        <div class="stat-sub"><a href="<?= $base ?>/hr/payroll">View →</a></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

    <!-- Recent Requests -->
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
            <strong style="font-size:.95rem">Recent Requests</strong>
            <a href="<?= $base ?>/hr/requests" class="btn btn-secondary btn-sm">All requests</a>
        </div>
        <?php if (empty($recentRequests)): ?>
        <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">No pending requests.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Submitted</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentRequests as $req): ?>
            <tr>
                <td><?= Formatter::escape($req['employee_name']) ?></td>
                <td><?= Formatter::escape($req['type']) ?></td>
                <td><?= Formatter::date($req['submitted_at']) ?></td>
                <td><?= $statusBadge($req['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Payroll Runs -->
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
            <strong style="font-size:.95rem">Payroll Runs</strong>
            <a href="<?= $base ?>/hr/payroll/create" class="btn btn-primary btn-sm">New run</a>
        </div>
        <?php if (empty($payrollRuns)): ?>
        <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">No payroll runs yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Period</th>
                    <th>Employees</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payrollRuns as $run): ?>
            <tr>
                <td><?= Formatter::escape($run['branch_name']) ?></td>
                <td><?= Formatter::escape($run['period']) ?></td>
                <td style="text-align:center"><?= (int) $run['employee_count'] ?></td>
                <td><?= $statusBadge($run['status']) ?></td>
                <td><a href="<?= $base ?>/hr/payroll/<?= (int) $run['id'] ?>" class="btn btn-secondary btn-sm">View</a></td>
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
        <a href="<?= $base ?>/hr/employees/new"    class="btn btn-secondary">Add Employee</a>
        <a href="<?= $base ?>/hr/attendance/import" class="btn btn-secondary">Import Attendance</a>
        <a href="<?= $base ?>/hr/schedules"         class="btn btn-secondary">Manage Schedules</a>
        <a href="<?= $base ?>/hr/payroll/create"       class="btn btn-primary">Generate Payroll</a>
    </div>
</div>
