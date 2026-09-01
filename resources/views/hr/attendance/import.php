<?php
/**
 * View: hr/attendance/import  (GET /hr/attendance/import)
 * Variables: $batches (recent import batches), $errors
 */
$errors  ??= [];
$batches ??= [];
?>
<div class="page-head">
    <div>
        <h1>Import Attendance Workbook</h1>
        <p>Upload a biometric device .xls daily-log export. <a href="/hr/attendance">← Back to Attendance</a></p>
    </div>
</div>

<?php if ($errors !== []): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b">
    <?php foreach ($errors as $e): ?><p style="margin:.1rem 0"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

    <!-- Upload form -->
    <div class="card">
        <h3 style="margin:0 0 1rem">Upload Workbook</h3>
        <form method="post" action="/hr/attendance/import" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-group" style="margin-bottom:1rem">
                <label for="attendance_file" style="display:block;font-weight:500;margin-bottom:.25rem">
                    Biometric .xls file <span style="color:#ef4444">*</span>
                </label>
                <input type="file" id="attendance_file" name="attendance_file"
                       accept=".xls,application/vnd.ms-excel" required class="form-control">
                <small style="color:#6b7280;display:block;margin-top:.25rem">
                    Only .xls files exported from the biometric device (max 10 MiB).
                    XLSX files are not accepted.
                </small>
            </div>

            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.75rem;margin-bottom:1rem;font-size:.875rem;color:#92400e">
                <strong>Note:</strong> The system will validate the file signature, detect duplicates by checksum, and queue the import.
                Matching and timesheet generation run automatically.
            </div>

            <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
        </form>
    </div>

    <!-- Import batch history -->
    <div class="card">
        <h3 style="margin:0 0 1rem">Recent Imports</h3>
        <?php if ($batches === []): ?>
        <p style="color:#6b7280;font-size:.875rem">No imports yet.</p>
        <?php else: ?>
        <table class="data-table" style="font-size:.875rem">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Imported</th>
                    <th>Matched</th>
                    <th>Unmatched</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($batches as $b): ?>
            <tr>
                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars((string)$b['source_filename']) ?>">
                    <?= htmlspecialchars((string)$b['source_filename']) ?>
                </td>
                <td style="font-size:.75rem"><?= htmlspecialchars((string)$b['imported_at']) ?></td>
                <td style="text-align:center"><?= (int)$b['matched_count'] ?></td>
                <td style="text-align:center"><?= (int)$b['unmatched_count'] ?></td>
                <td>
                    <?php
                    $c = match($b['status']) { 'Complete'=>'#10b981', 'Failed'=>'#ef4444', default=>'#f59e0b' };
                    ?>
                    <span style="background:<?= $c ?>;color:#fff;padding:1px 7px;border-radius:9999px;font-size:.7rem"><?= htmlspecialchars((string)$b['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
