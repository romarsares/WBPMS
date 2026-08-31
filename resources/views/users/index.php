<?php declare(strict_types=1);
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
 */
?>
<div class="page-head">
    <div><h1>User Management</h1><p>System accounts and role assignments.</p></div>
    <a class="btn-primary" href="<?= $base ?>/users/create">＋ New User</a>
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

<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search users..." oninput="filterRows(this,'usersTable')"></div>
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
                        <a class="btn-secondary btn-sm"
                           href="<?= $base ?>/users/<?= (int)$r['user_id'] ?>/edit">Edit</a>

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

                        <!-- Archive -->
                        <form method="POST"
                              action="<?= $base ?>/users/<?= (int)$r['user_id'] ?>/archive"
                              style="display:inline"
                              onsubmit="return confirm('Archive this user? This cannot be undone.')">
                            <?= $csrfField ?>
                            <button type="submit" class="btn-danger btn-sm">Archive</button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:13px">Archived</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
