<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

$rows ??= [];
$summary ??= ['employee_count' => 0, 'attendance_days' => 0, 'worked_minutes' => 0, 'late_minutes' => 0, 'overtime_minutes' => 0];
$backPath ??= '/hr/payroll';
$backLabel ??= 'Back to Payroll';

$formatMinutes = static function (int $minutes): string {
    return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
};
$statusColor = static function (string $status): string {
    return match ($status) {
        'Complete', 'Approved' => '#047857',
        'ReviewRequired' => '#b45309',
        default => '#64748b',
    };
};
?>

<div class="payroll-print-page">
    <header class="payroll-print-header">
        <div>
            <p class="payroll-print-kicker">Payroll supporting document</p>
            <h1>Cut-off Timesheet</h1>
            <p><?= Formatter::escape((string) $run['branch_name']) ?> &middot; <?= Formatter::escape((string) $run['period_label']) ?></p>
        </div>
        <div class="payroll-print-actions no-print">
            <a class="btn btn-secondary" href="<?= $base ?><?= Formatter::escape($backPath) ?>"><?= Formatter::escape($backLabel) ?></a>
            <button class="btn btn-primary" type="button" onclick="window.print()">Print Timesheet</button>
        </div>
    </header>

    <section class="payroll-print-meta">
        <span><b>Pay date</b><?= Formatter::date((string) $run['pay_date']) ?></span>
        <span><b>Payroll status</b><?= Formatter::escape((string) $run['status']) ?></span>
        <span><b>Generated</b><?= Formatter::escape(date('M j, Y g:i A')) ?></span>
    </section>

    <section class="payroll-print-stats">
        <div><strong><?= (int) $summary['employee_count'] ?></strong><span>Employees</span></div>
        <div><strong><?= (int) $summary['attendance_days'] ?></strong><span>Payable attendance days</span></div>
        <div><strong><?= $formatMinutes((int) $summary['worked_minutes']) ?></strong><span>Worked hours</span></div>
        <div><strong><?= (int) $summary['late_minutes'] ?></strong><span>Late minutes</span></div>
        <div><strong><?= (int) $summary['overtime_minutes'] ?></strong><span>Overtime minutes</span></div>
    </section>

    <p class="payroll-print-note">This sheet contains the attendance entries included by the payroll computation: Complete, Approved, or Review Required records with approved import coverage.</p>

    <div class="payroll-print-table-wrap">
        <table class="payroll-print-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Time in</th>
                    <th>Time out</th>
                    <th class="number">Worked</th>
                    <th class="number">Late</th>
                    <th class="number">Undertime</th>
                    <th class="number">Overtime</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                <tr><td colspan="9" class="empty">No payroll employees are available for this cut-off.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= Formatter::escape((string) $row['employee_name']) ?></strong><small><?= Formatter::escape((string) $row['employee_number']) ?></small></td>
                    <?php if ($row['attendance_date'] === null): ?>
                    <td colspan="8" class="muted">No payable attendance entry in this cut-off.</td>
                    <?php else: ?>
                    <td><?= Formatter::date((string) $row['attendance_date']) ?></td>
                    <td><?= Formatter::escape((string) ($row['time_in'] ?? '—')) ?></td>
                    <td><?= Formatter::escape((string) ($row['time_out'] ?? '—')) ?></td>
                    <td class="number"><?= $formatMinutes((int) $row['hours_worked_minutes']) ?></td>
                    <td class="number"><?= (int) $row['late_minutes'] ?></td>
                    <td class="number"><?= (int) $row['undertime_minutes'] ?></td>
                    <td class="number"><?= (int) $row['overtime_minutes'] ?></td>
                    <td><span style="color:<?= $statusColor((string) $row['status']) ?>;font-weight:700"><?= Formatter::escape((string) $row['status']) ?></span></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <footer class="payroll-print-footer">Source: approved biometric attendance records for this payroll cut-off.</footer>
</div>

<style>
.payroll-print-page{max-width:1400px;margin:0 auto}.payroll-print-header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem}.payroll-print-header h1{margin:.15rem 0;font-size:1.55rem}.payroll-print-header p{margin:0;color:#64748b}.payroll-print-kicker{color:#4f46e5!important;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.payroll-print-actions{display:flex;gap:.5rem;flex-wrap:wrap}.payroll-print-meta{display:flex;flex-wrap:wrap;gap:2rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem}.payroll-print-meta span{display:grid;gap:.15rem;font-size:.86rem}.payroll-print-meta b{font-size:.68rem;letter-spacing:.05em;text-transform:uppercase;color:#64748b}.payroll-print-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.payroll-print-stats div{border:1px solid #e2e8f0;border-top:3px solid #4f46e5;border-radius:8px;padding:.75rem;background:#fff}.payroll-print-stats strong{display:block;font-size:1.25rem;color:#0f172a}.payroll-print-stats span{font-size:.73rem;color:#64748b}.payroll-print-note{font-size:.8rem;color:#475569;margin:0 0 .8rem}.payroll-print-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:8px}.payroll-print-table{width:100%;border-collapse:collapse;font-size:.82rem}.payroll-print-table th{background:#f8fafc;color:#475569;padding:.6rem .7rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}.payroll-print-table td{padding:.58rem .7rem;border-top:1px solid #edf2f7;vertical-align:middle}.payroll-print-table td small{display:block;color:#64748b;font-size:.72rem;margin-top:.1rem}.payroll-print-table .number{text-align:right;font-variant-numeric:tabular-nums}.payroll-print-table .muted,.payroll-print-table .empty{color:#64748b}.payroll-print-table .empty{text-align:center;padding:2rem}.payroll-print-footer{color:#64748b;font-size:.74rem;margin-top:.7rem}
@media(max-width:800px){.payroll-print-header{flex-direction:column}.payroll-print-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media print{@page{size:landscape;margin:8mm}.no-print,.sidebar,.topbar,.mobile-toggle,.overlay{display:none!important}.app,.main,.content{display:block!important;margin:0!important;padding:0!important;width:auto!important}.payroll-print-page{max-width:none}.payroll-print-header{margin-bottom:4mm}.payroll-print-header h1{font-size:17pt}.payroll-print-meta{padding:2.5mm;margin-bottom:4mm}.payroll-print-stats{gap:3mm;margin-bottom:4mm}.payroll-print-stats div{padding:2.5mm}.payroll-print-table-wrap{overflow:visible;border:0}.payroll-print-table{font-size:8pt}.payroll-print-table th,.payroll-print-table td{padding:2mm}.payroll-print-table thead{display:table-header-group}.payroll-print-table tr{break-inside:avoid}}
</style>
