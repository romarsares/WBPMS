<?php
/**
 * View: hr/reports/index  (GET /hr/reports)
 * Variables: $totalEmployees, $approvedPayroll, $approvedNetPay, $totalRequests, $payrollSummary
 */
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
        ['label'=>'Payroll Summary',      'type'=>'payroll',     'icon'=>'₱'],
        ['label'=>'Attendance Report',    'type'=>'attendance',  'icon'=>'◷'],
        ['label'=>'Leave/Request Report', 'type'=>'requests',    'icon'=>'▤'],
        ['label'=>'Contributions Report', 'type'=>'contributions','icon'=>'♦'],
        ['label'=>'13th Month Pay',       'type'=>'13th_month',  'icon'=>'★'],
        ['label'=>'Employee List',        'type'=>'employees',   'icon'=>'♟'],
    ];
    foreach ($reportTypes as $rt): ?>
    <a href="<?= $rt['type'] === '13th_month'
        ? $base . '/hr/reports/13th-month'
        : $base . '/hr/reports/' . htmlspecialchars($rt['type']) ?>"
       class="card" style="text-decoration:none;display:flex;align-items:center;gap:.75rem;padding:1rem">
        <span style="font-size:1.5rem"><?= $rt['icon'] ?></span>
        <span style="font-weight:500"><?= htmlspecialchars($rt['label']) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Approved payroll summary -->
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
            <td style="font-size:.8rem"><?= htmlspecialchars((string)$r['approved_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
