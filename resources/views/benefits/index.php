<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $policies @var array[] $records @var int $totalPolicies @var int $active @var int $totalRecords @var int $programs @var string $base */
?>
<div class="page-head">
    <div><h1>Benefits &amp; Deductions</h1><p>SSS, PhilHealth, and Pag-IBIG contribution policies and records.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $programs ?></strong><span>Programs</span></div>
    <div class="stat"><strong><?= $totalPolicies ?></strong><span>Policy Versions</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $active ?></strong><span>Approved Policies</span></div>
    <div class="stat"><strong><?= $totalRecords ?></strong><span>Contribution Records</span></div>
</div>

<div class="grid-two">
    <!-- Policy versions -->
    <div class="panel table-wrap" style="padding:0">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
            <strong>Contribution Policy Versions</strong>
        </div>
        <table>
            <thead>
                <tr><th>PROGRAM</th><th>VERSION</th><th>EFFECTIVE FROM</th><th>EFFECTIVE TO</th><th>STATUS</th></tr>
            </thead>
            <tbody>
            <?php if (empty($policies)): ?>
                <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">No policies defined.</td></tr>
            <?php else: foreach ($policies as $p):
                $badge = $p['status'] === 'Approved' ? 'ok' : 'off'; ?>
                <tr>
                    <td style="font-weight:700"><?= Formatter::escape($p['program']) ?></td>
                    <td><?= Formatter::escape($p['version']) ?></td>
                    <td><?= Formatter::date($p['effective_from']) ?></td>
                    <td><?= $p['effective_to'] ? Formatter::date($p['effective_to']) : '<span class="muted">Current</span>' ?></td>
                    <td><span class="badge <?= $badge ?>"><?= Formatter::escape($p['status']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent records -->
    <div class="panel table-wrap" style="padding:0">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
            <strong>Recent Contribution Records</strong>
        </div>
        <table>
            <thead>
                <tr><th>EMPLOYEE</th><th>PROGRAM</th><th>PERIOD</th><th>EE SHARE</th><th>ER SHARE</th><th>STATUS</th></tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No records yet.</td></tr>
            <?php else: foreach ($records as $r):
                $badge = $r['status'] === 'Locked' ? 'ok' : 'wait'; ?>
                <tr>
                    <td>
                        <div style="font-weight:700"><?= Formatter::escape($r['employee_name']) ?></div>
                        <div class="muted"><?= Formatter::escape($r['employee_number']) ?></div>
                    </td>
                    <td><?= Formatter::escape($r['program']) ?></td>
                    <td class="muted" style="font-size:13px"><?= Formatter::date($r['period_start']) ?> – <?= Formatter::date($r['period_end']) ?></td>
                    <td>₱<?= number_format((float)$r['employee_share'], 2) ?></td>
                    <td>₱<?= number_format((float)$r['employer_share'], 2) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
