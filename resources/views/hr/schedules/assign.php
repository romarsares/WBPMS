<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/schedules/assign  (GET /hr/schedules/assign)
 *
 * Variables:
 *   list<array{id:int,name:string,employee_number:string}> $employees
 *   list<array{id:int,name:string}>                        $schedules
 *   array  $errors
 *   array  $input   — repopulate on validation failure
 */

$errors = $errors ?? [];
$input  = $input  ?? [];

$v = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($input[$key] ?? $fallback));
?>

<div class="page-head">
    <div>
        <h1>Assign Schedule to Employee</h1>
        <p><a href="<?= $base ?>/hr/schedules">← Back to Work Schedules</a></p>
    </div>
</div>

<div class="card" style="max-width:520px">
    <p style="color:#6b7280;margin-top:0">
        Select an employee and a reusable schedule template. If the employee already
        has an active assignment, it will be automatically closed on the day before
        the new effective date.
    </p>

    <form method="POST" action="<?= $base ?>/hr/schedules/assign">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <!-- Employee -->
        <div class="form-group">
            <label for="employee_id">Employee <span style="color:#dc2626">*</span></label>
            <select id="employee_id" name="employee_id"
                    class="<?= isset($errors['employee_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">— select employee —</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>"
                    <?= ((string) $emp['id'] === $v('employee_id')) ? 'selected' : '' ?>>
                    <?= Formatter::escape($emp['name']) ?>
                    (<?= Formatter::escape($emp['employee_number']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['employee_id'])): ?>
                <small class="field-error"><?= Formatter::escape($errors['employee_id']) ?></small>
            <?php endif; ?>
        </div>

        <!-- Schedule -->
        <div class="form-group">
            <label for="schedule_id">Work Schedule <span style="color:#dc2626">*</span></label>
            <select id="schedule_id" name="schedule_id"
                    class="<?= isset($errors['schedule_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">— select schedule —</option>
                <?php foreach ($schedules as $sched): ?>
                <option value="<?= (int) $sched['id'] ?>"
                    <?= ((string) $sched['id'] === $v('schedule_id')) ? 'selected' : '' ?>>
                    <?= Formatter::escape($sched['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['schedule_id'])): ?>
                <small class="field-error"><?= Formatter::escape($errors['schedule_id']) ?></small>
            <?php endif; ?>
        </div>

        <!-- Effective From -->
        <div class="form-group">
            <label for="effective_from">Effective From <span style="color:#dc2626">*</span></label>
            <input type="date" id="effective_from" name="effective_from"
                   value="<?= $v('effective_from', date('Y-m-d')) ?>"
                   class="<?= isset($errors['effective_from']) ? 'is-invalid' : '' ?>" required>
            <?php if (isset($errors['effective_from'])): ?>
                <small class="field-error"><?= Formatter::escape($errors['effective_from']) ?></small>
            <?php endif; ?>
        </div>

        <!-- Notes (optional) -->
        <div class="form-group">
            <label for="notes">Notes <span class="muted">(optional)</span></label>
            <input type="text" id="notes" name="notes"
                   value="<?= $v('notes') ?>"
                   placeholder="e.g. Transferred to night shift">
        </div>

        <?php if ($errors !== []): ?>
        <div class="alert error" role="alert">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button type="submit" class="btn btn-primary">Assign Schedule</button>
            <a href="<?= $base ?>/hr/schedules" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
