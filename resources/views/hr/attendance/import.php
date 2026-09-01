<?php
declare(strict_types=1);
use Wbpms\Http\View\Formatter;
/**
 * View: hr/attendance/import  (GET + POST /hr/attendance/import)
 *
 * Variables injected by AttendanceController:
 *   array  $batches  — recent attendance_import_batch rows
 *   array  $errors   — validation / parse errors from last upload attempt
 *   array  $devices  — active biometric_device rows (device_id, device_code, device_name)
 *   string $base     — APP_BASE_URL without trailing slash
 *   string $csrf     — CSRF token
 */
$errors  ??= [];
$batches ??= [];
$devices ??= [];
$base    ??= '';

// Repopulate POST values if the form was re-rendered with errors
$postDeviceId    = (int) ($_POST['device_id']    ?? 0);
$postYear        = (int) ($_POST['source_year']  ?? (int) date('Y'));
$postMonth       = (int) ($_POST['source_month'] ?? (int) date('n'));

$months = [
    1=>'January', 2=>'February', 3=>'March',    4=>'April',
    5=>'May',     6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October',11=>'November',12=>'December',
];
?>
<div class="page-head">
    <div>
        <h1>Import Attendance Workbook</h1>
        <p>Upload a biometric device .xls daily-log export.
            <a href="<?= $base ?>/hr/attendance">← Back to Attendance</a>
        </p>
    </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-error" role="alert">
    <?php foreach ($errors as $e): ?>
        <p style="margin:.15rem 0"><?= Formatter::escape($e) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:1.5rem;align-items:start">

    <!-- ── Upload form ─────────────────────────────────────────── -->
    <div class="card">
        <h3 style="margin:0 0 1.2rem;font-size:16px">Upload Workbook</h3>

        <form method="POST"
              action="<?= $base ?>/hr/attendance/import"
              enctype="multipart/form-data"
              novalidate>
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

            <!-- Device selector -->
            <div class="form-group">
                <label for="device_id">Biometric Device <span style="color:var(--bad)">*</span></label>
                <?php if ($devices === []): ?>
                    <p class="field-error">No active biometric devices found. Ask the administrator to register a device first.</p>
                    <input type="hidden" name="device_id" value="0">
                <?php else: ?>
                    <select id="device_id" name="device_id" class="form-control" required>
                        <option value="">— Select device —</option>
                        <?php foreach ($devices as $d): ?>
                            <option value="<?= (int) $d['device_id'] ?>"
                                <?= $postDeviceId === (int) $d['device_id'] ? 'selected' : '' ?>>
                                <?= Formatter::escape($d['device_name'] ?? $d['device_code']) ?>
                                (<?= Formatter::escape($d['device_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help">Choose the device this export came from.</span>
                <?php endif; ?>
            </div>

            <!-- Source period: year + month -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label for="source_year">Year <span style="color:var(--bad)">*</span></label>
                    <input type="number" id="source_year" name="source_year"
                           class="form-control"
                           value="<?= $postYear ?>"
                           min="2020" max="2099" required>
                </div>
                <div class="form-group">
                    <label for="source_month">Month <span style="color:var(--bad)">*</span></label>
                    <select id="source_month" name="source_month" class="form-control" required>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $postMonth === $num ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- File picker -->
            <div class="form-group">
                <label for="attendance_file">
                    Biometric .xls file <span style="color:var(--bad)">*</span>
                </label>
                <input type="file"
                       id="attendance_file"
                       name="attendance_file"
                       accept=".xls,application/vnd.ms-excel"
                       required
                       class="form-control">
                <span class="help">
                    Only .xls files exported from the biometric device (max 10 MiB).
                    .xlsx files are NOT accepted.
                </span>
            </div>

            <div class="alert info" style="font-size:13px;margin-bottom:1rem">
                The system validates the file signature, checks for duplicate imports by
                SHA-256 checksum, matches enrollment codes to employees, and generates
                daily timesheets in one atomic transaction.
            </div>

            <button type="submit" class="btn btn-primary">
                ↑ Upload &amp; Import
            </button>
        </form>
    </div>

    <!-- ── Recent import batches ───────────────────────────────── -->
    <div class="card">
        <h3 style="margin:0 0 1.2rem;font-size:16px">Recent Imports</h3>

        <?php if ($batches === []): ?>
            <p class="muted">No imports yet.</p>
        <?php else: ?>
        <div class="table-wrap">
        <table style="font-size:13px;width:100%;border-collapse:collapse">
            <thead>
                <tr>
                    <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap">File</th>
                    <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap">Period</th>
                    <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap">Uploaded</th>
                    <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--line)">Parsed</th>
                    <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--line)">Matched</th>
                    <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--line)">Unmatched</th>
                    <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--line)">Dupes</th>
                    <th style="padding:8px 10px;border-bottom:1px solid var(--line)">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($batches as $b):
                $statusClass = match((string)($b['status'] ?? '')) {
                    'Completed'  => 'badge-green',
                    'Rejected'   => 'badge-red',
                    default      => 'badge-yellow',
                };
                $period = sprintf('%s %s',
                    $months[(int)($b['source_month'] ?? 0)] ?? '?',
                    (int)($b['source_year'] ?? 0)
                );
            ?>
            <tr>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);
                            max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= Formatter::escape((string)($b['file_name'] ?? '')) ?>">
                    <?= Formatter::escape((string)($b['file_name'] ?? '—')) ?>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap">
                    <?= Formatter::escape($period) ?>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap;font-size:12px">
                    <?= Formatter::escape((string)($b['uploaded_at'] ?? '')) ?><br>
                    <span class="muted"><?= Formatter::escape((string)($b['uploaded_by'] ?? '')) ?></span>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);text-align:right">
                    <?= (int)($b['records_parsed'] ?? 0) ?>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);text-align:right;color:var(--ok-text)">
                    <?= (int)($b['records_matched'] ?? 0) ?>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);text-align:right;color:var(--wait-text)">
                    <?= (int)($b['records_unmatched'] ?? 0) ?>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line);text-align:right;color:var(--muted)">
                    <?= (int)($b['duplicates_skipped'] ?? 0) ?>
                </td>
                <td style="padding:8px 10px;border-bottom:1px solid var(--line)">
                    <span class="badge <?= $statusClass ?>">
                        <?= Formatter::escape((string)($b['status'] ?? '')) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if (!empty($batches)): ?>
        <p style="font-size:12px;color:var(--muted);margin-top:10px">
            Showing <?= count($batches) ?> most recent import(s).
            Incomplete days and multi-punch days are flagged for HR review in the
            <a href="<?= $base ?>/hr/attendance">Attendance list</a>.
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
