<?php


use Wbpms\Http\View\Formatter;

/**
 * Employee portal — Payslips list.
 *
 * @var list<array{
 *     id: int,
 *     period_label: string,
 *     branch_name: string,
 *     gross_pay: string,
 *     total_deductions: string,
 *     net_pay: string,
 *     approved_at: string
 * }> $payslips
 */

$payslips ??= [];
?>

<div class="page-header">
    <h1>My Payslips</h1>
</div>

<?php if (empty($payslips)): ?>
<div class="card" style="text-align:center;padding:2.5rem">
    <p style="color:#6b7280;font-size:.95rem;margin:0">
        No payslips available yet. Payslips appear here once a payroll run is approved.
    </p>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Branch</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Deductions</th>
                <th style="text-align:right;font-weight:700">Net Pay</th>
                <th>Approved</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($payslips as $ps): ?>
        <tr>
            <td style="font-weight:500"><?= Formatter::escape($ps['period_label']) ?></td>
            <td><?= Formatter::escape($ps['branch_name']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($ps['gross_pay']) ?></td>
            <td style="text-align:right;color:#dc2626">₱<?= Formatter::escape($ps['total_deductions']) ?></td>
            <td style="text-align:right;font-weight:700">₱<?= Formatter::escape($ps['net_pay']) ?></td>
            <td><?= Formatter::date($ps['approved_at']) ?></td>
            <td style="white-space:nowrap">
                <a href="<?= $base ?>/employee/payslips/<?= (int) $ps['id'] ?>"
                   class="btn btn-secondary btn-sm">View</a>
                <a href="<?= $base ?>/employee/payslips/<?= (int) $ps['id'] ?>/download"
                   class="btn btn-secondary btn-sm" style="margin-left:.35rem">Download</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
