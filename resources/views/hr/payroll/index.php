<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Payroll runs list.
 *
 * @var list<array{
 *     id: int,
 *     branch_name: string,
 *     period_label: string,
 *     status: string,
 *     employee_count: int,
 *     gross_total: string,
 *     net_total: string,
 *     created_at: string,
 *     submitted_at: string|null,
 *     return_reason: string|null
 * }> $runs
 * @var string $filterStatus
 * @var list<array{id:int,name:string}> $branches
 * @var string $filterBranch
 */

$runs         ??= [];
$filterStatus ??= '';
$filterBranch ??= '';
$branches     ??= [];

$statusBadge = static function (string $status): string {
    return match ($status) {
        'Draft'                => '<span class="badge badge-gray">Draft</span>',
        'Computed'             => '<span class="badge badge-blue">Computed</span>',
        'PendingOwnerApproval' => '<span class="badge badge-yellow">Pending Approval</span>',
        'Approved'             => '<span class="badge badge-green">Approved</span>',
        'Returned'             => '<span class="badge badge-red">Returned</span>',
        default                => '<span class="badge badge-gray">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
};
?>

<div class="page-header">
    <h1>Payroll Runs</h1>
    <a href="/hr/payroll/run" class="btn btn-primary">New Payroll Run</a>
</div>

<!-- Filters -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="/hr/payroll"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Branch</label>
            <select name="branch" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= (int) $b['id'] ?>"
                    <?= ((string) $b['id'] === $filterBranch) ? 'selected' : '' ?>>
                    <?= Formatter::escape($b['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Status</label>
            <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All statuses</option>
                <option value="Draft"                <?= $filterStatus === 'Draft'                ? 'selected' : '' ?>>Draft</option>
                <option value="Computed"             <?= $filterStatus === 'Computed'             ? 'selected' : '' ?>>Computed</option>
                <option value="PendingOwnerApproval" <?= $filterStatus === 'PendingOwnerApproval' ? 'selected' : '' ?>>Pending Approval</option>
                <option value="Approved"             <?= $filterStatus === 'Approved'             ? 'selected' : '' ?>>Approved</option>
                <option value="Returned"             <?= $filterStatus === 'Returned'             ? 'selected' : '' ?>>Returned</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filterStatus !== '' || $filterBranch !== ''): ?>
        <a href="/hr/payroll" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <?php if (empty($runs)): ?>
    <p style="padding:1.5rem;color:#6b7280;font-size:.875rem;margin:0">
        No payroll runs yet. <a href="/hr/payroll/run">Generate one →</a>
    </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:center">Employees</th>
                <th style="text-align:right">Gross Total</th>
                <th style="text-align:right">Net Total</th>
                <th>Status</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($runs as $run): ?>
        <tr>
            <td><?= Formatter::escape($run['branch_name']) ?></td>
            <td><?= Formatter::escape($run['period_label']) ?></td>
            <td style="text-align:center"><?= (int) $run['employee_count'] ?></td>
            <td style="text-align:right">
                <?= $run['gross_total'] !== '' ? '₱' . Formatter::escape($run['gross_total']) : '—' ?>
            </td>
            <td style="text-align:right">
                <?= $run['net_total'] !== '' ? '₱' . Formatter::escape($run['net_total']) : '—' ?>
            </td>
            <td><?= $statusBadge($run['status']) ?></td>
            <td><?= Formatter::date($run['created_at']) ?></td>
            <td style="white-space:nowrap">
                <a href="/hr/payroll/<?= (int) $run['id'] ?>"
                   class="btn btn-secondary btn-sm">View</a>
                <?php if ($run['status'] === 'Computed'): ?>
                <a href="/hr/payroll/<?= (int) $run['id'] ?>/submit"
                   class="btn btn-primary btn-sm" style="margin-left:.35rem">Submit</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php if (!empty($run['return_reason']) && $run['status'] === 'Returned'): ?>
        <tr style="background:#fef2f2">
            <td colspan="8" style="padding:.4rem .85rem;font-size:.8rem;color:#991b1b">
                <strong>Return reason:</strong> <?= Formatter::escape($run['return_reason']) ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
