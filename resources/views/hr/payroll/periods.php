<?php
declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/**
 * View: hr/payroll/periods  (GET + POST /hr/payroll/periods)
 *
 * Variables:
 *   array       $periods      — rows from PayrollService::listPeriods()
 *   array       $errors       — validation errors from last POST
 *   string|null $period_start — repopulated field value on error
 */
$periods      ??= [];
$errors       ??= [];
$period_start ??= '';

$statusBadge = static function (string $s): string {
    $map = [
        'Open'             => 'badge-green',
        'AttendanceClosed' => 'badge-yellow',
        'Disbursed'        => 'badge-blue',
        'Closed'           => 'badge-gray',
    ];
    return '<span class="badge ' . ($map[$s] ?? 'badge-gray') . '">' . htmlspecialchars($s) . '</span>';
};
?>
<div class="page-head">
    <div>
        <h1>Payroll Periods</h1>
        <p>Weekly pay windows (Friday → Thursday). <a href="<?= $base ?>/hr/payroll">← Back to Payroll</a></p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;align-items:start">

    <!-- ── Create form ─────────────────────────────────────── -->
    <div class="card">
        <h3 style="margin:0 0 1.1rem;font-size:15px">Add New Period</h3>

        <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom:1rem">
            <?php foreach ($errors as $e): ?>
                <p style="margin:.1rem 0"><?= Formatter::escape($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $base ?>/hr/payroll/periods" novalidate>
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

            <div class="form-group">
                <label for="period_start">
                    Period Start (Friday) <span style="color:var(--bad)">*</span>
                </label>
                <input type="date"
                       id="period_start"
                       name="period_start"
                       class="form-control <?= $errors !== [] ? 'is-invalid' : '' ?>"
                       value="<?= Formatter::escape($period_start) ?>"
                       required>
                <span class="help">Must be a Friday. End date and pay date are calculated automatically.</span>
            </div>

            <!-- Preview auto-calculated values via JS -->
            <div id="period-preview" style="display:none;background:var(--surface-2);border:1px solid var(--line);
                 border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:1rem;font-size:13px">
                <div style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px">
                    <span style="color:var(--muted)">Period end:</span>
                    <strong id="preview-end">—</strong>
                    <span style="color:var(--muted)">Pay date:</span>
                    <strong id="preview-pay">—</strong>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                Create Period
            </button>
        </form>
    </div>

    <!-- ── Period list ──────────────────────────────────────── -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px 12px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:15px">All Periods</h3>
            <span class="muted" style="font-size:13px"><?= count($periods) ?> period(s)</span>
        </div>

        <?php if ($periods === []): ?>
            <p class="muted" style="padding:2rem;text-align:center">
                No periods yet. Create one using the form.
            </p>
        <?php else: ?>
        <div class="table-wrap">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead>
                <tr>
                    <th style="padding:10px 16px;text-align:left;border-bottom:1px solid var(--line);
                                background:var(--surface-2);font-size:11.5px;text-transform:uppercase;
                                letter-spacing:.05em;color:var(--muted)">Period Start</th>
                    <th style="padding:10px 16px;text-align:left;border-bottom:1px solid var(--line);
                                background:var(--surface-2);font-size:11.5px;text-transform:uppercase;
                                letter-spacing:.05em;color:var(--muted)">Period End</th>
                    <th style="padding:10px 16px;text-align:left;border-bottom:1px solid var(--line);
                                background:var(--surface-2);font-size:11.5px;text-transform:uppercase;
                                letter-spacing:.05em;color:var(--muted)">Pay Date</th>
                    <th style="padding:10px 16px;text-align:center;border-bottom:1px solid var(--line);
                                background:var(--surface-2);font-size:11.5px;text-transform:uppercase;
                                letter-spacing:.05em;color:var(--muted)">Runs</th>
                    <th style="padding:10px 16px;text-align:left;border-bottom:1px solid var(--line);
                                background:var(--surface-2);font-size:11.5px;text-transform:uppercase;
                                letter-spacing:.05em;color:var(--muted)">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periods as $p):
                $isOpen = $p['status'] === 'Open';
            ?>
            <tr style="<?= !$isOpen ? 'opacity:.7' : '' ?>">
                <td style="padding:11px 16px;border-bottom:1px solid var(--line);font-weight:600">
                    <?= Formatter::date($p['period_start']) ?>
                    <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">
                        <?= (new DateTimeImmutable($p['period_start']))->format('D') ?>
                    </span>
                </td>
                <td style="padding:11px 16px;border-bottom:1px solid var(--line)">
                    <?= Formatter::date($p['period_end']) ?>
                    <span style="font-size:11px;color:var(--muted);margin-left:4px">
                        <?= (new DateTimeImmutable($p['period_end']))->format('D') ?>
                    </span>
                </td>
                <td style="padding:11px 16px;border-bottom:1px solid var(--line);color:var(--ok-text);font-weight:600">
                    <?= Formatter::date($p['pay_date']) ?>
                </td>
                <td style="padding:11px 16px;border-bottom:1px solid var(--line);text-align:center">
                    <?php if ((int)$p['run_count'] > 0): ?>
                        <a href="<?= $base ?>/hr/payroll" style="font-weight:600;color:var(--accent)">
                            <?= (int)$p['run_count'] ?>
                        </a>
                    <?php else: ?>
                        <span class="muted">0</span>
                    <?php endif; ?>
                </td>
                <td style="padding:11px 16px;border-bottom:1px solid var(--line)">
                    <?= $statusBadge($p['status']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
    var input = document.getElementById('period_start');
    var preview = document.getElementById('period-preview');
    var previewEnd = document.getElementById('preview-end');
    var previewPay = document.getElementById('preview-pay');

    var DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function fmt(d) {
        return DAYS[d.getDay()] + ', ' + MONTHS[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    function addDays(d, n) {
        var r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }

    input.addEventListener('change', function () {
        var parts = this.value.split('-');
        if (parts.length !== 3) { preview.style.display = 'none'; return; }
        // Parse as local date to avoid timezone shift
        var start = new Date(+parts[0], +parts[1] - 1, +parts[2]);
        if (isNaN(start)) { preview.style.display = 'none'; return; }

        if (start.getDay() !== 5) { // 5 = Friday
            preview.style.display = 'none';
            return;
        }

        var end = addDays(start, 6);
        var pay = addDays(start, 7);
        previewEnd.textContent = fmt(end) + ' (Thu)';
        previewPay.textContent = fmt(pay) + ' (Fri)';
        preview.style.display = 'block';
    });

    // Trigger on load if repopulated from error
    if (input.value) { input.dispatchEvent(new Event('change')); }
}());
</script>
