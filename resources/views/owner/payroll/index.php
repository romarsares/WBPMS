<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Business Owner — Payroll approval queue.
 *
 * @var list<array{
 *     id: int,
 *     branch_name: string,
 *     period_label: string,
 *     employee_count: int,
 *     gross_total: string,
 *     net_total: string,
 *     submitted_at: string
 * }> $pendingRuns
 * @var list<array{
 *     id: int,
 *     branch_name: string,
 *     period_label: string,
 *     status: string,
 *     net_total: string,
 *     actioned_at: string
 * }> $recentRuns
 */

$pendingRuns ??= [];
$recentRuns  ??= [];
?>

<div class="page-header">
    <h1>Payroll Approvals</h1>
    <span style="font-size:.875rem;color:#6b7280">
        <?= count($pendingRuns) ?> run<?= count($pendingRuns) !== 1 ? 's' : '' ?> awaiting action
    </span>
</div>

<!-- Pending approval -->
<?php if (empty($pendingRuns)): ?>
<div class="card" style="text-align:center;padding:2.5rem">
    <p style="color:#6b7280;font-size:.95rem;margin:0">
        No payroll runs are currently waiting for your approval.
    </p>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.75rem">
    <div style="padding:.85rem 1.25rem;border-bottom:1px solid #e5e7eb;background:#fffbeb">
        <strong style="font-size:.95rem;color:#92400e">
            ⚠ The following payroll runs require your review and approval.
        </strong>
    </div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:center">Employees</th>
                <th style="text-align:right">Gross Total</th>
                <th style="text-align:right">Net Total</th>
                <th>Submitted</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pendingRuns as $run): ?>
        <tr>
            <td><?= Formatter::escape($run['branch_name']) ?></td>
            <td><?= Formatter::escape($run['period_label']) ?></td>
            <td style="text-align:center"><?= (int) $run['employee_count'] ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($run['gross_total']) ?></td>
            <td style="text-align:right;font-weight:600">₱<?= Formatter::escape($run['net_total']) ?></td>
            <td><?= Formatter::date($run['submitted_at']) ?></td>
            <td>
                <a href="/owner/payroll/<?= (int) $run['id'] ?>/review"
                   class="btn btn-primary btn-sm">Review &amp; Act</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Recent activity -->
<?php if (!empty($recentRuns)): ?>
<h2 style="font-size:1rem;color:#374151;margin-bottom:.75rem">Recent Activity</h2>
<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:right">Net Total</th>
                <th>Status</th>
                <th>Actioned</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentRuns as $run): ?>
        <?php
        $badge = match ($run['status']) {
            'Approved' => '<span class="badge badge-green">Approved</span>',
            'Returned' => '<span class="badge badge-red">Returned</span>',
            default    => '<span class="badge badge-gray">' . Formatter::escape($run['status']) . '</span>',
        };
        ?>
        <tr>
            <td><?= Formatter::escape($run['branch_name']) ?></td>
            <td><?= Formatter::escape($run['period_label']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($run['net_total']) ?></td>
            <td><?= $badge ?></td>
            <td><?= Formatter::date($run['actioned_at']) ?></td>
            <td>
                <a href="/owner/payroll/<?= (int) $run['id'] ?>/review"
                   class="btn btn-secondary btn-sm">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
