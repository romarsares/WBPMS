<?php


use Wbpms\Http\View\Formatter;

/**
 * HR — Edit employee form.
 * Shared with create (when $employee is empty/null).
 *
 * @var array{
 *     id: int,
 *     employee_number: string,
 *     first_name: string,
 *     last_name: string,
 *     middle_name: string,
 *     status: string,
 *     effective_from: string,
 *     branch_id: int,
 *     schedule_id: int,
 *     device_id: int,
 *     enrollment_code: string,
 *     daily_rate: string
 * }|null $employee
 * @var list<array{id:int,name:string}> $branches
 * @var list<array{id:int,name:string}> $schedules
 * @var list<array{id:int,name:string}> $devices
 * @var array<string,string>            $errors
 * @var string                          $csrf
 */

$employee ??= null;
$branches ??= [];
$schedules ??= [];
$devices  ??= [];
$positions ??= [];
$errors   ??= [];
$csrf     ??= '';

$isEdit  = $employee !== null;
$title   = $isEdit ? 'Edit Employee' : 'Add Employee';
$action  = $isEdit
    ? $base . '/hr/employees/' . (int) $employee['id']
    : $base . '/hr/employees';

// Populate with existing values (edit) or empty/posted defaults (create)
$v = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($employee[$key] ?? ($_POST[$key] ?? $fallback)));
$sel = static fn(int|string $id, int|string $cmp): string =>
    ((string) $id === (string) $cmp) ? 'selected' : '';
$err = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . Formatter::escape($errors[$key]) . '</span>' : '';
?>

<div class="page-header">
    <h1><?= $title ?></h1>
    <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">← Back to employees</a>
</div>

<div class="card" style="max-width:720px">
    <form method="POST" action="<?= Formatter::escape($action) ?>">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <h2 style="margin-top:0">Personal Information</h2>
        <div class="form-row-3">
            <div class="form-group">
                <label for="first_name">First name <span style="color:#dc2626">*</span></label>
                <input type="text" id="first_name" name="first_name"
                    value="<?= $v('first_name') ?>"
                    class="<?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" required>
                <?= $err('first_name') ?>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle name</label>
                <input type="text" id="middle_name" name="middle_name"
                    value="<?= $v('middle_name') ?? $v('middle_initial') ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last name <span style="color:#dc2626">*</span></label>
                <input type="text" id="last_name" name="last_name"
                    value="<?= $v('last_name') ?>"
                    class="<?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" required>
                <?= $err('last_name') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="birthdate">Birthdate</label>
                <input type="date" id="birthdate" name="birthdate"
                    value="<?= $v('birthdate') ?>">
            </div>
            <div class="form-group">
                <label for="contact_number">Contact number</label>
                <input type="text" id="contact_number" name="contact_number"
                    value="<?= $v('contact_number') ?>"
                    placeholder="09XXXXXXXXX">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                    value="<?= $v('email') ?>"
                    class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                    placeholder="optional">
                <?= $err('email') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="position">Position / Job title <span style="color:#dc2626">*</span></label>
                <select id="position_select" name="position" required
                        onchange="syncPositionInput(this)"
                        class="<?= isset($errors['position']) ? 'is-invalid' : '' ?>">
                    <option value="">— select position —</option>
                    <?php
                    $currentPosition = (string) ($employee['position'] ?? ($_POST['position'] ?? ''));
                    $matched = false;
                    foreach ($positions ?? [] as $pos):
                        $selected = ($pos['name'] === $currentPosition);
                        if ($selected) $matched = true;
                    ?>
                    <option value="<?= Formatter::escape($pos['name']) ?>"
                            <?= $selected ? 'selected' : '' ?>>
                        <?= Formatter::escape($pos['name']) ?>
                        <?php if ($pos['department']): ?>
                            (<?= Formatter::escape($pos['department']) ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                    <option value="__other__" <?= (!$matched && $currentPosition !== '') ? 'selected' : '' ?>>
                        Other (type below)
                    </option>
                </select>
                <input type="text" id="position_other" name="position_other"
                       value="<?= (!$matched && $currentPosition !== '') ? Formatter::escape($currentPosition) : '' ?>"
                       placeholder="Type custom position title"
                       style="margin-top:.4rem;<?= (!$matched && $currentPosition !== '') ? '' : 'display:none' ?>">
                <small class="muted">
                    Can't find the title?
                    <a href="<?= $base ?>/hr/settings/positions" target="_blank">Add it in Settings →</a>
                </small>
                <?= $err('position') ?>
            </div>
            <div class="form-group">
                <label for="employee_type">Employee type <span style="color:#dc2626">*</span></label>
                <select id="employee_type" name="employee_type" required>
                    <option value="Regular"      <?= $sel($employee['employee_type'] ?? 'Regular', 'Regular') ?>>Regular</option>
                    <option value="Contractual"  <?= $sel($employee['employee_type'] ?? '',        'Contractual') ?>>Contractual</option>
                </select>
            </div>
        </div>

        <script>
        function syncPositionInput(sel) {
            var other = document.getElementById('position_other');
            if (sel.value === '__other__') {
                other.style.display = '';
                other.required = true;
                other.name = 'position';
                sel.name = '_position_select';
            } else {
                other.style.display = 'none';
                other.required = false;
                other.name = 'position_other';
                sel.name = 'position';
            }
        }
        // Run on page load to handle the pre-selected "Other" state
        (function(){ syncPositionInput(document.getElementById('position_select')); })();
        </script>

        <div class="form-row">
            <div class="form-group">
                <label for="employee_number">Employee number <span style="color:#dc2626">*</span></label>
                <input type="text" id="employee_number" name="employee_number"
                    value="<?= $v('employee_number') ?>"
                    <?= $isEdit ? 'readonly style="background:#f3f4f6"' : 'required' ?>
                    class="<?= isset($errors['employee_number']) ? 'is-invalid' : '' ?>">
                <?= $err('employee_number') ?>
            </div>
            <div class="form-group">
                <label for="effective_from"><?= $isEdit ? 'Hire date' : 'Effective from' ?> <span style="color:#dc2626">*</span></label>
                <input type="date" id="effective_from" name="effective_from"
                    value="<?= $v('effective_from') ?>"
                    class="<?= isset($errors['effective_from']) ? 'is-invalid' : '' ?>"
                    <?= $isEdit ? 'readonly style="background:#f3f4f6"' : 'required' ?>>
                <?= $err('effective_from') ?>
            </div>
        </div>

        <h2>Government IDs</h2>
        <div class="form-row-3">
            <div class="form-group">
                <label for="philhealth_number">PhilHealth number</label>
                <input type="text" id="philhealth_number" name="philhealth_number"
                    value="<?= $v('philhealth_number') ?>" placeholder="optional">
            </div>
            <div class="form-group">
                <label for="pagibig_number">Pag-IBIG number</label>
                <input type="text" id="pagibig_number" name="pagibig_number"
                    value="<?= $v('pagibig_number') ?>" placeholder="optional">
            </div>
            <div class="form-group">
                <label for="tin_number">TIN</label>
                <input type="text" id="tin_number" name="tin_number"
                    value="<?= $v('tin_number') ?>" placeholder="optional">
            </div>
        </div>

        <h2>Assignment &amp; Salary</h2>
        <div class="form-row">
            <div class="form-group">
                <label for="branch_id">Branch <span style="color:#dc2626">*</span></label>
                <select id="branch_id" name="branch_id"
                    class="<?= isset($errors['branch_id']) ? 'is-invalid' : '' ?>"
                    <?= $isEdit ? 'disabled' : 'required' ?>>
                    <option value="">— select —</option>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>"
                        <?= $sel($branch['id'], $employee['branch_id'] ?? ($_POST['branch_id'] ?? '')) ?>>
                        <?= Formatter::escape($branch['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('branch_id') ?>
                <?php if ($isEdit): ?>
                <small class="muted">To change branches, use <strong>Transfer Branch</strong> below so the effective date and history are recorded safely.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="schedule_id">Work schedule <span style="color:#dc2626">*</span></label>
                <select id="schedule_id" name="schedule_id"
                    class="<?= isset($errors['schedule_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="">— select —</option>
                    <?php foreach ($schedules as $schedule): ?>
                    <option value="<?= (int) $schedule['id'] ?>"
                        <?= $sel($schedule['id'], $employee['schedule_id'] ?? ($_POST['schedule_id'] ?? '')) ?>>
                        <?= Formatter::escape($schedule['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('schedule_id') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="daily_rate">Daily rate (₱) <span style="color:#dc2626">*</span></label>
                <input type="number" id="daily_rate" name="daily_rate"
                    value="<?= $v('daily_rate') ?>"
                    min="0" step="0.01" required
                    class="<?= isset($errors['daily_rate']) ? 'is-invalid' : '' ?>">
                <?= $err('daily_rate') ?>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php
                    $currentStatus = strtolower((string) ($employee['status'] ?? 'active'));
                    ?>
                    <option value="active"   <?= $currentStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <h2>Biometric Enrollment</h2>
        <div class="form-row">
            <div class="form-group">
                <label for="device_id">Biometric device <span style="color:#dc2626">*</span></label>
                <select id="device_id" name="device_id"
                    class="<?= isset($errors['device_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="">— select —</option>
                    <?php foreach ($devices as $device): ?>
                    <option value="<?= (int) $device['id'] ?>"
                        <?= $sel($device['id'], $employee['device_id'] ?? ($_POST['device_id'] ?? '')) ?>>
                        <?= Formatter::escape($device['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('device_id') ?>
            </div>
            <div class="form-group">
                <label for="enrollment_code">Enroll ID <span style="color:#dc2626">*</span></label>
                <input type="text" id="enrollment_code" name="enrollment_code"
                    value="<?= $v('enrollment_code') ?>"
                    inputmode="numeric"
                    class="<?= isset($errors['enrollment_code']) ? 'is-invalid' : '' ?>" required>
                <?= $err('enrollment_code') ?>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error" role="alert">
            Please correct the highlighted fields before saving.
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Save Changes' : 'Create Employee' ?>
            </button>
            <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">Cancel</a>
            <?php if ($isEdit): ?>
                <a href="<?= $base ?>/hr/employees/<?= (int) $employee['id'] ?>/documents"
                   class="btn btn-secondary">Documents</a>
                <a href="<?= $base ?>/hr/employees/<?= (int) $employee['id'] ?>/transfer"
                   class="btn btn-warning" style="margin-left:auto;">
                    Transfer Branch
                </a>
                <?php if (strtolower((string) $employee['status']) !== 'archived'): ?>
                <a href="<?= $base ?>/hr/employees/<?= (int) $employee['id'] ?>/archive"
                   class="btn btn-secondary">Archive</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
</div>
