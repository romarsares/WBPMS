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
    <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">← Back to Employees</a>
</div>

<div class="card" style="max-width:720px">
    <form method="POST" action="<?= Formatter::escape($action) ?>" novalidate>
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
                    maxlength="50"
                    pattern="[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ .'\-]{0,48}[A-Za-zÀ-ÖØ-öø-ÿ.]|[A-Za-zÀ-ÖØ-öø-ÿ.]"
                    title="Letters, spaces, hyphens, apostrophes, and periods only."
                    class="<?= isset($errors['first_name']) ? 'is-invalid' : '' ?>">
                <?= $err('first_name') ?>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle name</label>
                <input type="text" id="middle_name" name="middle_name"
                    value="<?= $v('middle_name') ?? $v('middle_initial') ?>"
                    maxlength="50"
                    pattern="[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ .'\-]{0,48}[A-Za-zÀ-ÖØ-öø-ÿ.]|[A-Za-zÀ-ÖØ-öø-ÿ.]"
                    title="Letters, spaces, hyphens, apostrophes, and periods only."
                    class="<?= isset($errors['middle_name']) ? 'is-invalid' : '' ?>">
                <?= $err('middle_name') ?>
            </div>
            <div class="form-group">
                <label for="last_name">Last name <span style="color:#dc2626">*</span></label>
                <input type="text" id="last_name" name="last_name"
                    value="<?= $v('last_name') ?>"
                    maxlength="50"
                    pattern="[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ .'\-]{0,48}[A-Za-zÀ-ÖØ-öø-ÿ.]|[A-Za-zÀ-ÖØ-öø-ÿ.]"
                    title="Letters, spaces, hyphens, apostrophes, and periods only."
                    class="<?= isset($errors['last_name']) ? 'is-invalid' : '' ?>">
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
                    placeholder="09XXXXXXXXX"
                    maxlength="11"
                    pattern="09[0-9]{9}"
                    title="Philippine mobile number starting with 09, exactly 11 digits (e.g. 09171234567)."
                    inputmode="numeric"
                    class="<?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>">
                <?= $err('contact_number') ?>
            </div>
            <div class="form-group">
                <label for="email">Email <?= !$isEdit ? '<span style="color:#dc2626">*</span>' : '' ?></label>
                <input type="email" id="email" name="email"
                    value="<?= $v('email') ?>"
                    maxlength="254"
                    <?= !$isEdit ? 'required' : '' ?>
                    class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                    placeholder="e.g. juan@lightdiamond.com">
                <?= $err('email') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="position">Position / Job title <span style="color:#dc2626">*</span></label>
                <select id="position_select" name="position"
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
                <select id="employee_type" name="employee_type">
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
                other.name = 'position';
                sel.name = '_position_select';
            } else {
                other.style.display = 'none';
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
                    maxlength="30"
                    <?= !$isEdit ? 'required' : '' ?>
                    <?= $isEdit ? 'readonly style="background:#f3f4f6"' : '' ?>
                    class="<?= isset($errors['employee_number']) ? 'is-invalid' : '' ?>">
                <?= $err('employee_number') ?>
            </div>
            <div class="form-group">
                <label for="effective_from"><?= $isEdit ? 'Hire date' : 'Effective from' ?> <span style="color:#dc2626">*</span></label>
                <input type="date" id="effective_from" name="effective_from"
                    value="<?= $v('effective_from') ?>"
                    class="<?= isset($errors['effective_from']) ? 'is-invalid' : '' ?>"
                    <?= $isEdit ? 'readonly style="background:#f3f4f6"' : '' ?>>
                <?= $err('effective_from') ?>
            </div>
        </div>

        <h2>Government IDs</h2>
        <div class="form-row-3">
            <div class="form-group">
                <label for="sss_number">SSS number</label>
                <input type="text" id="sss_number" name="sss_number"
                    value="<?= $v('sss_number') ?>"
                    placeholder="XX-XXXXXXX-X"
                    maxlength="13"
                    pattern="\d{2}-?\d{7}-?\d"
                    title="SSS number must be in XX-XXXXXXX-X format (10 digits, e.g. 34-5678901-2)."
                    inputmode="numeric"
                    class="<?= isset($errors['sss_number']) ? 'is-invalid' : '' ?>">
                <?= $err('sss_number') ?>
            </div>
            <div class="form-group">
                <label for="philhealth_number">PhilHealth number</label>
                <input type="text" id="philhealth_number" name="philhealth_number"
                    value="<?= $v('philhealth_number') ?>"
                    placeholder="12-digit number"
                    maxlength="15"
                    pattern="[\d\- ]{12,15}"
                    title="PhilHealth number must be 12 digits."
                    inputmode="numeric"
                    class="<?= isset($errors['philhealth_number']) ? 'is-invalid' : '' ?>">
                <?= $err('philhealth_number') ?>
            </div>
            <div class="form-group">
                <label for="pagibig_number">Pag-IBIG number</label>
                <input type="text" id="pagibig_number" name="pagibig_number"
                    value="<?= $v('pagibig_number') ?>"
                    placeholder="12-digit number"
                    maxlength="15"
                    pattern="[\d\- ]{12,15}"
                    title="Pag-IBIG number must be 12 digits."
                    inputmode="numeric"
                    class="<?= isset($errors['pagibig_number']) ? 'is-invalid' : '' ?>">
                <?= $err('pagibig_number') ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="tin_number">TIN</label>
                <input type="text" id="tin_number" name="tin_number"
                    value="<?= $v('tin_number') ?>"
                    placeholder="XXX-XXX-XXX"
                    maxlength="16"
                    pattern="\d{3}-?\d{3}-?\d{3}(-?\d{3})?"
                    title="TIN must be 9 or 12 digits (e.g. 123-456-789 or 123-456-789-000)."
                    inputmode="numeric"
                    class="<?= isset($errors['tin_number']) ? 'is-invalid' : '' ?>">
                <?= $err('tin_number') ?>
            </div>
        </div>

        <h2>Assignment &amp; Salary</h2>
        <div class="form-row">
            <div class="form-group">
                <label for="branch_id">Branch <?= !$isEdit ? '<span style="color:#dc2626">*</span>' : '' ?></label>
                <?php
                // On edit: the select is visually disabled so HR cannot change it directly
                // (use Transfer Branch instead), but we still need the value to survive
                // a validation-error re-render. A hidden input carries the submitted value;
                // the visible select is purely decorative and uses `disabled` + `tabindex="-1"`.
                $currentBranchId = (string) ($employee['branch_id'] ?? ($_POST['branch_id'] ?? ''));
                ?>
                <?php if ($isEdit): ?>
                <input type="hidden" name="branch_id" value="<?= Formatter::escape($currentBranchId) ?>">
                <?php endif; ?>
                <select id="branch_id"
                    <?= !$isEdit ? 'name="branch_id"' : 'tabindex="-1" aria-hidden="true"' ?>
                    <?= $isEdit ? 'disabled style="background:#f3f4f6;color:#6b7280;cursor:not-allowed"' : '' ?>
                    class="<?= isset($errors['branch_id']) ? 'is-invalid' : '' ?>"
                    <?= !$isEdit ? 'required' : '' ?>>
                    <option value="">— select —</option>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>"
                        <?= $sel($branch['id'], $currentBranchId) ?>>
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
                    required
                    class="<?= isset($errors['schedule_id']) ? 'is-invalid' : '' ?>">
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
                    min="0.01" step="0.01"
                    required
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
                <label for="device_id">Biometric device <?= !$isEdit ? '<span style="color:#dc2626">*</span>' : '' ?></label>
                <select id="device_id" name="device_id"
                    <?= !$isEdit ? 'required' : '' ?>
                    class="<?= isset($errors['device_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— select —</option>
                    <?php foreach ($devices as $device): ?>
                    <option value="<?= (int) $device['id'] ?>"
                        <?= $sel($device['id'], $employee['device_id'] ?? ($_POST['device_id'] ?? '')) ?>>
                        <?= Formatter::escape($device['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('device_id') ?>
                <?php if ($isEdit): ?>
                <small class="muted">To re-enroll this employee on a device, use the biometric enrollment workflow.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="enrollment_code">Enroll ID <?= !$isEdit ? '<span style="color:#dc2626">*</span>' : '' ?></label>
                <input type="text" id="enrollment_code" name="enrollment_code"
                    value="<?= $v('enrollment_code') ?>"
                    inputmode="numeric"
                    <?= !$isEdit ? 'required' : '' ?>
                    class="<?= isset($errors['enrollment_code']) ? 'is-invalid' : '' ?>">
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
                <a href="<?= $base ?>/hr/employees/<?= (int) $employee['id'] ?>/bank"
                   class="btn btn-secondary">Bank Account</a>
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
