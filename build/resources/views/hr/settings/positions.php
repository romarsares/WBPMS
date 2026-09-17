<?php
use Wbpms\Http\View\Formatter;
/**
 * HR Settings — Job Positions
 *
 * @var list<array<string,mixed>> $positions
 * @var int                       $total
 * @var int                       $active
 * @var int                       $inactive
 * @var array<string,string>      $errors
 * @var int|null                  $editRow    position_id being edited (null = create mode)
 * @var array<string,string>      $input      repopulate on validation failure
 */
$errors  ??= [];
$editRow ??= null;
$input   ??= [];

$v = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($input[$key] ?? $fallback));
$e = static fn(string $key): string => isset($errors[$key])
    ? '<p class="field-error" style="color:#dc2626;font-size:.8rem;margin:.2rem 0 0">'
      . Formatter::escape($errors[$key]) . '</p>'
    : '';
?>

<div class="page-head">
    <div>
        <h1>Job Positions</h1>
        <p>Manage the list of job titles available when adding or editing an employee.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalAddPosition')">+ Add Position</button>
</div>

<!-- Stats -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int) $total ?></span><span class="stat-label">Total Positions</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= (int) $active ?></span><span class="stat-label">Active</span></div>
    <div class="stat-card" style="border-left:4px solid #6b7280"><span class="stat-value"><?= (int) $inactive ?></span><span class="stat-label">Inactive</span></div>
</div>

<!-- Position table -->
<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
        <strong>All Positions</strong>
        <div class="local-search-wrap">⌕<input placeholder="Search…" oninput="filterRows(this,'posTable')"></div>
    </div>
    <table class="data-table" id="posTable">
        <thead>
            <tr>
                <th>Position Title</th>
                <th>Department / Group</th>
                <th style="text-align:center">Sort Order</th>
                <th style="text-align:center">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($positions)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:2rem">
                No positions defined yet. Click <strong>+ Add Position</strong> to create one.
            </td></tr>
        <?php else: foreach ($positions as $p):
            $isActive = $p['status'] === 'Active';
            $badge    = $isActive ? 'ok' : 'off';
        ?>
            <tr>
                <td><strong><?= Formatter::escape($p['position_title']) ?></strong></td>
                <td class="muted"><?= $p['department'] ? Formatter::escape($p['department']) : '—' ?></td>
                <td style="text-align:center"><?= (int) $p['sort_order'] ?></td>
                <td style="text-align:center">
                    <span class="badge <?= $badge ?>"><?= $p['status'] ?></span>
                </td>
                <td style="white-space:nowrap;display:flex;gap:.4rem">
                    <!-- Edit button — opens inline edit modal -->
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="openEditModal(<?= (int)$p['position_id'] ?>,
                                '<?= Formatter::escape(addslashes($p['position_title'])) ?>',
                                '<?= Formatter::escape(addslashes((string)$p['department'])) ?>',
                                <?= (int)$p['sort_order'] ?>)">
                        Edit
                    </button>

                    <!-- Toggle Active / Inactive -->
                    <form method="POST"
                          action="<?= $base ?>/hr/settings/positions/<?= (int)$p['position_id'] ?>/toggle"
                          onsubmit="return confirm('<?= $isActive ? 'Deactivate' : 'Activate' ?> this position?')">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-warn' : 'btn-secondary' ?>">
                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- =====================================================================
     MODAL: Add Position
     ===================================================================== -->
<div id="modalAddPosition" class="modal <?= ($errors !== [] && $editRow === null) ? 'show' : '' ?>">
    <div class="modal-card" style="max-width:480px">
        <div class="modal-header">
            <h3 style="margin:0">Add Job Position</h3>
            <button type="button" onclick="closeModal('modalAddPosition')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="<?= $base ?>/hr/settings/positions">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="add_position_title">Position Title <span style="color:#dc2626">*</span></label>
                <input type="text" id="add_position_title" name="position_title"
                       value="<?= ($editRow === null) ? $v('position_title') : '' ?>"
                       maxlength="100" required autofocus
                       placeholder="e.g. Sales Clerk"
                       class="<?= (isset($errors['position_title']) && $editRow === null) ? 'is-invalid' : '' ?>">
                <?= ($editRow === null) ? $e('position_title') : '' ?>
            </div>

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="add_department">Department / Group <span class="muted">(optional)</span></label>
                <input type="text" id="add_department" name="department"
                       value="<?= ($editRow === null) ? $v('department') : '' ?>"
                       maxlength="100"
                       placeholder="e.g. Warehouse, Admin, Sales">
                <small class="muted">Used for grouping in reports only.</small>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label for="add_sort_order">Sort Order</label>
                <input type="number" id="add_sort_order" name="sort_order"
                       value="<?= ($editRow === null) ? $v('sort_order', '0') : '0' ?>"
                       min="0" max="9999" step="1"
                       placeholder="0 = top of list">
                <small class="muted">Lower number appears higher in the dropdown.</small>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <button type="button" onclick="closeModal('modalAddPosition')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Position</button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================================
     MODAL: Edit Position
     ===================================================================== -->
<div id="modalEditPosition" class="modal <?= ($errors !== [] && $editRow !== null) ? 'show' : '' ?>">
    <div class="modal-card" style="max-width:480px">
        <div class="modal-header">
            <h3 style="margin:0">Edit Job Position</h3>
            <button type="button" onclick="closeModal('modalEditPosition')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" id="editPositionForm" action="<?= $base ?>/hr/settings/positions/0">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="edit_position_title">Position Title <span style="color:#dc2626">*</span></label>
                <input type="text" id="edit_position_title" name="position_title"
                       value="<?= ($editRow !== null) ? $v('position_title') : '' ?>"
                       maxlength="100" required
                       class="<?= (isset($errors['position_title']) && $editRow !== null) ? 'is-invalid' : '' ?>">
                <?= ($editRow !== null) ? $e('position_title') : '' ?>
            </div>

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="edit_department">Department / Group <span class="muted">(optional)</span></label>
                <input type="text" id="edit_department" name="department"
                       value="<?= ($editRow !== null) ? $v('department') : '' ?>"
                       maxlength="100">
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label for="edit_sort_order">Sort Order</label>
                <input type="number" id="edit_sort_order" name="sort_order"
                       value="<?= ($editRow !== null) ? $v('sort_order', '0') : '0' ?>"
                       min="0" max="9999" step="1">
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <button type="button" onclick="closeModal('modalEditPosition')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, title, dept, sort) {
    var form = document.getElementById('editPositionForm');
    form.action = '<?= rtrim($base, '/') ?>/hr/settings/positions/' + id;
    document.getElementById('edit_position_title').value = title;
    document.getElementById('edit_department').value     = dept;
    document.getElementById('edit_sort_order').value     = sort;
    openModal('modalEditPosition');
}

<?php if ($errors !== [] && $editRow !== null): ?>
// Re-open edit modal on server-side validation failure
openEditModal(
    <?= (int) $editRow ?>,
    <?= json_encode($input['position_title'] ?? '') ?>,
    <?= json_encode($input['department']     ?? '') ?>,
    <?= (int) ($input['sort_order'] ?? 0) ?>
);
<?php endif; ?>
</script>
