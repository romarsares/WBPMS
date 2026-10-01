<?php
use Wbpms\Http\View\Formatter;
/**
 * Shared create / edit form for user accounts.
 *
 * @var array<string,mixed>|null  $user       — populated for edit, null for create
 * @var array<string,mixed>       $old        — POST repopulation on validation failure
 * @var array[]                   $roles      — [role_id, role_name]
 * @var array[]                   $employees  — unlinked employees for the select
 * @var string[]                  $errors     — validation error messages
 * @var string                    $base
 * @var string                    $csrf
 */
$user       = $user ?? null;
$old        = $old  ?? [];
$errors     = $errors ?? [];
$roles      = $roles ?? [];
$employees  = $employees ?? [];
$isEdit     = isset($user) && !empty($user['user_id']);
$userId     = $isEdit ? (int) $user['user_id'] : 0;
$formAction = $isEdit ? $base . '/users/' . $userId : $base . '/users';
$pageTitle  = $isEdit ? 'Edit User' : 'Create User';

// Repopulate values: prefer $old (POST flash), then $user (existing record)
$val = static function (string $key) use ($old, $user): string {
    if (isset($old[$key]) && $old[$key] !== '') {
        return (string) $old[$key];
    }
    if (isset($user[$key])) {
        return (string) $user[$key];
    }
    return '';
};
?>
<div class="page-head">
    <div>
        <h1><?= $pageTitle ?></h1>
        <p><?= $isEdit ? 'Update account details and role assignment.' : 'Create a new system account.' ?></p>
    </div>
    <a class="btn-secondary" href="<?= $base ?>/users">← Back to Users</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert error" role="alert">
        <?php foreach ($errors as $err): ?>
            <div><?= Formatter::escape($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="panel" style="max-width:680px">
    <form method="POST" action="<?= Formatter::escape($formAction) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <!-- USERNAME -->
        <div class="form-group">
            <label for="username">Username <span class="req">*</span></label>
            <input id="username" name="username" type="text"
                   class="form-control"
                   value="<?= Formatter::escape($val('username')) ?>"
                   maxlength="50" autocomplete="username"
                   placeholder="e.g. jdelacruz">
        </div>

        <!-- EMAIL -->
        <div class="form-group">
            <label for="account_email">Account Email <span class="req">*</span></label>
            <input id="account_email" name="account_email" type="text"
                   class="form-control"
                   value="<?= Formatter::escape($val('account_email')) ?>"
                   maxlength="254" autocomplete="email"
                   placeholder="e.g. juan@lightdiamond.com">
        </div>

        <?php if (!$isEdit): ?>
        <!-- PASSWORD (create only) -->
        <div class="form-group">
            <label for="password">Password <span class="req">*</span></label>
            <input id="password" name="password" type="password"
                   class="form-control"
                   minlength="8" autocomplete="new-password"
                   placeholder="Minimum 8 characters">
        </div>
        <?php endif; ?>

        <!-- ROLE -->
        <?php if (($roleName ?? '') !== 'HRHead'): ?>
        <div class="form-group">
            <label for="role_id">Role <span class="req">*</span></label>
            <select id="role_id" name="role_id" class="form-control">
                <option value="">— Select role —</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int)$role['role_id'] ?>"
                        <?= (int)($val('role_id') ?: ($user['role_id'] ?? 0)) === (int)$role['role_id'] ? 'selected' : '' ?>>
                        <?= Formatter::escape($role['role_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="role_id" value="<?= (int)($val('role_id') ?: ($user['role_id'] ?? 0)) ?>">
        <?php endif; ?>

        <!-- LINKED EMPLOYEE -->
        <div class="form-group">
            <label for="employee_id">Linked Employee <span id="employeeRequired" class="req" hidden>*</span></label>
            <?php if (empty($employees) && !$isEdit): ?>
                <div class="alert info" role="alert" style="margin-top:4px;margin-bottom:0">
                    All active employees are already linked to a user account.
                    To link an employee here, first ensure they do not have an existing active account,
                    or archive their old account before creating a new one.
                </div>
                <input type="hidden" name="employee_id" value="">
            <?php else: ?>
            <select id="employee_id" name="employee_id" class="form-control">
                <option value="">— None (e.g. Business Owner) —</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int)$emp['employee_id'] ?>"
                        <?= (string)($val('employee_id') ?: ($user['employee_id'] ?? '')) === (string)$emp['employee_id'] ? 'selected' : '' ?>>
                        <?= Formatter::escape($emp['employee_name']) ?>
                        (<?= Formatter::escape($emp['employee_number']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <small class="help">Required for Employee accounts. Unlinking an existing Employee account deactivates it immediately.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <?= $isEdit ? '✓ Save Changes' : '＋ Create User' ?>
            </button>
            <a class="btn-secondary" href="<?= $base ?>/users">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var roleSelect = document.getElementById('role_id');
    var employeeSelect = document.getElementById('employee_id');
    var requiredMarker = document.getElementById('employeeRequired');
    var isEdit = <?= $isEdit ? 'true' : 'false' ?>;

    if (!roleSelect || !employeeSelect) return;

    function updateEmployeeRequirement() {
        var selected = roleSelect.options[roleSelect.selectedIndex];
        var isEmployee = selected && selected.text.trim() === 'Employee';
        if (requiredMarker) requiredMarker.hidden = !isEmployee;
    }

    roleSelect.addEventListener('change', updateEmployeeRequirement);
    updateEmployeeRequirement();
})();
</script>


