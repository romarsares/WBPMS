<?php
use Wbpms\Http\View\Formatter;
/** @var array[] $schedules @var array[] $holidays @var int $total @var int $active @var int $hTotal @var int $upcoming @var string $base */
?>
<div class="page-head">
    <div><h1>Work Schedule</h1><p>Schedule configurations and holiday calendar.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Schedules</span></div>
    <div class="stat"><strong><?= $active ?></strong><span>Active</span></div>
    <div class="stat"><strong><?= $hTotal ?></strong><span>Total Holidays</span></div>
    <div class="stat"><strong><?= $upcoming ?></strong><span>Upcoming Holidays</span></div>
</div>

<div class="grid-two">
    <!-- Work schedules -->
    <div class="panel table-wrap" style="padding:0">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
            <strong>Work Schedules</strong>
        </div>
        <table>
            <thead>
                <tr><th>EMPLOYEE</th><th>DAYS</th><th>START</th><th>END</th><th>BREAK</th><th>EFFECTIVE</th><th>STATUS</th></tr>
            </thead>
            <tbody>
            <?php if (empty($schedules)): ?>
                <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No schedules defined.</td></tr>
            <?php else: foreach ($schedules as $s):
                $badge = $s['status'] === 'Active' ? 'ok' : 'off';
                // working_days is stored as a JSON array — decode for display
                $days = $s['work_days'] ?? '[]';
                if (is_string($days)) {
                    $decoded = json_decode($days, true);
                    $days = is_array($decoded) ? implode(', ', array_map(fn($d) => substr($d, 0, 3), $decoded)) : $days;
                }
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700"><?= Formatter::escape($s['employee_name']) ?></div>
                        <div class="muted"><?= Formatter::escape($s['employee_number']) ?></div>
                    </td>
                    <td style="font-size:13px"><?= Formatter::escape($days) ?></td>
                    <td><?= Formatter::escape($s['time_in'] ?? '—') ?></td>
                    <td><?= Formatter::escape($s['time_out'] ?? '—') ?></td>
                    <td><?= (int)$s['break_minutes'] ?>m</td>
                    <td>
                        <?= Formatter::date($s['effective_from']) ?>
                        <?php if ($s['effective_to']): ?>
                            – <?= Formatter::date($s['effective_to']) ?>
                        <?php else: ?>
                            <span class="muted">– current</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $badge ?>"><?= Formatter::escape($s['status']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Holiday calendar -->
    <div class="panel table-wrap" style="padding:0">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
            <strong>Upcoming &amp; Recent Holidays</strong>
        </div>
        <table>
            <thead>
                <tr><th>HOLIDAY</th><th>DATE</th><th>TYPE</th></tr>
            </thead>
            <tbody>
            <?php if (empty($holidays)): ?>
                <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">No holidays on record.</td></tr>
            <?php else: foreach ($holidays as $h):
                $badge = $h['holiday_type'] === 'Regular' ? 'ok' : 'wait'; ?>
                <tr>
                    <td style="font-weight:700"><?= Formatter::escape($h['holiday_name']) ?></td>
                    <td><?= Formatter::date($h['holiday_date']) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= Formatter::escape($h['holiday_type']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
