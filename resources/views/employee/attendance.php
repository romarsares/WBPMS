<?php
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $present @var int $incomplete @var int $totalOT @var string $dateFrom @var string $dateTo @var string $base */
$fmt      = fn(int $m) => intdiv($m,60).'h '.str_pad((string)($m%60),2,'0',STR_PAD_LEFT).'m';
$dateFrom ??= '';
$dateTo   ??= '';
$filtered = $dateFrom !== '' || $dateTo !== '';
?>
<div class="page-head">
    <div><h1>My Attendance</h1><p>Your biometric attendance records.</p></div>
    <a href="<?= $base ?>/employee/dashboard" class="btn btn-secondary">← Back to Dashboard</a>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Days</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $present ?></strong><span>Complete</span></div>
    <div class="stat"><strong style="color:var(--bad)"><?= $incomplete ?></strong><span>Incomplete</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $fmt($totalOT) ?></strong><span>Total Overtime</span></div>
</div>

<!-- From / To date filter -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/employee/attendance"
          style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">From</label>
            <input type="date" name="date_from"
                   value="<?= Formatter::escape($dateFrom) ?>"
                   style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">To</label>
            <input type="date" name="date_to"
                   value="<?= Formatter::escape($dateTo) ?>"
                   style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($filtered): ?>
        <a href="<?= $base ?>/employee/attendance" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
    <?php if ($filtered): ?>
    <p style="margin:.6rem 0 0;font-size:.8rem;color:#6b7280">
        Showing records<?= $dateFrom !== '' ? ' from <strong>' . Formatter::escape($dateFrom) . '</strong>' : '' ?><?= $dateTo !== '' ? ' to <strong>' . Formatter::escape($dateTo) . '</strong>' : '' ?>.
    </p>
    <?php else: ?>
    <p style="margin:.6rem 0 0;font-size:.8rem;color:#6b7280">Showing the last 90 days. Use the date filter to view a specific range.</p>
    <?php endif; ?>
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
                    <?php
                    if ($r['is_incomplete']) {
                        echo '<span class="badge bad">Incomplete</span>';
                    } elseif ((int)$r['late_minutes'] > 0) {
                        echo '<span class="badge wait">Late</span>';
                    } elseif ((int)$r['overtime_minutes'] > 0) {
                        echo '<span class="badge ok">Complete + OT</span>';
                    } else {
                        echo '<span class="badge ok">Complete</span>';
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

