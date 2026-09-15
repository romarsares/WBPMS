<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Attendance frequency report: late-arrival occurrences and verified absence
 * days. The controller applies the same schedule, leave, holiday, and import
 * coverage rules used by Attendance Management.
 *
 * @var list<array{employee_number:string,employee_name:string,branch_name:string,tardy_days:int,late_minutes:int,absence_days:int,frequency:int}> $rows
 * @var array{tardy_days:int,late_minutes:int,absence_days:int,employees_affected:int} $summary
 */

$rows ??= [];
$summary ??= ['tardy_days' => 0, 'late_minutes' => 0, 'absence_days' => 0, 'employees_affected' => 0];
$branches ??= [];
$dateFrom ??= '';
$dateTo ??= '';
$branchId ??= null;
$search ??= '';
$backPath ??= '/hr/attendance';
$backLabel ??= 'Back to Attendance';
$rangeLabel = $dateFrom !== '' && $dateTo !== ''
    ? Formatter::date($dateFrom) . ' – ' . Formatter::date($dateTo)
    : 'Selected period';
?>

<div class="page-header attendance-report-header">
    <div>
        <p class="report-kicker">Attendance Management</p>
        <h1>Attendance Frequency Report</h1>
        <p>Track tardiness and verified absences by employee for a selected period.</p>
    </div>
    <div class="report-actions no-print">
        <a href="<?= $base ?><?= Formatter::escape($backPath) ?>" class="btn btn-secondary"><?= Formatter::escape($backLabel) ?></a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Report</button>
    </div>
</div>

<section class="attendance-report-filter no-print" aria-label="Frequency report filters">
    <form method="GET" action="<?= $base ?>/hr/attendance/frequency-report">
        <div>
            <label for="frequencyFrom">From</label>
            <input id="frequencyFrom" type="date" name="date_from" value="<?= Formatter::escape($dateFrom) ?>">
        </div>
        <div>
            <label for="frequencyTo">To</label>
            <input id="frequencyTo" type="date" name="date_to" value="<?= Formatter::escape($dateTo) ?>">
        </div>
        <div>
            <label for="frequencyBranch">Branch</label>
            <select id="frequencyBranch" name="branch_id">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['branch_id'] ?>"<?= (int) $branch['branch_id'] === (int) $branchId ? ' selected' : '' ?>>
                    <?= Formatter::escape((string) $branch['branch_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="frequency-search">
            <label for="frequencySearch">Employee</label>
            <input id="frequencySearch" type="search" name="search" value="<?= Formatter::escape($search) ?>" placeholder="Name or employee number">
        </div>
        <div class="frequency-filter-actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="<?= $base ?>/hr/attendance/frequency-report" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</section>

<section class="report-print-title print-only">
    <strong>Light Diamond Enterprises</strong><br>
    Attendance Frequency Report &middot; <?= Formatter::escape($rangeLabel) ?>
</section>

<section class="frequency-summary" aria-label="Report totals">
    <article class="frequency-stat frequency-stat-amber">
        <span><?= (int) $summary['tardy_days'] ?></span>
        <small>Tardy occurrences</small>
    </article>
    <article class="frequency-stat frequency-stat-orange">
        <span><?= number_format((int) $summary['late_minutes']) ?></span>
        <small>Total late minutes</small>
    </article>
    <article class="frequency-stat frequency-stat-rose">
        <span><?= (int) $summary['absence_days'] ?></span>
        <small>Verified absence days</small>
    </article>
    <article class="frequency-stat frequency-stat-indigo">
        <span><?= (int) $summary['employees_affected'] ?></span>
        <small>Employees with incidents</small>
    </article>
</section>

<section class="frequency-visual card">
    <div class="frequency-visual-head">
        <div>
            <h2>Highest frequency</h2>
            <p>Top employees by the selected incident type. Click a category to update the ranking.</p>
        </div>
        <div class="frequency-mode-toggle no-print" role="group" aria-label="Chart category">
            <button type="button" class="is-active" data-sort="frequency">Combined</button>
            <button type="button" data-sort="tardy">Tardiness</button>
            <button type="button" data-sort="absence">Absences</button>
        </div>
    </div>
    <div id="frequencyChart" class="frequency-chart" aria-live="polite"></div>
</section>

<section class="frequency-table-card card">
    <div class="frequency-table-head">
        <div>
            <h2>Employee frequency detail</h2>
            <p><?= Formatter::escape($rangeLabel) ?> &middot; <?= count($rows) ?> employee<?= count($rows) === 1 ? '' : 's' ?> in scope</p>
        </div>
        <p class="frequency-rule no-print">Absences exclude approved leave, holidays, unscheduled days, future dates, and dates without approved import coverage.</p>
    </div>
    <div class="frequency-table-wrap">
        <table id="frequencyTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Branch</th>
                    <th class="number">Tardy days</th>
                    <th class="number">Late minutes</th>
                    <th class="number">Absence days</th>
                    <th class="number">Total frequency</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                <tr><td colspan="6" class="frequency-empty">No active employees match the selected filters.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                <tr data-tardy="<?= (int) $row['tardy_days'] ?>" data-absence="<?= (int) $row['absence_days'] ?>" data-frequency="<?= (int) $row['frequency'] ?>">
                    <td>
                        <strong><?= Formatter::escape($row['employee_name']) ?></strong>
                        <small><?= Formatter::escape($row['employee_number']) ?></small>
                    </td>
                    <td><?= Formatter::escape($row['branch_name']) ?></td>
                    <td class="number"><?= (int) $row['tardy_days'] ?></td>
                    <td class="number"><?= number_format((int) $row['late_minutes']) ?></td>
                    <td class="number absence-value"><?= (int) $row['absence_days'] ?></td>
                    <td class="number total-value"><?= (int) $row['frequency'] ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <footer>Generated <?= Formatter::escape(date('F j, Y g:i A')) ?> &middot; Frequency counts are for attendance review and do not apply discipline automatically.</footer>
</section>

<style>
.attendance-report-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.15rem; }
.attendance-report-header h1 { margin:.1rem 0 .25rem; }
.attendance-report-header p { margin:0; color:#64748b; }
.report-kicker { color:#4f46e5 !important; font-size:.74rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
.report-actions { display:flex; gap:.5rem; flex-wrap:wrap; justify-content:flex-end; }
.attendance-report-filter { background:#fff; border:1px solid var(--line); border-radius:10px; padding:1rem 1.1rem; margin-bottom:1rem; }
.attendance-report-filter form { display:flex; flex-wrap:wrap; align-items:end; gap:.8rem; }
.attendance-report-filter label { display:block; font-size:.76rem; color:#475569; font-weight:700; margin-bottom:.28rem; }
.attendance-report-filter input, .attendance-report-filter select { min-height:36px; border:1px solid #cbd5e1; border-radius:6px; padding:.4rem .6rem; background:#fff; }
.frequency-search input { width:200px; }
.frequency-filter-actions { display:flex; gap:.45rem; }
.frequency-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:1rem; margin-bottom:1rem; }
.frequency-stat { min-height:105px; padding:1rem 1.1rem; border:1px solid var(--line); border-radius:10px; background:#fff; display:flex; flex-direction:column; justify-content:space-between; border-left:5px solid #94a3b8; }
.frequency-stat span { font-size:1.8rem; line-height:1; font-weight:800; color:#0f172a; }
.frequency-stat small { color:#64748b; font-size:.8rem; font-weight:700; }
.frequency-stat-amber { border-left-color:#f59e0b; }.frequency-stat-orange { border-left-color:#f97316; }.frequency-stat-rose { border-left-color:#e11d48; }.frequency-stat-indigo { border-left-color:#4f46e5; }
.frequency-visual { padding:1.1rem 1.25rem; margin-bottom:1rem; }
.frequency-visual-head, .frequency-table-head { display:flex; gap:1rem; justify-content:space-between; align-items:flex-start; }
.frequency-visual h2, .frequency-table-head h2 { margin:0 0 .2rem; font-size:1rem; }.frequency-visual p, .frequency-table-head p { color:#64748b; margin:0; font-size:.8rem; }
.frequency-mode-toggle { display:flex; background:#f1f5f9; padding:3px; gap:2px; border-radius:7px; }
.frequency-mode-toggle button { background:transparent; border:0; border-radius:5px; cursor:pointer; color:#475569; font-size:.78rem; font-weight:700; padding:.35rem .55rem; }.frequency-mode-toggle button.is-active { background:#fff; box-shadow:0 1px 2px #cbd5e1; color:#312e81; }
.frequency-chart { margin-top:1.15rem; display:grid; gap:.6rem; }.frequency-chart-row { display:grid; grid-template-columns:minmax(135px, 22%) 1fr 46px; align-items:center; gap:.65rem; }.frequency-chart-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#334155; font-size:.8rem; font-weight:700; }.frequency-chart-track { height:18px; background:#f1f5f9; border-radius:999px; overflow:hidden; }.frequency-chart-bar { height:100%; min-width:2px; border-radius:999px; background:linear-gradient(90deg, #f59e0b, #f97316); transition:width .2s ease; }.frequency-chart-row.absence .frequency-chart-bar { background:linear-gradient(90deg, #fb7185, #e11d48); }.frequency-chart-value { text-align:right; color:#475569; font-size:.78rem; font-weight:800; }.frequency-chart-empty { padding:1.5rem 0; color:#64748b; text-align:center; font-size:.86rem; }
.frequency-table-card { padding:0; overflow:hidden; }.frequency-table-head { padding:1.1rem 1.25rem .85rem; border-bottom:1px solid var(--line); }.frequency-rule { max-width:410px; text-align:right; }.frequency-table-wrap { overflow:auto; }.frequency-table-card table { width:100%; border-collapse:collapse; }.frequency-table-card th { background:#f8fafc; color:#475569; text-align:left; padding:.7rem .85rem; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }.frequency-table-card td { border-top:1px solid #eef2f7; padding:.72rem .85rem; font-size:.86rem; }.frequency-table-card td small { display:block; color:#64748b; margin-top:.12rem; font-size:.74rem; }.frequency-table-card .number { text-align:right; font-variant-numeric:tabular-nums; }.absence-value { color:#be123c; font-weight:800; }.total-value { color:#312e81; font-weight:800; }.frequency-empty { padding:2rem !important; text-align:center; color:#64748b; }.frequency-table-card footer { border-top:1px solid var(--line); color:#64748b; font-size:.75rem; padding:.7rem 1.25rem; }.print-only { display:none; }
@media (max-width:800px) { .attendance-report-header, .frequency-visual-head, .frequency-table-head { flex-direction:column; }.report-actions { justify-content:flex-start; }.frequency-summary { grid-template-columns:repeat(2, minmax(0,1fr)); }.frequency-rule { text-align:left; }.frequency-chart-row { grid-template-columns:110px 1fr 38px; } }
@media print { @page { size:landscape; margin:9mm; } .no-print { display:none !important; }.print-only { display:block; margin-bottom:7mm; font-size:10pt; }.attendance-report-header { margin-bottom:5mm; }.attendance-report-header h1 { font-size:18pt; }.frequency-summary { gap:4mm; margin-bottom:5mm; }.frequency-stat { min-height:0; padding:3mm; }.frequency-stat span { font-size:16pt; }.frequency-visual, .frequency-table-card { box-shadow:none !important; break-inside:avoid; }.frequency-visual { margin-bottom:5mm; }.frequency-chart-row { gap:3mm; }.frequency-table-card th, .frequency-table-card td { padding:2.2mm 2.5mm; font-size:8pt; }.frequency-table-card footer { padding:2.5mm; }.frequency-mode-toggle { display:none !important; } }
</style>

<script>
(function () {
    var table = document.getElementById('frequencyTable');
    var chart = document.getElementById('frequencyChart');
    if (!table || !chart) return;
    var body = table.tBodies[0];
    var rows = Array.prototype.slice.call(body.querySelectorAll('tr[data-frequency]'));
    var mode = 'frequency';
    var labels = {frequency: 'incidents', tardy: 'tardy days', absence: 'absence days'};

    function metric(row) { return Number(row.dataset[mode] || 0); }
    function employeeName(row) { return row.cells[0].querySelector('strong').textContent; }
    function render() {
        var ordered = rows.slice().sort(function (left, right) {
            return metric(right) - metric(left) || employeeName(left).localeCompare(employeeName(right));
        });
        ordered.forEach(function (row) { body.appendChild(row); });
        var leaders = ordered.filter(function (row) { return metric(row) > 0; }).slice(0, 8);
        chart.innerHTML = '';
        if (!leaders.length) {
            var empty = document.createElement('p');
            empty.className = 'frequency-chart-empty';
            empty.textContent = 'No ' + labels[mode] + ' were recorded for the selected period.';
            chart.appendChild(empty);
            return;
        }
        var maximum = Math.max.apply(null, leaders.map(metric));
        leaders.forEach(function (row) {
            var line = document.createElement('div');
            line.className = 'frequency-chart-row' + (mode === 'absence' ? ' absence' : '');
            var label = document.createElement('span'); label.className = 'frequency-chart-label'; label.textContent = employeeName(row);
            var track = document.createElement('div'); track.className = 'frequency-chart-track';
            var bar = document.createElement('div'); bar.className = 'frequency-chart-bar'; bar.style.width = ((metric(row) / maximum) * 100) + '%'; track.appendChild(bar);
            var value = document.createElement('span'); value.className = 'frequency-chart-value'; value.textContent = metric(row) + ' ' + labels[mode];
            line.appendChild(label); line.appendChild(track); line.appendChild(value); chart.appendChild(line);
        });
    }
    document.querySelectorAll('[data-sort]').forEach(function (button) {
        button.addEventListener('click', function () {
            mode = button.dataset.sort;
            document.querySelectorAll('[data-sort]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
            render();
        });
    });
    render();
}());
</script>
