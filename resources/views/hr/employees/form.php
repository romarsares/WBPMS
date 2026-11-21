<?php


use Wbpms\Http\View\Formatter;

/** @var list<array{id:int,name:string}> $branches */
/** @var list<array{id:int,name:string}> $schedules */
/** @var list<array{id:int,name:string}> $devices */
/** @var array<string,string> $errors */
/** @var array|null $employee */
?>
<h1><?= $employee ? 'Edit Employee' : 'Add Employee' ?></h1>
<form method="post" action="<?= $employee ? $base . '/hr/employees/' . (int) $employee['id'] : $base . '/hr/employees' ?>">
    <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf ?? '') ?>">
    <?php if ($employee): ?>
    <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <fieldset style="margin-bottom:1rem">
        <legend>Basic Information</legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
            <label>Employee number <input required name="employee_number" value="<?= Formatter::escape($employee['employee_number'] ?? $suggestedEmpNumber ?? '') ?>"></label>
            <label>First name <input required name="first_name" value="<?= Formatter::escape($employee['first_name'] ?? '') ?>"></label>
            <label>Middle name <input name="middle_name" value="<?= Formatter::escape($employee['middle_name'] ?? '') ?>" maxlength="50"></label>
            <label>Last name <input required name="last_name" value="<?= Formatter::escape($employee['last_name'] ?? '') ?>"></label>
            <label>Email <input required type="email" name="email" value="<?= Formatter::escape($employee['email'] ?? '') ?>"></label>
            <label>Contact number <input required name="contact_number" value="<?= Formatter::escape($employee['contact_number'] ?? '') ?>" inputmode="tel" placeholder="09XXXXXXXXX"></label>
            <label>Birthdate <input required type="date" name="birthdate" value="<?= Formatter::escape($employee['birthdate'] ?? '') ?>"></label>
            <label>Hire date <input required type="date" name="effective_from" value="<?= Formatter::escape($employee['effective_from'] ?? $employee['hire_date'] ?? '') ?>"></label>
            <label>Hire time <input type="time" name="hire_time" value="<?= Formatter::escape($employee['hire_time'] ?? '') ?>"></label>
        </div>
    </fieldset>

    <fieldset style="margin-bottom:1rem">
        <legend>Employment Details</legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
            <label>Employee type
                <select name="employee_type" required>
                    <option value="Regular" <?= ($employee['employee_type'] ?? 'Regular') === 'Regular' ? 'selected' : '' ?>>Regular</option>
                    <option value="Contractual" <?= ($employee['employee_type'] ?? '') === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                </select>
            </label>
            <label>Position
                <select name="position" required>
                    <option value="">Select position</option>
                    <?php foreach ($positions as $pos): ?>
                    <option value="<?= Formatter::escape($pos['name']) ?>" <?= ($employee['position'] ?? '') === $pos['name'] ? 'selected' : '' ?>>
                        <?= Formatter::escape($pos['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Contract review date <input type="date" name="contract_review_date" value="<?= Formatter::escape($employee['contract_review_date'] ?? '') ?>"></label>
            <label>Status
                <select name="status" required>
                    <option value="active" <?= ($employee['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($employee['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </label>
        </div>
    </fieldset>

    <fieldset style="margin-bottom:1rem">
        <legend>Assignment</legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
            <label>Branch <select required name="branch_id"><?php foreach ($branches as $branch): ?><option value="<?= $branch['id'] ?>" <?= ($employee['branch_id'] ?? '') == $branch['id'] ? 'selected' : '' ?>><?= Formatter::escape($branch['name']) ?></option><?php endforeach; ?></select></label>
            <label>Schedule <select required name="schedule_id"><?php foreach ($schedules as $schedule): ?><option value="<?= $schedule['id'] ?>" <?= ($employee['schedule_id'] ?? '') == $schedule['id'] ? 'selected' : '' ?>><?= Formatter::escape($schedule['name']) ?></option><?php endforeach; ?></select></label>
        </div>
    </fieldset>

    <fieldset style="margin-bottom:1rem">
        <legend>Biometric Enrollment</legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
            <label>Biometric device <select required name="device_id"><?php foreach ($devices as $device): ?><option value="<?= $device['id'] ?>" <?= ($employee['device_id'] ?? '') == $device['id'] ? 'selected' : '' ?>><?= Formatter::escape($device['name']) ?></option><?php endforeach; ?></select></label>
            <label>Enroll ID <input required name="enrollment_code" inputmode="numeric" value="<?= Formatter::escape($employee['enrollment_code'] ?? '') ?>"></label>
        </div>
    </fieldset>

    <fieldset style="margin-bottom:1rem">
        <legend>Government IDs</legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
            <label>SSS number <input name="sss_number" value="<?= Formatter::escape($employee['sss_number'] ?? '') ?>" placeholder="XX-XXXXXXX-X"></label>
            <label>PhilHealth number <input name="philhealth_number" value="<?= Formatter::escape($employee['philhealth_number'] ?? '') ?>" placeholder="12 digits"></label>
            <label>Pag-IBIG number <input name="pagibig_number" value="<?= Formatter::escape($employee['pagibig_number'] ?? '') ?>" placeholder="12 digits"></label>
            <label>TIN <input name="tin_number" value="<?= Formatter::escape($employee['tin_number'] ?? '') ?>" placeholder="XXX-XXX-XXX or XXX-XXX-XXX-XXX"></label>
        </div>
    </fieldset>

    <fieldset style="margin-bottom:1rem">
        <legend>System Access</legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;align-items:end">
            <div>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
                    <input type="checkbox" name="grant_hr_access" value="1" <?= (!empty($employee['has_hr_access']) ? 'checked' : '') ?>>
                    <span>Grant HRHead access (dual Employee + HRHead role)</span>
                </label>
                <p style="margin:.25rem 0 0 1.5rem;font-size:.8rem;color:#6b7280">
                    When checked, this employee will have both Employee and HRHead roles. They can switch roles after login.
                </p>
            </div>
        </div>
    </fieldset>

    <?php if (!empty($errors)): ?><p role="alert" style="color:#dc2626;margin-bottom:.75rem">Please correct the highlighted fields.</p><?php endif; ?>

    <div style="display:flex;gap:.5rem">
        <button type="submit" class="btn btn-primary"><?= $employee ? 'Save Changes' : 'Create Employee' ?></button>
        <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">Cancel</a>
    </div>
</form>
