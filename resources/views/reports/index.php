<?php
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
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/payroll">View →</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=payroll" target="_blank">🖨 Print</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=payroll&amp;format=csv">⬇ CSV</a>
        </div>
    </div>
    <div class="panel">
        <h2>◷ Attendance Report</h2>
        <p>Review employee timesheets, late, undertime, overtime, and incomplete records by period.</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/attendance">View →</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=attendance" target="_blank">🖨 Print</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=attendance&amp;format=csv">⬇ CSV</a>
        </div>
    </div>
    <div class="panel">
        <h2>▱ Request Report</h2>
        <p>Summary of leave, overtime, and cash advance requests by status and type.</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/requests">View →</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=requests" target="_blank">🖨 Print</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=requests&amp;format=csv">⬇ CSV</a>
        </div>
    </div>
    <div class="panel">
        <h2>＋ Contribution Report</h2>
        <p>SSS, PhilHealth, and Pag-IBIG contribution records per period.</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/contributions">View →</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=contributions" target="_blank">🖨 Print</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=contributions&amp;format=csv">⬇ CSV</a>
        </div>
    </div>
    <div class="panel">
        <h2>✦ 13th Month Report</h2>
        <p>Annual 13th month pay computation per employee.</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/13th-month">View →</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=13th_month" target="_blank">🖨 Print</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=13th_month&amp;format=csv">⬇ CSV</a>
        </div>
    </div>
    <div class="panel">
        <h2>👤 Employee List</h2>
        <p>Full employee directory with branch and status.</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/employees">View →</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=employees" target="_blank">🖨 Print</a>
            <a class="btn-secondary" href="<?= $base ?>/hr/reports/export?type=employees&amp;format=csv">⬇ CSV</a>
        </div>
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
                <td style="font-weight:700">
                    <?= Formatter::date($r['period_start']) ?> – <?= Formatter::date($r['period_end']) ?>
                </td>
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
