<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var int $totalEmployees @var int $approvedPayroll @var float $approvedNetPay @var int $totalRequests @var array[] $payrollSummary @var string $base */
?>
<div class="page-head">
    <div><h1>Reports</h1><p>Payroll summaries, attendance, and contribution reports.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $totalEmployees ?></strong><span>Active Employees</span></div>
    <div class="stat"><strong><?= $approvedPayroll ?></strong><span>Approved Payroll Runs</span></div>
    <div class="stat"><strong>₱<?= number_format($approvedNetPay, 2) ?></strong><span>Total Net Pay Released</span></div>
    <div class="stat"><strong><?= $totalRequests ?></strong><span>Total Requests</span></div>
</div>

<!-- Report type cards -->
<div class="report-grid" style="margin-bottom:20px">
    <div class="panel">
        <h2>▤ Payroll Report</h2>
        <p>View approved payroll summaries by period and branch. Print payslips and deposit-slip preparation lists.</p>
        <a class="btn-secondary" href="<?= $base ?>/payroll">Open Payroll →</a>
    </div>
    <div class="panel">
        <h2>◷ Attendance Report</h2>
        <p>Review employee timesheets, late, undertime, overtime, and incomplete records by period.</p>
        <a class="btn-secondary" href="<?= $base ?>/attendance">Open Attendance →</a>
    </div>
    <div class="panel">
        <h2>▱ Request Report</h2>
        <p>Summary of leave, overtime, and cash advance requests by status and type.</p>
        <a class="btn-secondary" href="<?= $base ?>/requests">Open Requests →</a>
    </div>
    <div class="panel">
        <h2>＋ Contribution Report</h2>
        <p>SSS, PhilHealth, and Pag-IBIG contribution records per period.</p>
        <a class="btn-secondary" href="<?= $base ?>/benefits">Open Contributions →</a>
    </div>
</div>

<!-- Approved payroll summary -->
<div class="panel table-wrap" style="padding:0">
    <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
        <strong>Approved Payroll Summary</strong>
    </div>
    <table>
        <thead>
            <tr>
                <th>PERIOD</th><th>BRANCH</th><th>EMPLOYEES</th>
                <th>GROSS</th><th>NET PAY</th><th>APPROVED</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($payrollSummary)): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:28px">No approved payroll runs yet.</td></tr>
        <?php else: foreach ($payrollSummary as $r): ?>
            <tr>
                <td style="font-weight:700"><?= Formatter::escape($r['period_label']) ?></td>
                <td><?= Formatter::escape($r['branch_name']) ?></td>
                <td style="text-align:center"><?= (int)$r['emp_count'] ?></td>
                <td>₱<?= number_format((float)$r['gross'], 2) ?></td>
                <td style="font-weight:700">₱<?= number_format((float)$r['net'], 2) ?></td>
                <td><?= Formatter::date($r['approved_at'] ?? '') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
