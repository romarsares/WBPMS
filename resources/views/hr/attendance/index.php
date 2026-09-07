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
$employees ??= [];
$dates     ??= [];
$approvedLeaves ??= [];
$holidayDates ??= [];
$today     ??= date('Y-m-d');
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
    <div style="display:flex;gap:.5rem;align-items:center">
        <button type="button" class="btn btn-secondary no-print" onclick="window.print()">Print Attendance</button>
        <a href="<?= $base ?>/hr/attendance/import" class="btn btn-primary no-print">Import Workbook</a>
    </div>
</div>

<!-- =====================================================================
     Filter bar
     ===================================================================== -->
<div class="card no-print" style="margin-bottom:1.25rem;padding:1rem 1.25rem">
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
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    <select id="cutoffMonthFilter"
                            style="padding:.4rem .6rem;border:1px solid var(--line);border-radius:4px;font-size:.9rem;min-width:140px">
                        <option value="">— Month —</option>
                        <?php
                        // Build unique year-month list from available periods
                        $cutoffMonths = [];
                        foreach ($periods as $p) {
                            $ym = substr($p['period_start'], 0, 7); // YYYY-MM
                            $cutoffMonths[$ym] = true;
                        }
                        ksort($cutoffMonths);
                        $selectedYm = $periodId
                            ? (function() use ($periods, $periodId) {
                                foreach ($periods as $p) {
                                    if ((int)$p['payroll_period_id'] === $periodId) {
                                        return substr($p['period_start'], 0, 7);
                                    }
                                }
                                return '';
                            })()
                            : '';
                        foreach (array_keys($cutoffMonths) as $ym):
                            $label = (new DateTimeImmutable($ym . '-01'))->format('F Y');
                        ?>
                        <option value="<?= Formatter::escape($ym) ?>"<?= $ym === $selectedYm ? ' selected' : '' ?>>
                            <?= Formatter::escape($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="period_id"
                            id="periodSelect"
                            style="padding:.4rem .6rem;border:1px solid var(--line);border-radius:4px;font-size:.9rem;min-width:260px">
                        <option value="">— Select cut-off —</option>
                        <?php foreach ($periods as $p): ?>
                        <option value="<?= (int) $p['payroll_period_id'] ?>"
                                data-ym="<?= Formatter::escape(substr($p['period_start'], 0, 7)) ?>"
                            <?= (int) $p['payroll_period_id'] === $periodId ? 'selected' : '' ?>>
                            <?= Formatter::date($p['period_start']) ?> – <?= Formatter::date($p['period_end']) ?>
                            (pay <?= Formatter::date($p['pay_date']) ?>)
                            <?= $p['status'] !== 'Open' ? ' [' . Formatter::escape($p['status']) . ']' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
<div class="no-print" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
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
<?php
$attendanceByEmployeeDate = [];
foreach ($rows as $row) {
    $attendanceByEmployeeDate[(int) $row['employee_id']][(string) $row['attendance_date']] = $row;
}
$cellStyles = [
    'Complete' => 'background:#ecfdf5;border-color:#a7f3d0;color:#065f46',
    'Approved' => 'background:#ecfdf5;border-color:#a7f3d0;color:#065f46',
    'Incomplete' => 'background:#fffbeb;border-color:#fde68a;color:#92400e',
    'ReviewRequired' => 'background:#fff1f2;border-color:#fecdd3;color:#9f1239',
    'Draft' => 'background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8',
    'Cancelled' => 'background:#f3f4f6;border-color:#d1d5db;color:#6b7280',
    'ApprovedLeave' => 'background:#f3e8ff;border-color:#d8b4fe;color:#7e22ce',
    'Absent' => 'background:#fff1f2;border-color:#fecdd3;color:#be123c',
];
?>
<div class="card" style="padding:0;overflow:hidden">
    <?php if ($employees === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280">No active employees match this filter.</p>
    <?php else: ?>
    <div style="overflow:auto;max-height:calc(100vh - 310px);min-height:360px">
        <table style="border-collapse:separate;border-spacing:0;min-width:max-content;width:100%;font-size:12px">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;top:0;z-index:4;min-width:200px;text-align:left;padding:10px 12px;background:#f8fafc;border-right:1px solid var(--line);border-bottom:1px solid var(--line)">Employee</th>
                    <?php foreach ($dates as $date): $header = new DateTimeImmutable($date); $holidayName = $holidayDates[$date] ?? null; ?>
                    <th style="position:sticky;top:0;z-index:3;min-width:82px;padding:8px 4px;text-align:center;background:#f8fafc;border-right:1px solid var(--line);border-bottom:1px solid var(--line);white-space:nowrap">
                        <span style="display:block;font-size:11px;color:#64748b"><?= $header->format('D') ?></span>
                        <strong><?= $header->format('j') ?></strong>
                        <?php if ($holidayName !== null): ?>
                        <span title="<?= Formatter::escape($holidayName) ?>" style="display:block;margin-top:2px;font-size:8px;line-height:1.1;color:#9f1239;font-weight:700">HOLIDAY</span>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                    <th title="Hours:minutes" style="position:sticky;top:0;z-index:3;min-width:82px;padding:8px 4px;text-align:center;background:#eef2ff;border-left:2px solid #c7d2fe;border-bottom:1px solid var(--line)">Hours<br><small>(H:MM)</small></th>
                    <th title="Total late minutes" style="position:sticky;top:0;z-index:3;min-width:64px;padding:8px 4px;text-align:center;background:#eef2ff;border-bottom:1px solid var(--line)">Late<br><small>(min)</small></th>
                    <th title="Total undertime minutes" style="position:sticky;top:0;z-index:3;min-width:64px;padding:8px 4px;text-align:center;background:#eef2ff;border-bottom:1px solid var(--line)">UT<br><small>(min)</small></th>
                    <th title="Total overtime minutes" style="position:sticky;top:0;z-index:3;min-width:64px;padding:8px 4px;text-align:center;background:#eef2ff;border-bottom:1px solid var(--line)">OT<br><small>(min)</small></th>
                    <th title="Number of absent workdays" style="position:sticky;top:0;z-index:3;min-width:64px;padding:8px 4px;text-align:center;background:#fff1f2;border-bottom:1px solid var(--line)">Absent<br><small>(days)</small></th>
                    <th title="Number of approved leave days" style="position:sticky;top:0;z-index:3;min-width:64px;padding:8px 4px;text-align:center;background:#f3e8ff;border-bottom:1px solid var(--line)">Leave<br><small>(days)</small></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $employee): ?>
                <?php
                $totalWorked = $totalLate = $totalUndertime = $totalOvertime = $absentDays = $leaveDays = 0;
                foreach ($dates as $summaryDate) {
                    $summaryCell = $attendanceByEmployeeDate[(int) $employee['employee_id']][$summaryDate] ?? null;
                    $summaryLeave = $approvedLeaves[(int) $employee['employee_id']][$summaryDate] ?? null;
                    $summaryWeekday = (int) (new DateTimeImmutable($summaryDate))->format('N');
                    $summaryWorkingDay = $summaryWeekday <= 5 && !isset($holidayDates[$summaryDate]);
                    if ($summaryCell !== null) {
                        $totalWorked += (int) $summaryCell['worked_minutes'];
                        $totalLate += (int) $summaryCell['late_minutes'];
                        $totalUndertime += (int) $summaryCell['undertime_minutes'];
                        $totalOvertime += (int) $summaryCell['overtime_minutes'];
                    } elseif ($summaryLeave !== null) {
                        $leaveDays++;
                    } elseif ($summaryDate < $today && $summaryDate >= (string) $employee['hire_date'] && $summaryWorkingDay) {
                        $absentDays++;
                    }
                }
                $totalHours = sprintf('%d:%02d', intdiv($totalWorked, 60), $totalWorked % 60);
                ?>
                <tr>
                    <th scope="row" style="position:sticky;left:0;z-index:2;text-align:left;padding:8px 12px;background:#fff;border-right:1px solid var(--line);border-bottom:1px solid var(--line);white-space:nowrap">
                        <strong><?= Formatter::escape($employee['employee_name']) ?></strong><br>
                        <span style="font-size:10px;color:#64748b"><?= Formatter::escape($employee['employee_number']) ?> · <?= Formatter::escape((string) ($employee['branch_name'] ?? '—')) ?></span>
                    </th>
                    <?php foreach ($dates as $date):
                        $employeeId = (int) $employee['employee_id'];
                        $cell = $attendanceByEmployeeDate[$employeeId][$date] ?? null;
                        $leave = $approvedLeaves[$employeeId][$date] ?? null;
                        $weekday = (int) (new DateTimeImmutable($date))->format('N');
                        $isWorkingDay = $weekday <= 5 && !isset($holidayDates[$date]);
                        $isAbsent = $cell === null
                            && $leave === null
                            && $date < $today
                            && $date >= (string) $employee['hire_date']
                            && $isWorkingDay;
                    ?>
                    <td style="padding:3px;border-right:1px solid var(--line);border-bottom:1px solid var(--line);text-align:center;vertical-align:middle">
                        <?php if ($cell !== null):
                            $status = (string) $cell['status'];
                            $style = $cellStyles[$status] ?? 'background:#f8fafc;border-color:#e2e8f0;color:#475569';
                        ?>
                        <a href="<?= $base ?>/hr/attendance/<?= (int) $cell['attendance_id'] ?>/adjust"
                           title="<?= Formatter::escape($status) ?> — <?= Formatter::escape((string) ($cell['time_in'] ?? 'No time in')) ?> to <?= Formatter::escape((string) ($cell['time_out'] ?? 'No time out')) ?>. Click to review."
                           style="<?= $style ?>;display:block;min-width:72px;padding:5px 3px;border:1px solid;border-radius:4px;text-decoration:none;line-height:1.2">
                            <strong style="font-size:11px"><?= Formatter::escape((string) ($cell['time_in'] ?? '—')) ?></strong><br>
                            <span style="font-size:10px"><?= Formatter::escape((string) ($cell['time_out'] ?? '—')) ?></span>
                            <?php if ($status === 'Draft'): ?><span style="display:block;font-size:9px;font-weight:700">DRAFT</span><?php endif; ?>
                            <?php if ($status === 'Incomplete' || $status === 'ReviewRequired'): ?><span style="display:block;font-size:9px;font-weight:700">REVIEW</span><?php endif; ?>
                        </a>
                        <?php elseif ($leave !== null): ?>
                        <a href="<?= $base ?>/hr/requests/<?= (int) $leave['request_id'] ?>"
                           title="Approved <?= Formatter::escape((string) $leave['leave_type']) ?> leave. Click to view the request."
                           style="<?= $cellStyles['ApprovedLeave'] ?>;display:block;min-width:72px;padding:8px 3px;border:1px solid;border-radius:4px;text-decoration:none;font-size:10px;font-weight:700">
                            LEAVE
                        </a>
                        <?php elseif (isset($holidayDates[$date])): ?>
                        <span title="<?= Formatter::escape((string) $holidayDates[$date]) ?>"
                              style="background:#fff7ed;border-color:#fed7aa;color:#9a3412;display:block;min-width:72px;padding:8px 3px;border:1px solid;border-radius:4px;font-size:9px;font-weight:700">
                            HOLIDAY
                        </span>
                        <?php elseif ($isAbsent): ?>
                        <span title="No attendance or approved leave recorded for this completed workday"
                              style="<?= $cellStyles['Absent'] ?>;display:block;min-width:72px;padding:8px 3px;border:1px solid;border-radius:4px;font-size:10px;font-weight:700">
                            ABSENT
                        </span>
                        <?php else: ?>
                        <span style="display:block;min-width:72px;color:#cbd5e1">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="padding:8px 4px;text-align:center;font-weight:700;background:#eef2ff;border-left:2px solid #c7d2fe;border-bottom:1px solid var(--line)"><?= $totalHours ?></td>
                    <td style="padding:8px 4px;text-align:center;<?= $totalLate > 0 ? 'color:#b45309;font-weight:700;' : '' ?>background:#eef2ff;border-bottom:1px solid var(--line)"><?= $totalLate ?></td>
                    <td style="padding:8px 4px;text-align:center;<?= $totalUndertime > 0 ? 'color:#b45309;font-weight:700;' : '' ?>background:#eef2ff;border-bottom:1px solid var(--line)"><?= $totalUndertime ?></td>
                    <td style="padding:8px 4px;text-align:center;<?= $totalOvertime > 0 ? 'color:#1d4ed8;font-weight:700;' : '' ?>background:#eef2ff;border-bottom:1px solid var(--line)"><?= $totalOvertime ?></td>
                    <td style="padding:8px 4px;text-align:center;<?= $absentDays > 0 ? 'color:#be123c;font-weight:700;' : '' ?>background:#fff1f2;border-bottom:1px solid var(--line)"><?= $absentDays ?></td>
                    <td style="padding:8px 4px;text-align:center;<?= $leaveDays > 0 ? 'color:#7e22ce;font-weight:700;' : '' ?>background:#f3e8ff;border-bottom:1px solid var(--line)"><?= $leaveDays ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:.75rem 1rem;border-top:1px solid var(--line);font-size:12px;color:#64748b">
        Click a populated day to review or adjust it. Green = complete, yellow/red = review or absence, blue = draft import, purple = approved leave, orange = holiday, grey = future/non-working day or cancelled.
    </div>
    <?php endif; ?>
</div>

<?php if (false): ?>
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
<?php endif; ?>

<style media="print">
@page { size: landscape; margin: 8mm; }
.no-print { display: none !important; }
body { background: #fff !important; color: #111827 !important; }
.page-header { margin-bottom: 8px !important; }
.card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
.card[style*="overflow:hidden"] { overflow: visible !important; }
.card[style*="overflow:hidden"] > div[style*="overflow:auto"] { overflow: visible !important; max-height: none !important; }
table { font-size: 9px !important; }
th, td { break-inside: avoid; }
thead th { position: static !important; }
</style>

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

// Month → cut-off cascade filter
(function () {
    var monthSel  = document.getElementById('cutoffMonthFilter');
    var periodSel = document.getElementById('periodSelect');
    if (!monthSel || !periodSel) return;

    function filterPeriods() {
        var ym = monthSel.value;
        var opts = periodSel.querySelectorAll('option[data-ym]');
        var currentVal = periodSel.value;
        var currentStillVisible = false;

        opts.forEach(function (opt) {
            var show = (ym === '' || opt.dataset.ym === ym);
            opt.style.display = show ? '' : 'none';
            if (show && opt.value === currentVal) currentStillVisible = true;
        });

        // If the selected period is now hidden, reset to the prompt
        if (!currentStillVisible) periodSel.value = '';
    }

    monthSel.addEventListener('change', filterPeriods);
    // Run on load to apply the pre-selected state
    filterPeriods();
}());
</script>
