<?php
declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR Settings hub — tabbed view.
 *
 * @var string                       $tab        Active tab: 'positions' | 'periods' | 'holidays'
 *
 * Positions tab:
 * @var list<array<string,mixed>>    $positions
 * @var int                          $total
 * @var int                          $active
 * @var int                          $inactive
 * @var array<string,string>         $errors
 * @var int|null                     $editRow
 * @var array<string,mixed>          $input
 *
 * Periods tab:
 * @var list<array<string,mixed>>    $periods
 * @var string                       $period_start
 *
 * Holidays tab:
 * @var list<array<string,mixed>>    $holidays
 */

$tab          ??= 'positions';
$positions    ??= [];
$total        ??= 0;
$active       ??= 0;
$inactive     ??= 0;
$errors       ??= [];
$editRow      ??= null;
$input        ??= [];
$periods      ??= [];
$period_start ??= '';
$holidays     ??= [];

$e = static fn(string $key): string => isset($errors[$key])
    ? '<p class="field-error" style="color:#dc2626;font-size:.8rem;margin:.2rem 0 0">'
      . Formatter::escape($errors[$key]) . '</p>'
    : '';
$v = static fn(string $key, string $fallback = ''): string =>
    Formatter::escape((string) ($input[$key] ?? $fallback));
?>

<!-- =========================================================
     Page header
     ========================================================= -->
<div class="page-head">
    <div>
        <h1>Settings</h1>
        <p>Manage job positions, payroll periods, and the holiday calendar.</p>
    </div>
</div>

<!-- =========================================================
     Tab strip
     ========================================================= -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--line);margin-bottom:1.5rem">
    <?php
    $tabs = [
        'positions' => ['⚙', 'Job Positions',    '/hr/settings/positions'],
        'periods'   => ['📅', 'Payroll Periods',  '/hr/settings/periods'],
        'holidays'  => ['🎉', 'Holidays',          '/hr/settings/holidays'],
    ];
    foreach ($tabs as $key => [$icon, $label, $href]):
        $isActive = $tab === $key;
    ?>
        <a href="<?= $base ?><?= $href ?>"
           style="display:inline-flex;align-items:center;gap:.4rem;
                  padding:.6rem 1.2rem;font-weight:600;font-size:.875rem;
                  text-decoration:none;white-space:nowrap;
                  border-bottom:3px solid <?= $isActive ? 'var(--accent)' : 'transparent' ?>;
                  margin-bottom:-2px;
                  color:<?= $isActive ? 'var(--accent)' : 'var(--muted)' ?>;
                  transition:color .15s,border-color .15s">
            <span aria-hidden="true"><?= $icon ?></span>
            <?= Formatter::escape($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- =========================================================
     TAB: Job Positions
     ========================================================= -->
<?php if ($tab === 'positions'): ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <p class="muted" style="margin:0">Manage the job titles used when adding or editing an employee.</p>
    <button class="btn btn-primary" onclick="openModal('modalAddPosition')">+ Add Position</button>
</div>

<!-- Stats strip -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int) $total ?></span><span class="stat-label">Total</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= (int) $active ?></span><span class="stat-label">Active</span></div>
    <div class="stat-card" style="border-left:4px solid #6b7280"><span class="stat-value"><?= (int) $inactive ?></span><span class="stat-label">Inactive</span></div>
</div>

<!-- Table -->
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
                <th style="text-align:center">Sort</th>
                <th style="text-align:center">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($positions)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:2rem">
                No positions defined yet. Click <strong>+ Add Position</strong> to get started.
            </td></tr>
        <?php else: foreach ($positions as $p):
            $isActive = $p['status'] === 'Active';
        ?>
            <tr>
                <td><strong><?= Formatter::escape($p['position_title']) ?></strong></td>
                <td class="muted"><?= $p['department'] ? Formatter::escape($p['department']) : '—' ?></td>
                <td style="text-align:center"><?= (int) $p['sort_order'] ?></td>
                <td style="text-align:center">
                    <span class="badge <?= $isActive ? 'ok' : 'off' ?>"><?= $p['status'] ?></span>
                </td>
                <td style="white-space:nowrap;display:flex;gap:.4rem">
                    <button type="button" class="btn btn-secondary btn-sm js-edit-pos"
                            data-id="<?= (int) $p['position_id'] ?>"
                            data-title="<?= Formatter::escape($p['position_title']) ?>"
                            data-dept="<?= Formatter::escape((string) $p['department']) ?>"
                            data-sort="<?= (int) $p['sort_order'] ?>">
                        Edit
                    </button>
                    <form method="POST"
                          action="<?= $base ?>/hr/settings/positions/<?= (int) $p['position_id'] ?>/toggle"
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

<!-- Modal: Add Position -->
<div id="modalAddPosition" class="modal <?= ($errors !== [] && $editRow === null) ? 'show' : '' ?>">
    <div class="modal-card" style="max-width:480px">
        <h3 style="margin:0 0 1.1rem">Add Job Position</h3>
        <button type="button" class="modal-close" onclick="closeModal('modalAddPosition')" aria-label="Close">&times;</button>
        <form method="POST" action="<?= $base ?>/hr/settings/positions">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group" style="margin-bottom:.85rem">
                <label for="add_position_title">Position Title <span style="color:#dc2626">*</span></label>
                <input type="text" id="add_position_title" name="position_title"
                       value="<?= ($editRow === null) ? $v('position_title') : '' ?>"
                       maxlength="100" required autofocus placeholder="e.g. Sales Clerk"
                       class="<?= (isset($errors['position_title']) && $editRow === null) ? 'is-invalid' : '' ?>">
                <?= ($editRow === null) ? $e('position_title') : '' ?>
            </div>
            <div class="form-group" style="margin-bottom:.85rem">
                <label for="add_department">Department / Group <span class="muted">(optional)</span></label>
                <input type="text" id="add_department" name="department"
                       value="<?= ($editRow === null) ? $v('department') : '' ?>"
                       maxlength="100" placeholder="e.g. Warehouse, Admin, Sales">
                <small class="muted">Used for grouping in reports only.</small>
            </div>
            <div class="form-group" style="margin-bottom:1.25rem">
                <label for="add_sort_order">Sort Order</label>
                <input type="number" id="add_sort_order" name="sort_order"
                       value="<?= ($editRow === null) ? $v('sort_order', '0') : '0' ?>"
                       min="0" max="9999" step="1" placeholder="0 = top of list">
                <small class="muted">Lower number appears higher in dropdowns.</small>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <button type="button" onclick="closeModal('modalAddPosition')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Position</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Position -->
<div id="modalEditPosition" class="modal <?= ($errors !== [] && $editRow !== null) ? 'show' : '' ?>">
    <div class="modal-card" style="max-width:480px">
        <h3 style="margin:0 0 1.1rem">Edit Job Position</h3>
        <button type="button" class="modal-close" onclick="closeModal('modalEditPosition')" aria-label="Close">&times;</button>
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

// Wire edit buttons — reads from data-* so no inline JS / escaping issues
document.querySelectorAll('.js-edit-pos').forEach(function(btn) {
    btn.addEventListener('click', function() {
        openEditModal(
            this.dataset.id,
            this.dataset.title,
            this.dataset.dept,
            this.dataset.sort
        );
    });
});
<?php if ($errors !== [] && $editRow !== null): ?>
openEditModal(
    <?= (int) $editRow ?>,
    <?= json_encode($input['position_title'] ?? '') ?>,
    <?= json_encode($input['department']     ?? '') ?>,
    <?= (int) ($input['sort_order'] ?? 0) ?>
);
<?php endif; ?>
</script>

<?php endif; /* positions tab */ ?>

<!-- =========================================================
     TAB: Payroll Periods
     ========================================================= -->
<?php if ($tab === 'periods'): ?>

<p class="muted" style="margin:0 0 1.25rem">
    Weekly pay windows run Sunday through Friday. Salary is released on the period-ending Friday.
</p>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;align-items:start">

    <!-- Add form -->
    <div class="card">
        <h3 style="margin:0 0 1.1rem;font-size:15px">Add New Period</h3>

        <?php if (!empty($errors)): ?>
        <div class="alert error" role="alert" style="margin-bottom:1rem">
            <?php foreach ($errors as $msg): ?>
                <p style="margin:.1rem 0"><?= Formatter::escape((string) $msg) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $base ?>/hr/settings/periods" novalidate>
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <div class="form-group">
                <label for="period_start">
                    Period Start (Sunday) <span style="color:var(--bad)">*</span>
                </label>
                <input type="date" id="period_start" name="period_start"
                       class="form-control <?= !empty($errors) ? 'is-invalid' : '' ?>"
                       value="<?= Formatter::escape($period_start) ?>" required>
                <span class="help">Must be a Sunday. The period ends and salary is released on Friday.</span>
            </div>

            <!-- JS preview of derived dates -->
            <div id="period-preview"
                 style="display:none;background:var(--surface-2);border:1px solid var(--line);
                        border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:1rem;font-size:13px">
                <div style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px">
                    <span style="color:var(--muted)">Period end:</span>  <strong id="preview-end">—</strong>
                    <span style="color:var(--muted)">Pay date:</span>    <strong id="preview-pay">—</strong>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Create Period</button>
        </form>
    </div>

    <!-- Period list -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px 12px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:15px">All Periods</h3>
            <span class="muted" style="font-size:13px"><?= count($periods) ?> period(s)</span>
        </div>

        <?php if (empty($periods)): ?>
            <p class="muted" style="padding:2rem;text-align:center">No periods yet. Use the form to create the first one.</p>
        <?php else: ?>
        <?php
        $statusBadge = static function (string $s): string {
            $map = [
                'Open'             => 'ok',
                'AttendanceClosed' => 'warn',
                'Disbursed'        => 'info',
                'Closed'           => 'off',
            ];
            return '<span class="badge ' . ($map[$s] ?? 'off') . '">' . htmlspecialchars($s, ENT_QUOTES) . '</span>';
        };
        ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period Start</th>
                    <th>Period End</th>
                    <th>Pay Date</th>
                    <th style="text-align:center">Runs</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periods as $p):
                $isOpen = $p['status'] === 'Open';
            ?>
            <tr style="<?= !$isOpen ? 'opacity:.7' : '' ?>">
                <td style="font-weight:600">
                    <?= Formatter::date($p['period_start']) ?>
                    <span class="muted" style="font-size:11px;margin-left:4px">
                        <?= (new DateTimeImmutable($p['period_start']))->format('D') ?>
                    </span>
                </td>
                <td>
                    <?= Formatter::date($p['period_end']) ?>
                    <span class="muted" style="font-size:11px;margin-left:4px">
                        <?= (new DateTimeImmutable($p['period_end']))->format('D') ?>
                    </span>
                </td>
                <td style="color:var(--ok-text);font-weight:600">
                    <?= Formatter::date($p['pay_date']) ?>
                </td>
                <td style="text-align:center">
                    <?php if ((int) $p['run_count'] > 0): ?>
                        <a href="<?= $base ?>/hr/payroll" style="font-weight:600;color:var(--accent)"><?= (int) $p['run_count'] ?></a>
                    <?php else: ?>
                        <span class="muted">0</span>
                    <?php endif; ?>
                </td>
                <td><?= $statusBadge($p['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
    var input      = document.getElementById('period_start');
    var preview    = document.getElementById('period-preview');
    var previewEnd = document.getElementById('preview-end');
    var previewPay = document.getElementById('preview-pay');
    var DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function fmt(d) {
        return DAYS[d.getDay()] + ', ' + MONTHS[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }
    function addDays(d, n) { var r = new Date(d); r.setDate(r.getDate() + n); return r; }

    input.addEventListener('change', function () {
        var parts = this.value.split('-');
        if (parts.length !== 3) { preview.style.display = 'none'; return; }
        var start = new Date(+parts[0], +parts[1] - 1, +parts[2]);
        if (isNaN(start.getTime()) || start.getDay() !== 0) { preview.style.display = 'none'; return; }
        previewEnd.textContent = fmt(addDays(start, 5)) + ' (Fri)';
        previewPay.textContent = fmt(addDays(start, 5)) + ' (Fri)';
        preview.style.display = 'block';
    });
    if (input && input.value) { input.dispatchEvent(new Event('change')); }
}());
</script>

<?php endif; /* periods tab */ ?>

<!-- =========================================================
     TAB: Holidays
     ========================================================= -->
<?php if ($tab === 'holidays'): ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;align-items:start">

    <!-- Add form -->
    <div class="card">
        <h3 style="margin:0 0 1.1rem;font-size:15px">Add Holiday</h3>

        <form method="POST" action="<?= $base ?>/hr/settings/holidays">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="holiday_date">Date <span style="color:#dc2626">*</span></label>
                <input type="date" name="holiday_date" id="holiday_date" class="form-control"
                       value="<?= Formatter::escape($input['holiday_date'] ?? '') ?>" required>
                <?= $e('holiday_date') ?>
            </div>

            <div class="form-group" style="margin-bottom:.85rem">
                <label for="h_description">Name / Description <span style="color:#dc2626">*</span></label>
                <input type="text" name="description" id="h_description" class="form-control"
                       value="<?= Formatter::escape($input['description'] ?? '') ?>"
                       placeholder="e.g. New Year's Day" maxlength="100" required>
                <?= $e('description') ?>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label for="holiday_type">Type <span style="color:#dc2626">*</span></label>
                <select name="holiday_type" id="holiday_type" class="form-control" required>
                    <option value="">— Select —</option>
                    <option value="Regular" <?= ($input['holiday_type'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular (200%)</option>
                    <option value="Special" <?= ($input['holiday_type'] ?? '') === 'Special' ? 'selected' : '' ?>>Special (130%)</option>
                </select>
                <?= $e('holiday_type') ?>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Add Holiday</button>
        </form>
    </div>

    <!-- Holiday list -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
            <strong>All Holidays</strong>
            <div class="local-search-wrap">⌕<input placeholder="Search…" oninput="filterRows(this,'holTable')"></div>
        </div>

        <?php if (empty($holidays)): ?>
            <p class="muted" style="padding:2rem;text-align:center">No holidays defined yet.</p>
        <?php else: ?>
        <table class="data-table" id="holTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th style="text-align:center">Multiplier</th>
                    <th style="text-align:center">Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($holidays as $h):
                $hActive = $h['status'] === 'Active';
            ?>
                <tr style="<?= !$hActive ? 'opacity:.6' : '' ?>">
                    <td style="white-space:nowrap"><?= Formatter::escape($h['holiday_date']) ?></td>
                    <td><strong><?= Formatter::escape($h['description']) ?></strong></td>
                    <td>
                        <span class="badge <?= $h['holiday_type'] === 'Regular' ? 'ok' : 'warn' ?>">
                            <?= Formatter::escape($h['holiday_type']) ?>
                        </span>
                    </td>
                    <td style="text-align:center"><?= Formatter::escape((string) $h['pay_multiplier']) ?>×</td>
                    <td style="text-align:center">
                        <span class="badge <?= $hActive ? 'ok' : 'off' ?>"><?= $h['status'] ?></span>
                    </td>
                    <td style="white-space:nowrap;display:flex;gap:.4rem">
                        <a href="<?= $base ?>/hr/settings/holidays/<?= (int) $h['holiday_id'] ?>/edit"
                           class="btn btn-sm btn-secondary">Edit</a>
                        <?php if ($hActive): ?>
                        <form method="POST"
                              action="<?= $base ?>/hr/settings/holidays/<?= (int) $h['holiday_id'] ?>/delete"
                              onsubmit="return confirm('Deactivate this holiday?')">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-sm btn-warn">Deactivate</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<?php endif; /* holidays tab */ ?>
