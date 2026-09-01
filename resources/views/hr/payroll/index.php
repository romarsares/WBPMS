<?php
/**
 * View: hr/payroll/index  (GET /hr/payroll)
 * Variables: $runs, $total, $draft, $pending, $approved
 */
$runs ??= [];
$statusBadge = static function (string $s): string {
    $map = [
        'Draft'                => '#6b7280',
        'Computed'             => '#3b82f6',
        'PendingOwnerApproval' => '#f59e0b',
        'Approved'             => '#10b981',
        'Returned'             => '#ef4444',
    ];
    $c = $map[$s] ?? '#6b7280';
    $label = $s === 'PendingOwnerApproval' ? 'Pending Approval' : $s;
    return "<span style='background:{$c};color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem'>" . htmlspecialchars($label) . "</span>";
};
?>
<div class="page-head">
    <div>
        <h1>Payroll</h1>
        <p>Manage payroll runs by branch and period.</p>
    </div>
    <a href="/hr/payroll/create" class="btn btn-primary">+ New Payroll Run</a>
</div>

<!-- Summary -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int)$total ?></span><span class="stat-label">Total Runs</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$draft ?></span><span class="stat-label">Draft/Computed</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= (int)$pending ?></span><span class="stat-label">Pending Approval</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= (int)$approved ?></span><span class="stat-label">Approved</span></div>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <?php if ($runs === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280">No payroll runs yet. <a href="/hr/payroll/create">Create one →</a></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:center">Employees</th>
                <th style="text-align:right">Gross Total</th>
                <th style="text-align:right">Net Total</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($runs as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['branch_name']) ?></td>
            <td><?= htmlspecialchars($r['period_start'].' – '.$r['period_end']) ?></td>
            <td style="text-align:center"><?= (int)$r['employee_count'] ?></td>
            <td style="text-align:right">₱<?= number_format((float)$r['gross_total'], 2) ?></td>
            <td style="text-align:right">₱<?= number_format((float)$r['net_total'], 2) ?></td>
            <td><?= $statusBadge($r['status']) ?></td>
            <td style="font-size:.8rem"><?= htmlspecialchars($r['created_at']) ?></td>
            <td style="white-space:nowrap">
                <a href="/hr/payroll/<?= (int)$r['payroll_run_id'] ?>" class="btn btn-sm btn-secondary">View</a>
            </td>
        </tr>
        <?php if ($r['status'] === 'Returned' && $r['return_reason']): ?>
        <tr style="background:#fef2f2">
            <td colspan="8" style="padding:.3rem .85rem;font-size:.8rem;color:#991b1b">
                <strong>Return reason:</strong> <?= htmlspecialchars((string)$r['return_reason']) ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
