<?php
/**
 * View: hr/attendance/index  (GET /hr/attendance)
 * Variables: $rows, $total, $complete, $incomplete, $unmatched
 */
$rows ??= [];
?>
<div class="page-head">
    <div>
        <h1>Attendance Management</h1>
        <p>Review employee timesheets and attendance records.</p>
    </div>
    <a href="/hr/attendance/import" class="btn btn-primary">Import Workbook</a>
</div>

<!-- Summary -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int)$total ?></span><span class="stat-label">Total Records</span></div>
    <div class="stat-card" style="border-left:4px solid #10b981"><span class="stat-value"><?= (int)$complete ?></span><span class="stat-label">Complete</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= (int)$incomplete ?></span><span class="stat-label">Incomplete</span></div>
    <div class="stat-card" style="border-left:4px solid #ef4444"><span class="stat-value"><?= (int)$unmatched ?></span><span class="stat-label">Unmatched Punches</span></div>
</div>

<?php if ($incomplete > 0): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;color:#92400e;font-size:.875rem">
    ⚠ <strong><?= (int)$incomplete ?></strong> attendance record(s) are incomplete (missing time-in or time-out). Review and adjust as needed.
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden">
    <?php if ($rows === []): ?>
    <p style="padding:2rem;text-align:center;color:#6b7280">
        No attendance records found. <a href="/hr/attendance/import">Import a workbook →</a>
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Branch</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th style="text-align:right">Worked (min)</th>
                <th style="text-align:right">Late (min)</th>
                <th style="text-align:right">OT (min)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr<?= $r['is_incomplete'] ? ' style="background:#fffbeb"' : '' ?>>
            <td>
                <strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
                <small style="color:#6b7280"><?= htmlspecialchars($r['employee_number']) ?></small>
            </td>
            <td><?= htmlspecialchars((string)($r['branch_name'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string)$r['attendance_date']) ?></td>
            <td><?= htmlspecialchars((string)($r['time_in'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string)($r['time_out'] ?? '—')) ?></td>
            <td style="text-align:right"><?= (int)$r['worked_minutes'] ?></td>
            <td style="text-align:right<?= (int)$r['late_minutes'] > 0 ? ';color:#b45309' : '' ?>"><?= (int)$r['late_minutes'] ?></td>
            <td style="text-align:right<?= (int)$r['overtime_minutes'] > 0 ? ';color:#1d4ed8' : '' ?>"><?= (int)$r['overtime_minutes'] ?></td>
            <td>
                <?php
                $c = ['Complete'=>'#10b981','Approved'=>'#10b981','Incomplete'=>'#f59e0b','ReviewRequired'=>'#ef4444'][$r['status'] ?? ''] ?? '#6b7280';
                ?>
                <span style="background:<?= $c ?>;color:#fff;padding:2px 7px;border-radius:9999px;font-size:.7rem"><?= htmlspecialchars((string)($r['status'] ?? '')) ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
