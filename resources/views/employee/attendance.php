<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $present @var int $incomplete @var int $totalOT @var string $base */
$fmt = fn(int $m) => intdiv($m,60).'h '.str_pad((string)($m%60),2,'0',STR_PAD_LEFT).'m';
?>
<div class="page-head">
    <div><h1>My Attendance</h1><p>Your biometric attendance records (last 90 days).</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Days</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $present ?></strong><span>Complete</span></div>
    <div class="stat"><strong style="color:var(--bad)"><?= $incomplete ?></strong><span>Incomplete</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $fmt($totalOT) ?></strong><span>Total Overtime</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Filter by date..." oninput="filterRows(this,'attTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="attTable">
        <thead>
            <tr>
                <th>DATE</th><th>TIME IN</th><th>TIME OUT</th>
                <th>WORKED</th><th>LATE</th><th>UNDERTIME</th><th>OVERTIME</th><th>STATUS</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;padding:28px">No attendance records found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td style="font-weight:700"><?= Formatter::date($r['attendance_date']) ?></td>
                <td><?= $r['time_in']  ? Formatter::time($r['time_in'])  : '<span class="muted">—</span>' ?></td>
                <td><?= $r['time_out'] ? Formatter::time($r['time_out']) : '<span class="muted">—</span>' ?></td>
                <td><?= $fmt((int)$r['worked_minutes']) ?></td>
                <td><?= (int)$r['late_minutes']      > 0 ? '<span style="color:var(--bad)">'.$fmt((int)$r['late_minutes']).'</span>'      : '—' ?></td>
                <td><?= (int)$r['undertime_minutes'] > 0 ? '<span style="color:var(--wait)">'.$fmt((int)$r['undertime_minutes']).'</span>' : '—' ?></td>
                <td><?= (int)$r['overtime_minutes']  > 0 ? '<span style="color:var(--ok)">'.$fmt((int)$r['overtime_minutes']).'</span>'   : '—' ?></td>
                <td>
                    <?php if ($r['is_incomplete']): ?>
                        <span class="badge bad">Incomplete</span>
                    <?php else: ?>
                        <span class="badge ok">OK</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
