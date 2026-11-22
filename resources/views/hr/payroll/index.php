<?php
/**
 * View: hr/payroll/index  (GET /hr/payroll)
 * Variables: $runs, $total, $draft, $pending, $approved, $page, $totalPages, $pageSize
 */
$runs ??= [];
$page ??= 1;
$totalPages ??= 1;
$pageSize ??= 50;
$statusBadge = static function (string $s): string {
    $map = [
        'Draft'                => '#6b7280',
        'Computed'             => '#3b82f6',
        'PendingOwnerApproval' => '#f59e0b',
        'Approved'             => '#10b981',
        'Returned'             => '#ef4444',
        'Cancelled'            => '#6b7280',
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
    <div style="display:flex;gap:.75rem">
        <a href="<?= $base ?>/hr/payroll/periods" class="btn btn-secondary">Manage Periods</a>
        <a href="<?= $base ?>/hr/payroll/create" class="btn btn-primary">+ New Payroll Run</a>
    </div>
</div>

<!-- Summary -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int)$total ?></span><span class="stat-label">Total Runs</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$draft ?></span><span class="stat-label">Draft/Computed</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= (int)$pending ?></span><span class="stat-label">Pending Approval</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= (int)$approved ?></span><span class="stat-label">Approved</span></div>
</div>

<div class="card" style="margin-bottom:1rem;padding:0.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem">
    <form method="GET" action="<?= $base ?>/hr/payroll" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto">
            <label for="page_size" style="font-size:.8rem;font-weight:500;color:#374151;white-space:nowrap">Per page</label>
            <select id="page_size" name="page_size" style="min-width:7rem;padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="10" <?= $pageSize === 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $pageSize === 20 ? 'selected' : '' ?>>20</option>
                <option value="30" <?= $pageSize === 30 ? 'selected' : '' ?>>30</option>
                <option value="50" <?= $pageSize === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $pageSize === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <?php if ($runs === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280">No payroll runs yet. <a href="<?= $base ?>/hr/payroll/create">Create one →</a></p>
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
            <td style="font-size:.8rem"><?= \Wbpms\Http\View\Formatter::date($r['created_at']) ?></td>
            <td style="white-space:nowrap">
                <a href="<?= $base ?>/hr/payroll/<?= (int)$r['payroll_run_id'] ?>" class="btn btn-sm btn-secondary">View</a>
            </td>
        </tr>
        <?php if ($r['status'] === 'Returned' && $r['return_reason']): ?>
        <tr style="background:#fef2f2">
            <td colspan="8" style="padding:.3rem .85rem;font-size:.8rem;color:#991b1b">
                <strong>Return reason:</strong> <?= htmlspecialchars((string)$r['return_reason']) ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($r['status'] === 'Cancelled' && !empty($r['cancellation_reason'])): ?>
        <tr style="background:#f9fafb">
            <td colspan="8" style="padding:.3rem .85rem;font-size:.8rem;color:#4b5563">
                <strong>Cancellation reason:</strong> <?= htmlspecialchars((string)$r['cancellation_reason']) ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="card" style="padding:1rem 1.25rem;margin-top:1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem">
    <div style="font-size:.875rem;color:#374151">
        Showing
        <strong><?= (int) (($page - 1) * $pageSize + 1) ?></strong>
        to
        <strong><?= (int) min($page * $pageSize, $total) ?></strong>
        of
        <strong><?= (int) $total ?></strong>
        payroll runs
    </div>
    <nav aria-label="Payroll runs pagination">
        <ul style="display:flex;gap:.25rem;list-style:none;margin:0;padding:0">
            <!-- Previous -->
            <?php if ($page > 1): ?>
            <li>
                <a href="<?= $base ?>/hr/payroll?page=<?= $page - 1 ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Previous page">« Prev</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">« Prev</span>
            </li>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1): ?>
            <li>
                <a href="<?= $base ?>/hr/payroll?page=1&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm">1</a>
            </li>
            <?php if ($start > 2): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <li>
                <?php if ($p === $page): ?>
                <span class="btn btn-primary btn-sm" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="<?= $base ?>/hr/payroll?page=<?= $p ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <li>
                <a href="<?= $base ?>/hr/payroll?page=<?= $totalPages ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <li>
                <a href="<?= $base ?>/hr/payroll?page=<?= $page + 1 ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Next page">Next »</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">Next »</span>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
