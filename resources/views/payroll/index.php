<?php
use Wbpms\Http\View\Formatter;
/** @var array[] $runs @var int $total @var int $draft @var int $pending @var int $approved @var string $base @var string $roleName */
?>
<div class="page-head">
    <div><h1>Payroll</h1><p>Payroll runs, computation, and approval workflow.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Runs</span></div>
    <div class="stat"><strong><?= $draft ?></strong><span>Draft / Computed</span></div>
    <div class="stat"><strong style="color:var(--wait)"><?= $pending ?></strong><span>Pending Approval</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $approved ?></strong><span>Approved</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search payroll runs..." oninput="filterRows(this,'payrollTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="payrollTable">
        <thead>
            <tr>
                <th>BRANCH</th><th>PERIOD</th><th>EMPLOYEES</th>
                <th>GROSS TOTAL</th><th>NET TOTAL</th>
                <th>STATUS</th><th>CREATED</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($runs)): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">No payroll runs yet.</td></tr>
        <?php else: foreach ($runs as $r):
            $badge = match($r['status']) {
                'Draft'                 => 'off',
                'Computed'              => 'wait',
                'PendingOwnerApproval'  => 'wait',
                'Approved'              => 'ok',
                'Returned'              => 'bad',
                default                 => 'off',
            };
            $label = match($r['status']) {
                'PendingOwnerApproval' => 'Pending Approval',
                default => $r['status'],
            };
            // Build a human-readable period label from period_start and period_end
            $periodLabel = Formatter::date($r['period_start']) . ' – ' . Formatter::date($r['period_end']);
            ?>
            <tr>
                <td><?= Formatter::escape($r['branch_name']) ?></td>
                <td>
                    <div style="font-weight:700"><?= $periodLabel ?></div>
                    <div class="muted">Pay: <?= Formatter::date($r['pay_date']) ?></div>
                </td>
                <td style="text-align:center"><?= (int)$r['employee_count'] ?></td>
                <td>₱<?= number_format((float)$r['gross_total'], 2) ?></td>
                <td style="font-weight:700">₱<?= number_format((float)$r['net_total'], 2) ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($label) ?></span></td>
                <td><?= Formatter::date($r['created_at']) ?></td>
            </tr>
            <?php if (!empty($r['return_reason']) && $r['status'] === 'Returned'): ?>
            <tr style="background:#fdf2f2">
                <td colspan="7" style="padding:8px 12px;font-size:14px;color:var(--bad)">
                    <strong>Return reason:</strong> <?= Formatter::escape($r['return_reason']) ?>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
