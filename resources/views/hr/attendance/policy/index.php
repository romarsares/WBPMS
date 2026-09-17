<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/attendance/policy/index
 *
 * Variables injected by AttendancePolicyController::index() + ViewRenderer:
 *   list<array<string,mixed>> $rows      Flag queue rows
 *   array<string,int>         $counts    Counts by status + Total
 *   array{status:string,type:string,employee_number:string} $filters
 *   string $defaultFrom, $defaultTo      Date inputs for the evaluation form
 *   string $base, $csrf                  Injected by ViewRenderer
 */

$rows          ??= [];
$counts        ??= [];
$filters       ??= [];
$defaultFrom   ??= '';
$defaultTo     ??= '';
$base          ??= '';
$csrf          ??= '';

$typeColor = static function (string $type): string {
    return match ($type) {
        'ConsecutiveLate' => '#f59e0b',
        'TardinessMemoCap' => '#ef4444',
        'TwoWeekAbsence'   => '#6366f1',
        'ConsecutiveAWOL'  => '#0ea5e9',
        default            => '#6b7280',
    };
};

$statusColor = static function (string $status): string {
    return match ($status) {
        'Pending'  => '#f59e0b',
        'Reviewed' => '#0ea5e9',
        'Closed'   => '#6b7280',
        default    => '#6b7280',
    };
};

$flagTypes = ['ConsecutiveLate', 'TardinessMemoCap', 'TwoWeekAbsence', 'ConsecutiveAWOL'];
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem">
    <div>
        <h1 style="margin:0 0 .25rem">Attendance Policy Flags</h1>
        <p style="margin:0;color:#6b7280;font-size:.875rem">
            HR-review alerts for consecutive lates, tardiness memoranda, and AWOL absences. The system never disciplines employees automatically.
        </p>
    </div>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int) ($counts['Total'] ?? 0) ?></span><span class="stat-label">Total</span></div>
    <div class="stat-card" style="border-left:4px solid #f59e0b"><span class="stat-value"><?= (int) ($counts['Pending'] ?? 0) ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card" style="border-left:4px solid #0ea5e9"><span class="stat-value"><?= (int) ($counts['Reviewed'] ?? 0) ?></span><span class="stat-label">Reviewed</span></div>
    <div class="stat-card" style="border-left:4px solid #6b7280"><span class="stat-value"><?= (int) ($counts['Closed'] ?? 0) ?></span><span class="stat-label">Closed</span></div>
</div>

<!-- Run evaluation -->
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:.9rem 1rem;margin-bottom:1.25rem">
    <form method="POST" action="<?= $base ?>/hr/attendance/policy/flags/evaluate"
          style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
        <div>
            <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">From</label>
            <input type="date" name="from" value="<?= Formatter::escape($defaultFrom) ?>"
                   style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
        </div>
        <div>
            <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">To</label>
            <input type="date" name="to" value="<?= Formatter::escape($defaultTo) ?>"
                   style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
        </div>
        <button type="submit" class="btn btn-primary" style="font-size:.875rem;padding:.5rem .9rem">
            Run policy evaluation
        </button>
        <span style="font-size:.78rem;color:#6b7280">
            Detects 3 consecutive lates, the 3rd tardiness memorandum, 2 weeks without reporting, and 3 consecutive unexcused absences.
        </span>
    </form>
</div>

<!-- Filters -->
<form method="GET" action="<?= $base ?>/hr/attendance/policy/flags"
      style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.25rem">
    <div>
        <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">Status</label>
        <select name="status" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
            <option value="">All</option>
            <?php foreach (['Pending', 'Reviewed', 'Closed'] as $s): ?>
            <option value="<?= Formatter::escape($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= Formatter::escape($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">Type</label>
        <select name="type" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
            <option value="">All types</option>
            <?php foreach ($flagTypes as $t): ?>
            <option value="<?= Formatter::escape($t) ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= Formatter::escape($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.78rem;font-weight:500;color:#374151;display:block;margin-bottom:.2rem">Employee No.</label>
        <input type="text" name="employee_number" value="<?= Formatter::escape((string) ($filters['employee_number'] ?? '')) ?>"
               placeholder="e.g. E-001" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
    </div>
    <button type="submit" class="btn btn-secondary" style="font-size:.875rem;padding:.5rem .9rem">Filter</button>
</form>

<div class="table-card" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;min-width:960px">
        <thead>
            <tr style="background:#f9fafb">
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Flag</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Employee</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Type</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Triggered</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Evidence</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Status</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Created</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">Employee Notice</th>
                <th style="padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:600;color:#6b7280;border-bottom:1px solid #e5e7eb">HR Review</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr>
                <td colspan="9" style="padding:2rem;text-align:center;color:#6b7280;font-size:.875rem">
                    No policy flags found. Run the policy evaluation above to detect late/memorandum/AWOL thresholds.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:.65rem 1rem;font-size:.85rem;color:#9ca3af">#<?= (int) $r['flag_id'] ?></td>
                <td style="padding:.65rem 1rem">
                    <strong style="font-size:.875rem"><?= Formatter::escape((string) $r['employee_name']) ?></strong><br>
                    <span style="font-size:.75rem;color:#6b7280"><?= Formatter::escape((string) $r['employee_number']) ?> · <?= Formatter::escape((string) ($r['position'] ?? '')) ?></span>
                </td>
                <td style="padding:.65rem 1rem">
                    <span style="background:<?= $typeColor((string) $r['flag_type']) ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem;white-space:nowrap">
                        <?= Formatter::escape((string) $r['flag_type']) ?>
                    </span>
                </td>
                <td style="padding:.65rem 1rem;font-size:.85rem;white-space:nowrap"><?= Formatter::escape((string) $r['triggering_date']) ?></td>
                <td style="padding:.65rem 1rem;font-size:.8rem;color:#374151;max-width:260px;line-height:1.4">
                    <?php $evidence = $r['late_dates'] ?? $r['absence_dates'] ?? null; ?>
                    <?php if ($evidence !== null && $evidence !== ''): ?>
                    <button type="button" class="btn btn-secondary" style="font-size:.78rem;padding:.3rem .6rem"
                            onclick="document.getElementById('evidence-<?= (int) $r['flag_id'] ?>').showModal()">
                        View evidence
                    </button>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="padding:.65rem 1rem">
                    <span style="background:<?= $statusColor((string) $r['status']) ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem">
                        <?= Formatter::escape((string) $r['status']) ?>
                    </span>
                </td>
                <td style="padding:.65rem 1rem;font-size:.85rem;white-space:nowrap"><?= Formatter::escape((string) $r['created_at']) ?></td>
                <td style="padding:.65rem 1rem;font-size:.8rem;white-space:nowrap">
                    <?php if (($r['notice_id'] ?? null) === null): ?>
                        <span style="color:#6b7280">Not issued</span>
                    <?php elseif (($r['notice_acknowledged_at'] ?? null) === null): ?>
                        <span class="badge badge-yellow">Awaiting acknowledgment</span>
                    <?php else: ?>
                        <span class="badge badge-gray">Acknowledged</span>
                    <?php endif; ?>
                </td>
                <td style="padding:.65rem 1rem;min-width:280px">
                    <details>
                        <summary style="font-size:.8rem;color:#2563eb;cursor:pointer">
                            <?= ($r['status'] === 'Pending') ? 'Review now' : 'Edit review' ?>
                        </summary>
                        <div style="margin-top:.4rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:.6rem .7rem">
                            <?php if (($r['status'] ?? '') !== 'Pending' && ($r['reviewed_at'] ?? null) !== null): ?>
                            <div style="font-size:.78rem;color:#374151;margin-bottom:.4rem">
                                <strong>Reviewed:</strong> <?= Formatter::escape((string) $r['reviewed_at']) ?>
                                <?php if (($r['action_taken'] ?? '') !== ''): ?>
                                · <strong>Action:</strong> <?= Formatter::escape((string) $r['action_taken']) ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <form method="POST" action="<?= $base ?>/hr/attendance/policy/flags/<?= (int) $r['flag_id'] ?>/review"
                                  style="display:flex;flex-direction:column;gap:.35rem">
                                <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                                <select name="status" style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:4px;font-size:.8rem">
                                    <?php foreach (['Reviewed', 'Closed'] as $s): ?>
                                    <option value="<?= Formatter::escape($s) ?>" <?= ($r['status'] ?? '') === $s ? 'selected' : '' ?>><?= Formatter::escape($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="action_taken" value="<?= Formatter::escape((string) ($r['action_taken'] ?? '')) ?>"
                                       placeholder="Action taken (e.g. memorandum issued, warning letter)"
                                       maxlength="255" style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:4px;font-size:.8rem">
                                <textarea name="notes" rows="2" maxlength="4000"
                                          style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:4px;font-size:.8rem"><?= Formatter::escape((string) ($r['notes'] ?? '')) ?></textarea>
                                <?php if (($r['notice_id'] ?? null) === null): ?>
                                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:#374151">
                                        <input type="checkbox" name="issue_employee_notice" value="1">
                                        Issue an employee-facing notice
                                    </label>
                                    <input type="text" name="notice_title" maxlength="160"
                                           value="Attendance policy notice"
                                           placeholder="Notice title"
                                           style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:4px;font-size:.8rem">
                                    <textarea name="notice_body" rows="3" maxlength="4000"
                                              placeholder="Message the employee will see in their portal. Include the required next step."></textarea>
                                <?php elseif (($r['notice_acknowledged_at'] ?? null) === null): ?>
                                    <div style="font-size:.78rem;color:#a16207">Notice issued <?= Formatter::escape((string) $r['notice_issued_at']) ?>; awaiting employee acknowledgment.</div>
                                <?php else: ?>
                                    <div style="font-size:.78rem;color:#4b5563">Employee acknowledged <?= Formatter::escape((string) $r['notice_acknowledged_at']) ?>.</div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem;padding:.3rem .6rem">Save review</button>
                            </form>
                        </div>
                    </details>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($rows as $r): ?>
    <?php $evidence = $r['late_dates'] ?? $r['absence_dates'] ?? null; ?>
    <?php if ($evidence === null || $evidence === '') { continue; } ?>
    <?php $isLateEvidence = in_array((string) $r['flag_type'], ['ConsecutiveLate', 'TardinessMemoCap'], true); ?>
    <dialog id="evidence-<?= (int) $r['flag_id'] ?>" class="policy-evidence-modal">
        <div class="policy-evidence-modal__header">
            <div>
                <h2><?= $isLateEvidence ? 'Late Attendance Evidence' : 'Absence Evidence' ?></h2>
                <p><?= Formatter::escape((string) $r['employee_name']) ?> · Flag #<?= (int) $r['flag_id'] ?></p>
            </div>
            <form method="dialog"><button type="submit" class="btn btn-secondary" aria-label="Close evidence">Close</button></form>
        </div>
        <p class="policy-evidence-modal__intro">
            <?= $isLateEvidence
                ? 'Time-in and chargeable late minutes for the attendance dates that triggered this flag.'
                : 'Unexcused no-reporting dates that triggered this flag.' ?>
        </p>
        <ul class="policy-evidence-modal__list">
            <?php foreach (explode(', ', (string) $evidence) as $date): ?>
            <li><?= Formatter::escape($date) ?></li>
            <?php endforeach; ?>
        </ul>
    </dialog>
<?php endforeach; ?>

<style>
.policy-evidence-modal {
    position:fixed; inset:0; margin:auto;
    width:min(560px, calc(100vw - 2rem)); max-height:calc(100dvh - 2rem);
    overflow:auto; border:0; border-radius:10px;
    padding:0; box-shadow:0 20px 50px rgba(15,23,42,.25);
}
.policy-evidence-modal::backdrop { background:rgba(15,23,42,.48); }
.policy-evidence-modal__header {
    display:flex; justify-content:space-between; gap:1rem; align-items:flex-start;
    padding:1.1rem 1.25rem; border-bottom:1px solid #e5e7eb;
}
.policy-evidence-modal__header h2 { margin:0; font-size:1.05rem; }
.policy-evidence-modal__header p { margin:.25rem 0 0; color:#64748b; font-size:.85rem; }
.policy-evidence-modal__intro { margin:1rem 1.25rem .5rem; color:#475569; font-size:.875rem; }
.policy-evidence-modal__list { margin:.5rem 1.25rem 1.25rem; padding-left:1.25rem; line-height:1.8; font-size:.9rem; }
</style>
