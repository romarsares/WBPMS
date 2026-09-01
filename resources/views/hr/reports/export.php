<?php
/**
 * View: hr/reports/export  (GET /hr/reports/export)
 * Variables: $type, $errors
 */
$reportLabels = [
    'payroll'       => 'Payroll Summary',
    'attendance'    => 'Attendance Report',
    'requests'      => 'Leave / Request Report',
    'contributions' => 'Contributions Report',
    '13th_month'    => '13th Month Pay',
    'employees'     => 'Employee List',
];
$label = $reportLabels[$type] ?? ($type !== '' ? htmlspecialchars($type) : 'Report');
?>
<div class="page-head">
    <div>
        <h1><?= htmlspecialchars($label) ?></h1>
        <p><a href="/hr/reports">← Back to Reports</a></p>
    </div>
</div>

<div class="card" style="max-width:520px">
    <form method="get" action="/hr/reports/export">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

        <div class="form-group" style="margin-bottom:1rem">
            <label for="period_start" style="display:block;font-weight:500;margin-bottom:.25rem">Period Start</label>
            <input type="date" id="period_start" name="period_start" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:1rem">
            <label for="period_end" style="display:block;font-weight:500;margin-bottom:.25rem">Period End</label>
            <input type="date" id="period_end" name="period_end" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:1.25rem">
            <label for="format" style="display:block;font-weight:500;margin-bottom:.25rem">Format</label>
            <select id="format" name="format" class="form-control">
                <option value="html">Print / HTML</option>
                <option value="csv">CSV Download</option>
            </select>
        </div>

        <div style="background:#f3f4f6;border-radius:6px;padding:.75rem;margin-bottom:1rem;font-size:.875rem;color:#6b7280">
            Full report generation (PDF/CSV export) is implemented in the Reports module (task 11). This form currently previews report parameters.
        </div>

        <button type="submit" class="btn btn-primary" disabled style="opacity:.6;cursor:not-allowed">
            Generate Report (coming soon)
        </button>
        <a href="/hr/reports" class="btn btn-secondary" style="margin-left:.5rem">Cancel</a>
    </form>
</div>
