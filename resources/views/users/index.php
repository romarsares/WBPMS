<?php
use Wbpms\Http\View\Formatter;
/**
 * @var array[]  $rows
 * @var int      $total
 * @var int      $active
 * @var int      $inactive
 * @var int      $archived
 * @var string   $base
 * @var string   $csrf
 * @var array[]  $positions       Active job positions for filter dropdown
 * @var string   $filterPosition  Current position filter value
 * @var string   $filterUsername  Current username filter value
 * @var string   $filterStatus    Current status filter value
 *
 * Flash messages are handled by layout.php via the ViewRenderer toast system.
 */
$positions      ??= [];
$filterPosition ??= '';
$filterUsername ??= '';
$filterStatus   ??= '';
$roleName       ??= '';
?>
<div class="page-head">
    <div><h1>User Management</h1><p>System accounts are created automatically when employees are added.</p></div>
</div>

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
            $badge      = match($r['status']) { 'Active' => 'ok', 'Inactive' => 'off', default => 'bad' };
            $isArchived = $r['status'] === 'Archived';
            $uid        = (int) $r['user_id'];
            $rawUsername = (string) ($r['username'] ?? '');
            // $uname is safe for use in data-* attribute values (no HTML tags)
            $uname      = $rawUsername !== '' ? Formatter::escape($rawUsername) : '(no username)';
            // $unameDisplay is used in the table cell and may include styling
            $unameDisplay = $rawUsername !== '' ? Formatter::escape($rawUsername) : '<span class="muted" style="font-style:italic">(no username)</span>';
        ?>
            <tr>
                <td style="font-weight:700"><?= $unameDisplay ?></td>
                <td><?= $r['account_email'] !== '' && $r['account_email'] !== null ? Formatter::escape($r['account_email']) : '<span class="muted">—</span>' ?></td>
                <td><?= Formatter::escape($r['role_name'] ?? '—') ?></td>
                <td class="muted"><?= Formatter::escape($r['employee_name'] ?? '—') ?></td>
                <td><span class="badge <?= $badge ?>"><?= Formatter::escape($r['status']) ?></span></td>
                <td><?= Formatter::date($r['created_at']) ?></td>
                <td>
                    <?php if ($isArchived): ?>
                        <div class="action-menu">
                            <button type="button" class="action-menu-toggle" aria-haspopup="true" aria-expanded="false">
                                Actions <span class="chevron">▾</span>
                            </button>
                            <div class="action-menu-list" role="menu">
                                <!-- Unarchive -->
                                <form id="form-unarchive-<?= $uid ?>" method="POST" action="<?= $base ?>/users/<?= $uid ?>/unarchive">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button" class="item-ok" role="menuitem"
                                            data-confirm
                                            data-form="form-unarchive-<?= $uid ?>"
                                            data-icon="↩"
                                            data-title="Unarchive user"
                                            data-body="Unarchive &lt;strong&gt;<?= $uname ?>&lt;/strong&gt;? The account will be set to Inactive. You can activate it afterwards.">
                                        ↩ Unarchive
                                    </button>
                                </form>
                            </div>
                        </div>

                    <?php else: ?>
                    <?php
                        $canEdit    = true;
                        $canAdmin   = true;
                        $hasActions = true;
                    ?>
                    <?php if ($hasActions): ?>
                    <div class="action-menu">
                        <button type="button" class="action-menu-toggle" aria-haspopup="true" aria-expanded="false">
                            Actions <span class="chevron">▾</span>
                        </button>
                        <div class="action-menu-list" role="menu">

                            <?php if ($canEdit): ?>
                            <a href="<?= $base ?>/users/<?= $uid ?>/edit" role="menuitem">✏ Edit</a>
                            <?php endif; ?>

                            <?php if ($canAdmin): ?>
                            <div class="menu-divider"></div>

                            <!-- Toggle Active / Inactive -->
                            <?php $isActive = $r['status'] === 'Active'; ?>
                            <form id="form-toggle-<?= $uid ?>" method="POST" action="<?= $base ?>/users/<?= $uid ?>/toggle">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="button"
                                        class="<?= $isActive ? 'item-warn' : 'item-ok' ?>"
                                        role="menuitem"
                                        data-confirm
                                        data-form="form-toggle-<?= $uid ?>"
                                        data-icon="<?= $isActive ? '⏸' : '▶' ?>"
                                        data-title="<?= $isActive ? 'Deactivate user' : 'Activate user' ?>"
                                        data-body="<?= $isActive
                                            ? "Deactivate &lt;strong&gt;{$uname}&lt;/strong&gt;? They will not be able to log in until reactivated."
                                            : "Activate &lt;strong&gt;{$uname}&lt;/strong&gt;? They will regain access immediately." ?>">
                                    <?= $isActive ? '⏸ Deactivate' : '▶ Activate' ?>
                                </button>
                            </form>

                            <?php if ($uid !== $currentUserId): ?>
                            <form id="form-archive-<?= $uid ?>" method="POST" action="<?= $base ?>/users/<?= $uid ?>/archive">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="button"
                                        class="item-danger"
                                        role="menuitem"
                                        data-confirm
                                        data-form="form-archive-<?= $uid ?>"
                                        data-icon="🗄"
                                        data-title="Archive user"
                                        data-body="Archive &lt;strong&gt;<?= $uname ?>&lt;/strong&gt;? This will also archive their linked employee record. The account can be restored later by the Business Owner.">
                                    🗄 Archive
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php endif; /* canAdmin */ ?>

                            <?php if ($canEdit): ?>
                            <?php if ($canAdmin): ?><div class="menu-divider"></div><?php endif; ?>
                            <!-- Reset Password -->
                            <form id="form-reset-<?= $uid ?>" method="POST" action="<?= $base ?>/users/<?= $uid ?>/reset-password">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="button"
                                        role="menuitem"
                                        data-confirm
                                        data-form="form-reset-<?= $uid ?>"
                                        data-icon="🔑"
                                        data-title="Reset password"
                                        data-body="Generate a new temporary password for &lt;strong&gt;<?= $uname ?>&lt;/strong&gt;? The user will be required to change it on next login.">
                                    🔑 Reset Password
                                </button>
                            </form>
                            <?php endif; /* canEdit */ ?>

                        </div>
                    </div>
                    <?php else: ?>
                    <span class="muted" style="font-size:13px">—</span>
                    <?php endif; /* hasActions */ ?>
                    <?php endif; /* isArchived */ ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- ================================================================
     CONFIRM MODAL  (single shared instance — JS populates it)
     ================================================================ -->
<div id="confirmModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="modal-box">
        <span id="confirmModalIcon" class="modal-icon" aria-hidden="true"></span>
        <p id="confirmModalTitle" class="modal-title"></p>
        <p id="confirmModalBody"  class="modal-body"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="confirmModalCancel">Cancel</button>
            <button type="button" class="btn btn-primary"   id="confirmModalOk">Confirm</button>
        </div>
    </div>
</div>

<script>
(function () {
    /* ---- Action-menu toggle ---- */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.action-menu-toggle');
        if (toggle) {
            var menu   = toggle.closest('.action-menu');
            var isOpen = menu.classList.contains('open');
            closeAllMenus();
            if (!isOpen) {
                menu.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
                positionMenuList(toggle, menu.querySelector('.action-menu-list'));
            }
            e.stopPropagation();
            return;
        }
        // Click outside closes all menus (but not modal clicks)
        if (!e.target.closest('.modal-box')) {
            closeAllMenus();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllMenus();
            closeModal();
        }
    });

    function closeAllMenus() {
        document.querySelectorAll('.action-menu.open').forEach(function (m) {
            m.classList.remove('open');
            m.querySelector('.action-menu-toggle').setAttribute('aria-expanded', 'false');
            var list = m.querySelector('.action-menu-list');
            if (list) { list.style.top = ''; list.style.left = ''; list.style.right = ''; }
        });
    }

    function positionMenuList(toggle, list) {
        var rect = toggle.getBoundingClientRect();
        // fixed positioning is relative to the viewport — no scrollY offset needed.
        // Align right edge of dropdown to right edge of the toggle button.
        list.style.top   = (rect.bottom + 4) + 'px';
        list.style.right = (window.innerWidth - rect.right) + 'px';
        list.style.left  = '';
    }

    /* ---- Confirm modal ---- */
    var modal      = document.getElementById('confirmModal');
    var modalIcon  = document.getElementById('confirmModalIcon');
    var modalTitle = document.getElementById('confirmModalTitle');
    var modalBody  = document.getElementById('confirmModalBody');
    var modalOk    = document.getElementById('confirmModalOk');
    var modalCancelBtn = document.getElementById('confirmModalCancel');
    var pendingFormId  = null;

    // Wire every [data-confirm] button
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;

        e.preventDefault();
        closeAllMenus();

        pendingFormId = btn.getAttribute('data-form');
        modalIcon.textContent  = btn.getAttribute('data-icon')  || '❓';
        modalTitle.textContent = btn.getAttribute('data-title') || 'Are you sure?';
        modalBody.innerHTML    = btn.getAttribute('data-body')  || '';

        // Colour the confirm button to match the action severity
        modalOk.className = 'btn btn-primary';
        if (btn.classList.contains('item-danger')) {
            modalOk.className = 'btn btn-danger';
        } else if (btn.classList.contains('item-warn')) {
            modalOk.className = 'btn btn-warn';
        }

        modal.classList.add('show');
        modalOk.focus();
    });

    modalOk.addEventListener('click', function () {
        if (pendingFormId) {
            var form = document.getElementById(pendingFormId);
            if (form) form.submit();
        }
        closeModal();
    });

    modalCancelBtn.addEventListener('click', closeModal);

    // Click on backdrop (outside box) cancels
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    function closeModal() {
        modal.classList.remove('show');
        pendingFormId = null;
    }
}());
</script>
