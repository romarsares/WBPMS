<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $complete @var int $incomplete @var int $unmatched @var string $base @var string $csrfField */
$fmt = fn(int $m) => intdiv($m,60).'h '.str_pad((string)($m%60),2,'0',STR_PAD_LEFT).'m';
?>
<div class="page-head">
    <div><h1>Attendance Management</h1><p>Biometric import logs and employee timesheets.</p></div>
    <a class="btn-primary" href="<?= $base ?>/attendance/import">⬆ Import Workbook</a>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Records</span></div>
    <div class="stat"><strong><?= $complete ?></strong><span>Complete</span></div>
    <div class="stat"><strong style="color:var(--bad)"><?= $incomplete ?></strong><span>Incomplete</span></div>
    <div class="stat"><strong style="color:var(--wait)"><?= $unmatched ?></strong><span>Unmatched Punches</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search attendance..." oninput="filterRows(this,'attTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="attTable">
        <thead>
            <tr>
                <th>EMPLOYEE</th><th>BRANCH</th><th>DATE</th>
                <th>TIME IN</th><th>TIME OUT</th>
                <th>WORKED</th><th>LATE</th><th>UNDERTIME</th><th>OT</th><th>STATUS</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="10" class="muted" style="text-align:center;padding:28px">No attendance records. Import a workbook to begin.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?= Formatter::escape($r['employee_name']) ?></div>
                    <div class="muted"><?= Formatter::escape($r['employee_number']) ?></div>
                </td>
                <td><?= Formatter::escape($r['branch_name'] ?? '—') ?></td>
                <td><?= Formatter::date($r['attendance_date']) ?></td>
                <td><?= $r['time_in']  ? Formatter::time($r['time_in'])  : '<span class="muted">—</span>' ?></td>
                <td><?= $r['time_out'] ? Formatter::time($r['time_out']) : '<span class="muted">—</span>' ?></td>
                <td><?= $fmt((int)$r['worked_minutes']) ?></td>
                <td><?= (int)$r['late_minutes'] > 0 ? '<span style="color:var(--bad)">'.$fmt((int)$r['late_minutes']).'</span>' : '—' ?></td>
                <td><?= (int)$r['undertime_minutes'] > 0 ? '<span style="color:var(--wait)">'.$fmt((int)$r['undertime_minutes']).'</span>' : '—' ?></td>
                <td><?= (int)$r['overtime_minutes'] > 0 ? '<span style="color:var(--ok)">'.$fmt((int)$r['overtime_minutes']).'</span>' : '—' ?></td>
                <td><?php if ($r['is_incomplete']): ?>
                    <span class="badge bad">Incomplete</span>
                <?php else: ?>
                    <span class="badge ok">OK</span>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
