<?php
/** @var array<string,mixed> $attendance */
/** @var list<string> $errors */
/** @var array<string,string> $old */

use Wbpms\Http\View\Formatter;

$value = static function (string $key, string $fallback = '') use ($old): string {
    return (string) ($old[$key] ?? $fallback);
};
$timeValue = static fn (?string $time): string => $time === null ? '' : substr($time, 0, 5);
?>

<div class="page-header">
    <div>
        <h1>Adjust Attendance</h1>
        <p>Correct a biometric exception or enter approved manual overtime. The original punches remain unchanged.</p>
    </div>
    <a href="<?= $base ?>/hr/attendance" class="btn btn-secondary">Back to Attendance</a>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <div><?= Formatter::escape($error) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem">
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem">
        <div><small>Employee</small><br><strong><?= Formatter::escape((string) $attendance['employee_name']) ?></strong><br><small><?= Formatter::escape((string) $attendance['employee_number']) ?></small></div>
        <div><small>Date / Schedule</small><br><strong><?= Formatter::date((string) $attendance['attendance_date']) ?></strong><br><small><?= Formatter::escape((string) $attendance['schedule_name']) ?></small></div>
        <div><small>Current status</small><br><strong><?= Formatter::escape((string) $attendance['status']) ?></strong><br><small>Worked <?= (int) $attendance['hours_worked_minutes'] ?> min · OT <?= (int) $attendance['overtime_minutes'] ?> min</small></div>
    </div>
</div>

<div class="card" style="max-width:760px">
    <form method="POST" action="<?= $base ?>/hr/attendance/<?= (int) $attendance['attendance_id'] ?>/adjust">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <p style="margin-top:0;color:#6b7280;font-size:.9rem">Enter both times to resolve an incomplete record. Worked, late, undertime, and standard overtime are recalculated from the assigned schedule.</p>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem">
            <div>
                <label for="time_in">Time in</label>
                <input id="time_in" name="time_in" type="time" required value="<?= Formatter::escape($value('time_in', $timeValue($attendance['time_in']))) ?>">
            </div>
            <div>
                <label for="time_out">Time out</label>
                <input id="time_out" name="time_out" type="time" required value="<?= Formatter::escape($value('time_out', $timeValue($attendance['time_out']))) ?>">
            </div>
        </div>

        <div style="margin-top:1rem">
            <label for="manual_overtime_minutes">Manual overtime minutes <small style="font-weight:normal;color:#6b7280">(optional override)</small></label>
            <input id="manual_overtime_minutes" name="manual_overtime_minutes" type="number" min="0" max="1440" step="1"
                   value="<?= Formatter::escape($value('manual_overtime_minutes')) ?>" style="max-width:240px">
            <small style="display:block;color:#6b7280;margin-top:.3rem">Leave blank to use the overtime calculated from time in/out. Enter 0 to explicitly remove overtime.</small>
        </div>

        <div style="margin-top:1rem">
            <label for="reason">Reason and supporting reference</label>
            <textarea id="reason" name="reason" required maxlength="1000" rows="4" placeholder="e.g. Supervisor-approved overtime, gate log reference, or missed biometric punch."><?= Formatter::escape($value('reason')) ?></textarea>
        </div>

        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.75rem 1rem;margin:1rem 0;color:#92400e;font-size:.875rem">
            Changes are blocked if the related payroll is approved. If a payroll is only computed or pending, recompute it after saving this correction.
        </div>
        <button type="submit" class="btn btn-primary">Save audited adjustment</button>
    </form>
</div>
