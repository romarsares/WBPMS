<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var float $totalNet @var float $latestNet @var string $base @var string $displayName */
?>
<div class="page-head">
    <div><h1>My Payslips</h1><p>Your approved payslips from completed payroll runs.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Available Payslips</span></div>
    <div class="stat"><strong>₱<?= number_format($latestNet, 2) ?></strong><span>Latest Net Pay</span></div>
    <div class="stat"><strong>₱<?= number_format($totalNet, 2) ?></strong><span>Total Net Pay (All Time)</span></div>
    <div class="stat"><strong>4</strong><span>Sick Leaves / Year</span></div>
</div>
<div class="panel table-wrap">
    <table>
        <thead>
            <tr><th>PERIOD</th><th>BRANCH</th><th>GROSS PAY</th><th>DEDUCTIONS</th><th>NET PAY</th><th>APPROVED</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:28px">No payslips available yet. Payslips appear here once payroll is approved.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td style="font-weight:700"><?= Formatter::escape($r['period_label']) ?></td>
                <td><?= Formatter::escape($r['branch_name']) ?></td>
                <td>₱<?= number_format((float)$r['gross_pay'], 2) ?></td>
                <td style="color:var(--bad)">₱<?= number_format((float)$r['total_deductions'], 2) ?></td>
                <td style="font-weight:700">₱<?= number_format((float)$r['net_pay'], 2) ?></td>
                <td><?= Formatter::date($r['approved_at'] ?? '') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
