<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Employee — My Attendance view.
 *
 * Variables injected by controller:
 *   list<array{
 *     date:string, timeIn:?string, timeOut:?string,
 *     workedMinutes:int, lateMinutes:int,
 *     undertimeMinutes:int, overtimeMinutes:int,
 *     flags:list<string>
 *   }> $attendance
 */
$attendance = $attendance ?? [];
?>

<div class="page-head">
    <div>
        <h1>My Attendance</h1>
        <p>View your biometric attendance records.</p>
    </div>
</div>

<div class="panel table-wrap">
    <table>
        <thead>
            <tr>
                <th>DATE</th>
                <th>TIME IN</th>
                <th>TIME OUT</th>
                <th>WORKED</th>
                <th>LATE</th>
                <th>UNDERTIME</th>
                <th>OVERTIME</th>
                <th>FLAGS</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($attendance)): ?>
            <tr>
                <td colspan="8" class="muted" style="text-align:center;padding:28px">
                    No attendance records found.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($attendance as $day): ?>
                <tr>
                    <td><?= Formatter::date($day['date']) ?></td>
                    <td><?= Formatter::time($day['timeIn']) ?></td>
                    <td><?= Formatter::time($day['timeOut']) ?></td>
                    <td>
                        <?= intdiv($day['workedMinutes'], 60) ?>h
                        <?= $day['workedMinutes'] % 60 ?>m
                    </td>
                    <td><?= (int) $day['lateMinutes'] ?>m</td>
                    <td><?= (int) $day['undertimeMinutes'] ?>m</td>
                    <td><?= (int) $day['overtimeMinutes'] ?>m</td>
                    <td>
                        <?php if (!empty($day['flags'])): ?>
                            <?php foreach ($day['flags'] as $flag): ?>
                                <span class="badge wait"><?= Formatter::escape($flag) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="badge ok">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
