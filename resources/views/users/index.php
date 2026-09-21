<?php
use Wbpms\Http\View\Formatter;
/**
 * @var array[]  $rows
 * @var int      $total
 * @var int      $active
 * @var int      $inactive
 * @var int      $archived
 * @var string   $base
 * @var string   $csrfField
 * @var array[]  $flash
 * @var array[]  $positions       Active job positions for filter dropdown
 * @var string   $filterPosition  Current position filter value
 * @var string   $filterUsername  Current username filter value
 * @var string   $filterStatus    Current status filter value
 */
$positions      ??= [];
$filterPosition ??= '';
$filterUsername ??= '';
$filterStatus   ??= '';
?>
<div class="page-head">
    <div><h1>User Management</h1><p>System accounts and role assignments.</p></div>
    <?php if ($roleName === 'BusinessOwner'): ?>
    <a class="btn btn-primary" href="<?= $base ?>/users/create">＋ New User</a>
    <?php endif; ?>
</div>

<?php foreach ($flash as [$type, $msg]): ?>
    <div class="alert <?= Formatter::escape($type) ?>" role="alert"><?= Formatter::escape($msg) ?></div>
<?php endforeach; ?>

<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Users</span></div>
    <div class="stat"><strong style="color:var(--ok)"><?= $active ?></strong><span>Active</span></div>
    <div class="stat"><strong style="color:var(--wait)"><?= $inactive ?></strong><span>Inactive</span></div>
    <div class="stat"><strong style="color:var(--bad)"><?= $archived ?></strong><span>Archived</span></div>
</div>

<!-- Filters -->
<div class="card" style="padding:.9rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" action="<?= $base ?>/users" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Username</label>
            <input
                type="text"
                name="username"
                value="<?= Formatter::escape($filterUsername) ?>"
                placeholder="Search username..."
                style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;width:180px"
            >
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Status</label>
            <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All</option>
                <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.25rem">Job Position</label>
            <select name="position" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="">All positions</option>
                <?php foreach ($positions as $pos): ?>
                <option value="<?= Formatter::escape($pos['name']) ?>"
                    <?= ($pos['name'] === $filterPosition) ? 'selected' : '' ?>>
                    <?= Formatter::escape($pos['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filterUsername !== '' || $filterStatus !== '' || $filterPosition !== ''): ?>
        <a href="<?= $base ?>/users" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="panel table-wrap">
    <table id="usersTable">
        <thead>
            <tr>
                <th>USERNAME</th><th>EMAIL</th><th>ROLE</th>
                <th>LINKED EMPLOYEE</th><th>STATUS</th><th>CREATED</th><th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">No users found.</td></tr>
        <?php else: foreach ($rows as $r):
            $badge       = match($r['status']) { 'Active' => 'ok', 'Inactive' => 'off', default => 'bad' };
            $isArchived  = $r['status'] === 'Archived';
            $toggleLabel = $r['status'] === 'Active' ? 'Deactivate' : 'Activate';
            $toggleClass = $r['status'] === 'Active' ? 'btn-warn'   : 'btn-secondary';
        ?>
            <tr>
                <td style="font-weight:700"><?= Formatter::escape($r['username']) ?></td>
                <td><?= Formatter::escape($r['account_email']) ?></td>
                <td><?= Formatter::escape($r['role_name']) ?></td>
                <td class="muted"><?= Formatter::escape($r['employee_name'] ?? '—') ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
                <td><?= Formatter::date($r['created_at']) ?></td>
                <td>
                    <?php if (!$isArchived): ?>
                        <?php if ($roleName === 'BusinessOwner' || ($roleName === 'HRHead' && $r['role_name'] === 'Employee')): ?>
                        <a class="btn-secondary btn-sm"
                           href="<?= $base ?>/users/<?= (int)$r['user_id'] ?>/edit">Edit</a>
                        <?php endif; ?>

                        <?php if ($roleName === 'BusinessOwner'): ?>
                        <!-- Toggle Active / Inactive -->
                        <form method="POST"
                              action="<?= $base ?>/users/<?= (int)$r['user_id'] ?>/toggle"
                              style="display:inline"
                              onsubmit="return confirm('<?= $r['status'] === 'Active' ? 'Deactivate' : 'Activate' ?> this user?')">
                            <?= $csrfField ?>
                            <button type="submit" class="<?= $toggleClass ?> btn-sm">
                                <?= $toggleLabel ?>
                            </button>
                        </form>

                        <!-- Archive: self-archiving is prohibited to prevent lockout. -->
                        <?php if ((int) $r['user_id'] !== $currentUserId): ?>
                        <form method="POST"
                              action="<?= $base ?>/users/<?= (int)$r['user_id'] ?>/archive"
                              style="display:inline"
                              onsubmit="return confirm('Archive this user? This cannot be undone.')">
                            <?= $csrfField ?>
                            <button type="submit" class="btn-danger btn-sm">Archive</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Reset Password — HRHead (Employee accounts only) + BusinessOwner (any account) -->
                        <?php if ($roleName === 'BusinessOwner' || ($roleName === 'HRHead' && $r['role_name'] === 'Employee')): ?>
                        <form method="POST"
                              action="<?= $base ?>/users/<?= (int)$r['user_id'] ?>/reset-password"
                              style="display:inline"
                              onsubmit="return confirm('Reset password for <?= Formatter::escape($r['username']) ?>? A new temporary password will be generated.')">
                            <?= $csrfField ?>
                            <button type="submit" class="btn-secondary btn-sm">Reset Password</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="muted" style="font-size:13px">Archived</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
