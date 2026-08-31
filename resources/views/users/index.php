<?php declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/** @var array[] $rows @var int $total @var int $active @var int $inactive @var int $archived @var string $base */
?>
<div class="page-head">
    <div><h1>User Management</h1><p>System accounts and role assignments.</p></div>
</div>
<div class="cards four">
    <div class="stat"><strong><?= $total ?></strong><span>Total Users</span></div>
    <div class="stat"><strong><?= $active ?></strong><span>Active</span></div>
    <div class="stat"><strong><?= $inactive ?></strong><span>Inactive</span></div>
    <div class="stat"><strong><?= $archived ?></strong><span>Archived</span></div>
</div>
<div class="filterbar">
    <div class="local-search-wrap">⌕<input placeholder="Search users..." oninput="filterRows(this,'usersTable')"></div>
</div>
<div class="panel table-wrap">
    <table id="usersTable">
        <thead><tr><th>USERNAME</th><th>EMAIL</th><th>ROLE</th><th>STATUS</th><th>CREATED</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:28px">No users found.</td></tr>
        <?php else: foreach ($rows as $r):
            $badge = match($r['status']) { 'Active' => 'ok', 'Inactive' => 'off', default => 'bad' }; ?>
            <tr>
                <td><?= Formatter::escape($r['username']) ?></td>
                <td><?= Formatter::escape($r['account_email']) ?></td>
                <td><?= Formatter::escape($r['role_name']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
                <td><?= Formatter::date($r['created_at']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
