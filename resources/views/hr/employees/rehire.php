<?php

use Wbpms\Http\View\Formatter;

/** @var array<string,mixed> $employee */
/** @var list<array{id:int,name:string}> $branches */
/** @var list<array{id:int,name:string}> $schedules */
/** @var list<array{id:int,name:string}> $devices */
/** @var list<array{id:int,name:string}> $positions */
/** @var array<string,string> $errors */
$errors ??= [];
$value = static fn(string $key, string $fallback = ''): string => Formatter::escape((string) ($_POST[$key] ?? $fallback));
$fieldError = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . Formatter::escape($errors[$key]) . '</span>' : '';
?>

<div class="page-header">
    <h1>Rehire Employee</h1>
    <a href="<?= $base ?>/hr/employees?status=archived" class="btn btn-secondary">&larr; Archived employees</a>
</div>

<div class="card" style="max-width:720px">
    <p><strong><?= Formatter::escape($employee['last_name']) ?>, <?= Formatter::escape($employee['first_name']) ?></strong>
        <span class="muted">(<?= Formatter::escape($employee['employee_number']) ?>)</span></p>
    <p class="muted">This creates a new employment episode using the same employee record. Historical attendance and payroll are retained unchanged.</p>
    <?php if (isset($errors['form'])): ?>
    <div class="flash flash-error" role="alert"><?= Formatter::escape($errors['form']) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/hr/employees/<?= (int) $employee['id'] ?>/rehire">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="rehire_date">Rehire effective date <span style="color:#dc2626">*</span></label>
                <input type="date" id="rehire_date" name="rehire_date" value="<?= $value('rehire_date') ?>" required class="<?= isset($errors['rehire_date']) ? 'is-invalid' : '' ?>">
                <?= $fieldError('rehire_date') ?>
            </div>
            <div class="form-group">
                <label for="employee_type">Employee type <span style="color:#dc2626">*</span></label>
                <select id="employee_type" name="employee_type" required>
                    <?php $employeeType = $_POST['employee_type'] ?? $employee['employee_type']; ?>
                    <option value="Regular" <?= $employeeType === 'Regular' ? 'selected' : '' ?>>Regular</option>
                    <option value="Contractual" <?= $employeeType === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                </select>
                <?= $fieldError('employee_type') ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="position">Position <span style="color:#dc2626">*</span></label>
                <select id="position" name="position" required class="<?= isset($errors['position']) ? 'is-invalid' : '' ?>">
                    <?php $currentPosition = (string) ($_POST['position'] ?? $employee['position']); ?>
                    <?php foreach ($positions as $position): ?>
                    <option value="<?= Formatter::escape($position['name']) ?>" <?= $position['name'] === $currentPosition ? 'selected' : '' ?>><?= Formatter::escape($position['name']) ?></option>
                    <?php endforeach; ?>
                    <?php if ($currentPosition !== '' && !in_array($currentPosition, array_column($positions, 'name'), true)): ?>
                    <option value="<?= Formatter::escape($currentPosition) ?>" selected><?= Formatter::escape($currentPosition) ?></option>
                    <?php endif; ?>
                </select>
                <?= $fieldError('position') ?>
            </div>
            <div class="form-group">
                <label for="daily_rate">Daily rate (&#8369;) <span style="color:#dc2626">*</span></label>
                <input type="number" id="daily_rate" name="daily_rate" min="0.01" step="0.01" required value="<?= $value('daily_rate', (string) $employee['daily_rate']) ?>" class="<?= isset($errors['daily_rate']) ? 'is-invalid' : '' ?>">
                <?= $fieldError('daily_rate') ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="branch_id">Branch <span style="color:#dc2626">*</span></label>
                <select id="branch_id" name="branch_id" required class="<?= isset($errors['branch_id']) ? 'is-invalid' : '' ?>">
                    <option value="">&mdash; select &mdash;</option>
                    <?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (string) $branch['id'] === ($_POST['branch_id'] ?? '') ? 'selected' : '' ?>><?= Formatter::escape($branch['name']) ?></option><?php endforeach; ?>
                </select>
                <?= $fieldError('branch_id') ?>
            </div>
            <div class="form-group">
                <label for="schedule_id">Work schedule <span style="color:#dc2626">*</span></label>
                <select id="schedule_id" name="schedule_id" required class="<?= isset($errors['schedule_id']) ? 'is-invalid' : '' ?>">
                    <option value="">&mdash; select &mdash;</option>
                    <?php foreach ($schedules as $schedule): ?><option value="<?= (int) $schedule['id'] ?>" <?= (string) $schedule['id'] === ($_POST['schedule_id'] ?? '') ? 'selected' : '' ?>><?= Formatter::escape($schedule['name']) ?></option><?php endforeach; ?>
                </select>
                <?= $fieldError('schedule_id') ?>
            </div>
        </div>
        <h2>Biometric Enrollment</h2>
        <div class="form-row">
            <div class="form-group">
                <label for="device_id">Device <span style="color:#dc2626">*</span></label>
                <select id="device_id" name="device_id" required class="<?= isset($errors['device_id']) ? 'is-invalid' : '' ?>">
                    <option value="">&mdash; select &mdash;</option>
                    <?php foreach ($devices as $device): ?><option value="<?= (int) $device['id'] ?>" <?= (string) $device['id'] === ($_POST['device_id'] ?? '') ? 'selected' : '' ?>><?= Formatter::escape($device['name']) ?></option><?php endforeach; ?>
                </select>
                <?= $fieldError('device_id') ?>
            </div>
            <div class="form-group">
                <label for="enrollment_code">Enroll ID <span style="color:#dc2626">*</span></label>
                <input type="text" id="enrollment_code" name="enrollment_code" required value="<?= $value('enrollment_code') ?>" class="<?= isset($errors['enrollment_code']) ? 'is-invalid' : '' ?>">
                <?= $fieldError('enrollment_code') ?>
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="reactivate_account" value="1" <?= isset($_POST['reactivate_account']) || $_POST === [] ? 'checked' : '' ?>> Reactivate the linked employee login, if it is currently inactive</label>
        </div>
        <div class="form-group">
            <label for="notes">HR note</label>
            <textarea id="notes" name="notes" rows="3" maxlength="1000"><?= $value('notes') ?></textarea>
            <?= $fieldError('notes') ?>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">Rehire Employee</button>
            <a href="<?= $base ?>/hr/employees?status=archived" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
