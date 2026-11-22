<?php
/**
 * View: hr/reports/index  (GET /hr/reports)
 * Variables: $totalEmployees, $approvedPayroll, $approvedNetPay, $totalRequests, $payrollSummary, $page, $totalPages, $pageSize, $total
 */
$page ??= 1;
$totalPages ??= 1;
$pageSize ??= 50;
$total ??= 0;
?>
<div class="page-head">
    <div>
        <h1>Reports Management</h1>
        <p>Payroll, attendance, request, and contribution reports.</p>
    </div>
    <a href="<?= $base ?>/hr/reports/export" class="btn btn-secondary">Export / Print</a>
</div>

<!-- Summary -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int)$totalEmployees ?></span><span class="stat-label">Active Employees</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$approvedPayroll ?></span><span class="stat-label">Approved Payroll Runs</span></div>
    <div class="stat-card"><span class="stat-value">₱<?= number_format($approvedNetPay, 2) ?></span><span class="stat-label">Total Net Pay Approved</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$totalRequests ?></span><span class="stat-label">Total Requests</span></div>
</div>

<!-- Quick reports -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <?php
    $reportTypes = [
        ['label'=>'Payroll Summary',      'type'=>'payroll',       'icon'=>'₱'],
        ['label'=>'Attendance Report',    'type'=>'attendance',    'icon'=>'◷'],
        ['label'=>'Attendance Frequency', 'type'=>'frequency',     'icon'=>'▥'],
        ['label'=>'Leave/Request Report', 'type'=>'requests',      'icon'=>'▤'],
        ['label'=>'Contributions Report', 'type'=>'contributions', 'icon'=>'♦'],
        ['label'=>'13th Month Pay',       'type'=>'13th_month',    'icon'=>'★'],
        ['label'=>'Transaction Slips',    'type'=>'transaction_slips', 'icon'=>'🏦'],
        ['label'=>'Employee List',        'type'=>'employees',     'icon'=>'♟'],
        ['label'=>'Employee Payslip Report', 'type'=>'payslips',   'icon'=>'🧾'],
    ];
    foreach ($reportTypes as $rt): ?>
    <a href="<?= $rt['type'] === '13th_month'
        ? $base . '/hr/reports/13th-month'
        : ($rt['type'] === 'frequency'
            ? $base . '/hr/attendance/frequency-report'
            : ($rt['type'] === 'transaction_slips'
                ? $base . '/hr/reports/transaction-slips'
                : $base . '/hr/reports/' . htmlspecialchars($rt['type']))) ?>"
       class="card" style="text-decoration:none;display:flex;align-items:center;gap:.75rem;padding:1rem">
        <span style="font-size:1.5rem"><?= $rt['icon'] ?></span>
        <span style="font-weight:500"><?= htmlspecialchars($rt['label']) ?></span>
    </a>
    <?php endforeach; ?>
    <!-- Audit Trail — accessible to HRHead and BusinessOwner -->
    <a href="<?= $base ?>/hr/audit"
       class="card" style="text-decoration:none;display:flex;align-items:center;gap:.75rem;padding:1rem">
        <span style="font-size:1.5rem">🔍</span>
        <span style="font-weight:500">Audit Trail</span>
    </a>
</div>

<!-- Approved payroll summary -->
<div class="card" style="margin-bottom:1rem;padding:0.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem">
    <form method="GET" action="<?= $base ?>/hr/reports" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
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

<div class="card">
    <h3 style="margin:0 0 1rem">Approved Payroll Runs</h3>
    <?php if ($payrollSummary === []): ?>
    <p style="color:#6b7280">No approved payroll runs yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Branch</th>
                <th>Period</th>
                <th style="text-align:center">Employees</th>
                <th style="text-align:right">Gross</th>
                <th style="text-align:right">Net</th>
                <th>Approved At</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($payrollSummary as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['branch_name']) ?></td>
            <td><?= htmlspecialchars($r['period_start'].' – '.$r['period_end']) ?></td>
            <td style="text-align:center"><?= (int)$r['emp_count'] ?></td>
            <td style="text-align:right">₱<?= number_format((float)($r['gross'] ?? 0), 2) ?></td>
            <td style="text-align:right;font-weight:600">₱<?= number_format((float)($r['net'] ?? 0), 2) ?></td>
            <td style="font-size:.8rem"><?= \Wbpms\Http\View\Formatter::date($r['approved_at']) ?></td>
        </tr>
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
                <a href="<?= $base ?>/hr/reports?page=<?= $page - 1 ?>&page_size=<?= $pageSize ?>"
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
                <a href="<?= $base ?>/hr/reports?page=1&page_size=<?= $pageSize ?>"
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
                <a href="<?= $base ?>/hr/reports?page=<?= $p ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <li>
                <a href="<?= $base ?>/hr/reports?page=<?= $totalPages ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <li>
                <a href="<?= $base ?>/hr/reports?page=<?= $page + 1 ?>&page_size=<?= $pageSize ?>"
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
