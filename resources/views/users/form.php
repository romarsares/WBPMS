<?php
use Wbpms\Http\View\Formatter;
/**
 * Shared create / edit form for user accounts.
 *
 * @var array<string,mixed>|null  $user       — populated for edit, null for create
 * @var array<string,mixed>       $old        — POST repopulation on validation failure
 * @var array[]                   $roles      — [role_id, role_name]
 * @var string[]                  $errors     — validation error messages
 * @var string                    $base
 * @var string                    $csrf
 */
$user       = $user ?? null;
$old        = $old  ?? [];
$errors     = $errors ?? [];
$roles      = $roles ?? [];
$isEdit     = isset($user) && !empty($user['user_id']);
$userId     = $isEdit ? (int) $user['user_id'] : 0;
$formAction = $isEdit ? $base . '/users/' . $userId : $base . '/users';
$pageTitle  = $isEdit ? 'Edit User' : 'Create User';
$isEmployeeAccount = $isEdit && (($user['role_name'] ?? '') === 'Employee');

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
    <a class="btn btn-secondary" href="<?= $base ?>/users">← Back to Users</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert error" role="alert">
        <?php foreach ($errors as $err): ?>
            <div><?= Formatter::escape($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="panel" style="max-width:680px">
    <form method="POST" action="<?= Formatter::escape($formAction) ?>" novalidate data-validate>
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
                   required
                   data-msg-required="Username is required."
                   placeholder="e.g. jdelacruz">
        </div>

        <!-- EMAIL -->
        <div class="form-group">
            <label for="account_email">Account Email <span class="req">*</span></label>
            <input id="account_email" name="account_email" type="email"
                   class="form-control"
                   value="<?= Formatter::escape($val('account_email')) ?>"
                   maxlength="254" autocomplete="email"
                   required
                   data-msg-required="Account email is required."
                   data-msg-type="Please enter a valid email address."
                   placeholder="e.g. juan@lightdiamond.com">
        </div>

        <?php if (!$isEdit): ?>
        <!-- PASSWORD (create only) -->
        <div class="form-group">
            <label for="password">Password <span class="req">*</span></label>
            <input id="password" name="password" type="password"
                   class="form-control"
                   minlength="8" autocomplete="new-password"
                   required
                   data-msg-required="Password is required."
                   data-msg-min="Password must be at least 8 characters."
                   placeholder="Minimum 8 characters">
        </div>
        <?php endif; ?>

        <!-- ROLE -->
        <?php if ($isEmployeeAccount): ?>
        <div class="form-group">
            <label>Role</label>
            <input type="text" class="form-control" value="Employee" disabled>
            <input type="hidden" name="role_id" value="<?= (int) $user['role_id'] ?>">
            <small class="help">Employee accounts are created and linked automatically from Employee Management.</small>
        </div>
        <?php elseif (($roleName ?? '') !== 'HRHead'): ?>
        <div class="form-group">
            <label for="role_id">Role <span class="req">*</span></label>
            <select id="role_id" name="role_id" class="form-control"
                    required data-msg-required="Role is required.">
                <option value="">— Select role —</option>
                <?php foreach ($roles as $role): ?>
                    <?php if ($role['role_name'] === 'Employee') continue; ?>
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

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? '✓ Save Changes' : '＋ Create User' ?>
            </button>
            <a class="btn btn-secondary" href="<?= $base ?>/users">Cancel</a>
        </div>
    </form>
</div>

<script src="<?= $base ?>/assets/inline-validate.js"></script>
