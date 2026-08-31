<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $active @var float $avgRate @var float $maxRate @var string $base */
?>
<div class="page-head">
    <div><h1>Salary Management</h1><p>Daily rates and salary history per employee.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Salary Records</span></div>
    <div class="stat"><strong><?= $active ?></strong><span>Active Rates</span></div>
    <div class="stat"><strong>₱<?= number_format($avgRate, 2) ?></strong><span>Average Daily Rate</span></div>
    <div class="stat"><strong>₱<?= number_format($maxRate, 2) ?></strong><span>Highest Daily Rate</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search salary records..." oninput="filterRows(this,'salaryTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="salaryTable">
        <thead>
            <tr><th>EMPLOYEE</th><th>BRANCH</th><th>DAILY RATE</th><th>EFFECTIVE FROM</th><th>STATUS</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:28px">No salary records found.</td></tr>
        <?php else: foreach ($rows as $r):
            $badge = $r['status'] === 'Active' ? 'ok' : 'off'; ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?= Formatter::escape($r['employee_name']) ?></div>
                    <div class="muted"><?= Formatter::escape($r['employee_number']) ?></div>
                </td>
                <td><?= Formatter::escape($r['branch_name'] ?? '—') ?></td>
                <td style="font-weight:700">₱<?= number_format((float)$r['daily_rate'], 2) ?></td>
                <td><?= Formatter::date($r['effective_from']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
