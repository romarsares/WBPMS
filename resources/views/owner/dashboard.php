<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Business Owner dashboard.
 *
 * @var int    $pendingApprovals    Payroll runs awaiting owner approval
 * @var int    $approvedThisPeriod  Approved runs in the current pay period
 * @var int    $totalBranches       Total active branches
 * @var string $currentPeriod       Current pay period label (e.g. "08/01/26–08/15/26")
 * @var list<array{id:int,branch_name:string,period:string,status:string,employee_count:int,submitted_at:string}> $pendingRuns
 * @var list<array{branch_name:string,period:string,gross_total:string,net_total:string,approved_at:string}>       $recentApproved
 */

$pendingApprovals   ??= 0;
$approvedThisPeriod ??= 0;
$totalBranches      ??= 0;
$currentPeriod      ??= '';
$pendingRuns        ??= [];
$recentApproved     ??= [];
?>

<div class="page-header">
    <h1>Owner Dashboard</h1>
    <span style="font-size:.875rem;color:#6b7280"><?= date('l, F j, Y') ?></span>
</div>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Pending Approval</div>
        <div class="stat-value" style="<?= $pendingApprovals > 0 ? 'color:#b45309' : '' ?>"><?= $pendingApprovals ?></div>
        <div class="stat-sub"><a href="/owner/payroll">Review payroll →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved This Period</div>
        <div class="stat-value" style="color:#16a34a"><?= $approvedThisPeriod ?></div>
        <div class="stat-sub"><?= Formatter::escape($currentPeriod) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Branches</div>
        <div class="stat-value"><?= $totalBranches ?></div>
    </div>
</div>

<!-- Pending payroll approvals -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
    <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
        <strong style="font-size:.95rem">Payroll Runs Awaiting Your Approval</strong>
        <a href="/owner/payroll" class="btn btn-secondary btn-sm">All pending</a>
    </div>
    <?php if (empty($pendingRuns)): ?>
    <p style="padding:1.25rem;color:#6b7280;font-size:.875rem;margin:0">
        No payroll runs are waiting for approval.
    </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th>Employees</th>
                <th>Submitted</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pendingRuns as $run): ?>
        <tr>
            <td><?= Formatter::escape($run['branch_name']) ?></td>
            <td><?= Formatter::escape($run['period']) ?></td>
            <td style="text-align:center"><?= (int) $run['employee_count'] ?></td>
            <td><?= Formatter::date($run['submitted_at']) ?></td>
            <td><span class="badge badge-yellow">Pending Approval</span></td>
            <td>
                <a href="/owner/payroll/<?= (int) $run['id'] ?>/review" class="btn btn-primary btn-sm">Review</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Recently approved -->
<?php if (!empty($recentApproved)): ?>
<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb">
        <strong style="font-size:.95rem">Recently Approved</strong>
    </div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th>Gross Total</th>
                <th>Net Total</th>
                <th>Approved</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentApproved as $run): ?>
        <tr>
            <td><?= Formatter::escape($run['branch_name']) ?></td>
            <td><?= Formatter::escape($run['period']) ?></td>
            <td>₱<?= Formatter::escape($run['gross_total']) ?></td>
            <td>₱<?= Formatter::escape($run['net_total']) ?></td>
            <td><?= Formatter::date($run['approved_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
