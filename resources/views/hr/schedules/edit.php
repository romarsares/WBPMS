<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/schedules/edit  (GET /hr/schedules/{id}/edit)
 *
 * Variables:
 *   array        $schedule  — existing schedule row (keys from ScheduleRepository::findById)
 *   array        $errors    — validation errors
 */

$errors   = $errors   ?? [];
$schedule = $schedule ?? [];

$allDays  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$currentDays = [];
if (!empty($schedule['working_days'])) {
    $decoded = json_decode((string) $schedule['working_days'], true);
    if (is_array($decoded)) {
        $currentDays = $decoded;
    }
}

$v = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($schedule[$key] ?? $fallback));
?>

<div class="page-head">
    <div>
        <h1>Edit Schedule</h1>
        <p><a href="<?= $base ?>/hr/schedules">← Back to Work Schedules</a></p>
    </div>
</div>

<div class="card" style="max-width:640px">
    <form method="POST" action="<?= $base ?>/hr/schedules/<?= (int) $schedule['id'] ?>">
        <input type="hidden" name="_csrf"   value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_method" value="PUT">

        <!-- Schedule Name -->
        <div class="form-group">
            <label for="schedule_name">Schedule Name <span style="color:#dc2626">*</span></label>
            <input type="text" id="schedule_name" name="schedule_name"
                   value="<?= $v('schedule_name') ?>"
                   placeholder="e.g. Regular Shift, Morning Shift"
                   class="<?= isset($errors['schedule_name']) ? 'is-invalid' : '' ?>" required>
            <?php if (isset($errors['schedule_name'])): ?>
                <small class="field-error"><?= Formatter::escape($errors['schedule_name']) ?></small>
            <?php endif; ?>
        </div>

        <!-- Working Days -->
        <div class="form-group">
            <label>Working Days <span style="color:#dc2626">*</span></label>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem .75rem;margin-top:.35rem">
                <?php foreach ($allDays as $day): ?>
                <label style="display:flex;align-items:center;gap:.3rem;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="work_days[]" value="<?= $day ?>"
                           <?= in_array($day, $currentDays, true) ? 'checked' : '' ?>>
                    <?= substr($day, 0, 3) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['work_days'])): ?>
                <small class="field-error"><?= Formatter::escape($errors['work_days']) ?></small>
            <?php endif; ?>
        </div>

        <!-- Time In / Time Out -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
                <label for="time_in">Time In <span style="color:#dc2626">*</span></label>
                <input type="time" id="time_in" name="time_in"
                       value="<?= $v('time_in') ?>"
                       class="<?= isset($errors['time_in']) ? 'is-invalid' : '' ?>" required>
                <?php if (isset($errors['time_in'])): ?>
                    <small class="field-error"><?= Formatter::escape($errors['time_in']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="time_out">Time Out <span style="color:#dc2626">*</span></label>
                <input type="time" id="time_out" name="time_out"
                       value="<?= $v('time_out') ?>"
                       class="<?= isset($errors['time_out']) ? 'is-invalid' : '' ?>" required>
                <?php if (isset($errors['time_out'])): ?>
                    <small class="field-error"><?= Formatter::escape($errors['time_out']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Break Time -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
            <div class="form-group">
                <label for="break_minutes">Break Duration (min)</label>
                <input type="number" id="break_minutes" name="break_minutes"
                       value="<?= $v('break_minutes', '60') ?>"
                       min="0" max="480" step="5">
            </div>
            <div class="form-group">
                <label for="break_start">Break Start</label>
                <input type="time" id="break_start" name="break_start"
                       value="<?= $v('break_start_time') ?>">
            </div>
            <div class="form-group">
                <label for="break_end">Break End</label>
                <input type="time" id="break_end" name="break_end"
                       value="<?= $v('break_end_time') ?>">
            </div>
        </div>

        <!-- Grace Period / Overtime -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
                <label for="grace_minutes">Grace Period (min)</label>
                <input type="number" id="grace_minutes" name="grace_minutes"
                       value="<?= $v('grace_minutes', '0') ?>"
                       min="0" max="120" step="1">
                <?php if (isset($errors['grace_minutes'])): ?>
                    <small class="field-error"><?= Formatter::escape($errors['grace_minutes']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="overtime_allowed">Overtime Allowed</label>
                <select id="overtime_allowed" name="overtime_allowed">
                    <option value="1" <?= ((string) ($schedule['overtime_allowed'] ?? 1)) !== '0' ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ((string) ($schedule['overtime_allowed'] ?? 1)) === '0' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>

        <!-- Effective From / Status -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
                <label for="effective_from">Effective From</label>
                <input type="date" id="effective_from" name="effective_from"
                       value="<?= $v('effective_from') ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="Active"   <?= strtolower((string) ($schedule['status'] ?? 'active')) === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="Archived" <?= strtolower((string) ($schedule['status'] ?? ''))       === 'archived' ? 'selected' : '' ?>>Archived / Inactive</option>
                </select>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group">
            <label for="notes">Notes / Description <span class="muted">(optional)</span></label>
            <textarea id="notes" name="notes" rows="2"
                      style="width:100%;resize:vertical"><?= $v('notes') ?></textarea>
        </div>

        <?php if ($errors !== []): ?>
        <div class="alert error" role="alert">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= $base ?>/hr/schedules" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
