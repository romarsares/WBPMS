<?php
/**
 * View: hr/attendance/index  (GET /hr/attendance)
 *
 * Variables:
 *   array   $rows         — attendance rows
 *   int     $total        — count in range
 *   int     $complete     — complete/approved count
 *   int     $incomplete   — incomplete/review count
 *   int     $unmatched    — unmatched punches count
 *   string  $activeTab    — 'month' | 'cutoff'
 *   string  $monthInput   — YYYY-MM value for the month picker
 *   int|null $periodId    — selected payroll_period_id
 *   string  $dateFrom     — resolved range start (YYYY-MM-DD)
 *   string  $dateTo       — resolved range end (YYYY-MM-DD)
 *   array   $periods      — payroll_period rows for cut-off dropdown
 *   array   $branches     — branch rows for branch filter
 *   int|null $branchId    — selected branch_id
 *   string  $search       — employee name/number search term
 *   string  $base         — injected by ViewRenderer
 *   string  $csrf         — injected by ViewRenderer
 */

use Wbpms\Http\View\Formatter;

$rows      ??= [];
$periods   ??= [];
$branches  ??= [];
$activeTab ??= 'month';
$monthInput ??= date('Y-m');
$periodId  ??= null;
$dateFrom  ??= '';
$dateTo    ??= '';
$branchId  ??= null;
$search    ??= '';

// Format a date range label for display
$rangeLabel = $dateFrom !== '' && $dateTo !== ''
    ? Formatter::date($dateFrom) . ' – ' . Formatter::date($dateTo)
    : '';
?>

<div class="page-header">
    <div>
        <h1>Attendance Management</h1>
        <p>Review employee timesheets and attendance records.</p>
    </div>
    <a href="<?= $base ?>/hr/attendance/import" class="btn btn-primary">Import Workbook</a>
</div>

<!-- =====================================================================
     Filter bar
     ===================================================================== -->
<div class="card" style="margin-bottom:1.25rem;padding:1rem 1.25rem">
    <form method="GET" action="<?= $base ?>/hr/attendance" id="attendanceFilter">

        <!-- Tab switcher -->
        <div style="display:flex;gap:0;margin-bottom:1rem;border:1px solid var(--line);border-radius:6px;overflow:hidden;width:fit-content">
            <button type="button"
                    onclick="setTab('month')"
                    id="tab-month"
                    class="btn <?= $activeTab === 'month' ? 'btn-primary' : 'btn-secondary' ?>"
                    style="border-radius:0;border:none;padding:.4rem 1rem;font-size:.85rem">
                By Month
            </button>
            <button type="button"
                    onclick="setTab('cutoff')"
                    id="tab-cutoff"
                    class="btn <?= $activeTab === 'cutoff' ? 'btn-primary' : 'btn-secondary' ?>"
                    style="border-radius:0;border:none;border-left:1px solid var(--line);padding:.4rem 1rem;font-size:.85rem">
                By Cut-off Period
            </button>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">

            <!-- Month picker -->
            <div id="panel-month" style="display:<?= $activeTab === 'month' ? 'block' : 'none' ?>">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem">Month</label>
                <input type="month"
                       name="month"
                       id="monthPicker"
                       value="<?= Formatter::escape($monthInput) ?>"
                       style="padding:.4rem .6rem;border:1px solid var(--line);border-radius:4px;font-size:.9rem">
            </div>

            <!-- Cut-off period dropdown -->
            <div id="panel-cutoff" style="display:<?= $activeTab === 'cutoff' ? 'block' : 'none' ?>">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem">Cut-off Period (Fri–Thu)</label>
                <?php if (empty($periods)): ?>
                    <span style="font-size:.85rem;color:#6b7280">No payroll periods defined yet.</span>
                    <input type="hidden" name="period_id" value="">
                <?php else: ?>
                <select name="period_id"
                        id="periodSelect"
                        style="padding:.4rem .6rem;border:1px solid var(--line);border-radius:4px;font-size:.9rem;min-width:260px">
                    <option value="">— All cut-offs —</option>
                    <?php foreach ($periods as $p): ?>
                    <option value="<?= (int) $p['payroll_period_id'] ?>"
                        <?= (int) $p['payroll_period_id'] === $periodId ? 'selected' : '' ?>>
                        <?= Formatter::date($p['period_start']) ?> – <?= Formatter::date($p['period_end']) ?>
                        (pay <?= Formatter::date($p['pay_date']) ?>)
                        <?= $p['status'] !== 'Open' ? ' [' . Formatter::escape($p['status']) . ']' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Branch filter -->
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem">Branch</label>
                <select name="branch_id"
                        style="padding:.4rem .6rem;border:1px solid var(--line);border-radius:4px;font-size:.9rem;min-width:160px">
                    <option value="">All branches</option>
                    <?php foreach ($branches as $br): ?>
                    <option value="<?= (int) $br['branch_id'] ?>"
                        <?= (int) $br['branch_id'] === $branchId ? 'selected' : '' ?>>
                        <?= Formatter::escape($br['branch_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Employee search -->
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem">Employee</label>
                <input type="text"
                       name="search"
                       value="<?= Formatter::escape($search) ?>"
                       placeholder="Name or ID…"
                       style="padding:.4rem .6rem;border:1px solid var(--line);border-radius:4px;font-size:.9rem;width:160px">
            </div>

            <!-- Submit / clear -->
            <div style="display:flex;gap:.5rem;align-items:flex-end">
                <button type="submit" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.9rem">Apply</button>
                <a href="<?= $base ?>/hr/attendance" class="btn btn-secondary" style="padding:.4rem .9rem;font-size:.9rem">Clear</a>
            </div>
        </div>

        <!-- Hidden active-tab field so the controller knows which mode is active -->
        <input type="hidden" name="_tab" id="hiddenTab" value="<?= Formatter::escape($activeTab) ?>">
    </form>

    <?php if ($rangeLabel !== ''): ?>
    <p style="margin:.75rem 0 0;font-size:.8rem;color:#6b7280">
        Showing records for <strong><?= Formatter::escape($rangeLabel) ?></strong>
    </p>
    <?php endif; ?>
</div>

<!-- =====================================================================
     Summary cards
     ===================================================================== -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card">
        <span class="stat-value"><?= (int) $total ?></span>
        <span class="stat-label">Records in Range</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #10b981">
        <span class="stat-value"><?= (int) $complete ?></span>
        <span class="stat-label">Complete</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #f59e0b">
        <span class="stat-value"><?= (int) $incomplete ?></span>
        <span class="stat-label">Incomplete / Review</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #ef4444">
        <span class="stat-value"><?= (int) $unmatched ?></span>
        <span class="stat-label">Unmatched Punches</span>
    </div>
</div>

<?php if ($incomplete > 0): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;color:#92400e;font-size:.875rem">
    ⚠ <strong><?= (int) $incomplete ?></strong> record(s) are incomplete or require review in this range.
</div>
<?php endif; ?>

<!-- =====================================================================
     Attendance table
     ===================================================================== -->
<div class="card" style="padding:0;overflow-x:auto">
    <?php if ($rows === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280">
        No attendance records found for this period.
        <?php if ($dateFrom === ''): ?>
            <a href="<?= $base ?>/hr/attendance/import">Import a workbook →</a>
        <?php endif; ?>
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Branch</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th style="text-align:right">Worked (min)</th>
                <th style="text-align:right">Late (min)</th>
                <th style="text-align:right">UT (min)</th>
                <th style="text-align:right">OT (min)</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr<?= $r['is_incomplete'] ? ' style="background:#fffbeb"' : '' ?>>
            <td style="white-space:nowrap"><?= Formatter::date($r['attendance_date']) ?></td>
            <td>
                <strong><?= Formatter::escape($r['employee_name']) ?></strong><br>
                <small style="color:#6b7280"><?= Formatter::escape($r['employee_number']) ?></small>
            </td>
            <td><?= Formatter::escape((string) ($r['branch_name'] ?? '—')) ?></td>
            <td><?= Formatter::escape((string) ($r['time_in']  ?? '—')) ?></td>
            <td><?= Formatter::escape((string) ($r['time_out'] ?? '—')) ?></td>
            <td style="text-align:right"><?= (int) $r['worked_minutes'] ?></td>
            <td style="text-align:right<?= (int) $r['late_minutes'] > 0 ? ';color:#b45309;font-weight:600' : '' ?>">
                <?= (int) $r['late_minutes'] ?>
            </td>
            <td style="text-align:right<?= (int) $r['undertime_minutes'] > 0 ? ';color:#b45309' : '' ?>">
                <?= (int) $r['undertime_minutes'] ?>
            </td>
            <td style="text-align:right<?= (int) $r['overtime_minutes'] > 0 ? ';color:#1d4ed8;font-weight:600' : '' ?>">
                <?= (int) $r['overtime_minutes'] ?>
            </td>
            <td>
                <?php
                $statusColors = [
                    'Complete'      => '#10b981',
                    'Approved'      => '#10b981',
                    'Incomplete'    => '#f59e0b',
                    'ReviewRequired'=> '#ef4444',
                ];
                $c = $statusColors[$r['status'] ?? ''] ?? '#6b7280';
                ?>
                <span style="background:<?= $c ?>;color:#fff;padding:2px 7px;border-radius:9999px;font-size:.7rem;white-space:nowrap">
                    <?= Formatter::escape((string) ($r['status'] ?? '')) ?>
                </span>
            </td>
            <td style="text-align:right">
                <a href="<?= $base ?>/hr/attendance/<?= (int) $r['attendance_id'] ?>/adjust"
                   class="btn btn-secondary" style="padding:.3rem .65rem;font-size:.8rem">Adjust</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($rows) >= 500): ?>
    <p style="padding:.75rem 1rem;font-size:.8rem;color:#6b7280;border-top:1px solid var(--line)">
        Showing first 500 records. Narrow the filter to see more.
    </p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Tab switcher — shows/hides the month picker vs cut-off dropdown
// and syncs the hidden _tab field so the controller knows which mode is active.
function setTab(tab) {
    document.getElementById('panel-month').style.display  = tab === 'month'  ? 'block' : 'none';
    document.getElementById('panel-cutoff').style.display = tab === 'cutoff' ? 'block' : 'none';
    document.getElementById('hiddenTab').value            = tab;
    document.getElementById('tab-month').className  = 'btn ' + (tab === 'month'  ? 'btn-primary' : 'btn-secondary');
    document.getElementById('tab-cutoff').className = 'btn ' + (tab === 'cutoff' ? 'btn-primary' : 'btn-secondary');
    // Clear the inactive filter so it doesn't interfere
    if (tab === 'month')  { var s = document.getElementById('periodSelect'); if (s) s.value = ''; }
    if (tab === 'cutoff') { var m = document.getElementById('monthPicker');  if (m) m.value = ''; }
}
</script>
