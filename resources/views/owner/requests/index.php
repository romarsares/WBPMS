<?php

use Wbpms\Http\View\Formatter;

/**
 * View: owner/requests/index  (GET /owner/requests)
 *
 * Variables:
 *   list<array<string,mixed>> $pending  — rows from RequestService::ownerPendingList()
 *   list<array<string,mixed>> $actioned — rows from RequestService::ownerActionedList()
 *   string $base, $csrf — injected by ViewRenderer
 */

$pending  ??= [];
$actioned ??= [];
$base     ??= '';

$typeIcons = [
    'Leave'       => '🏖',
    'Overtime'    => '⏰',
    'CashAdvance' => '💵',
];
?>

<div class="page-head">
    <div>
        <h1>Request Approvals</h1>
        <p>Review and act on employee requests, and see your full approval history.</p>
    </div>
</div>

<?php if ($pending === []): ?>
<div class="card" style="padding:2.5rem;text-align:center;color:#6b7280;margin-bottom:1.5rem">
    <p style="margin:0;font-size:1rem">No requests are awaiting your approval right now.</p>
</div>
<?php else: ?>

<!-- Summary -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <?php
    $leaves = count(array_filter($pending, fn($r) => $r['type_name'] === 'Leave'));
    $ot     = count(array_filter($pending, fn($r) => $r['type_name'] === 'Overtime'));
    $ca     = count(array_filter($pending, fn($r) => $r['type_name'] === 'CashAdvance'));
    ?>
    <div class="stat-card">
        <span class="stat-value"><?= count($pending) ?></span>
        <span class="stat-label">Pending Your Approval</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #6366f1">
        <span class="stat-value"><?= $leaves ?></span>
        <span class="stat-label">Leave Requests</span>
    </div>
    <div class="stat-card" style="border-left:4px solid #f59e0b">
        <span class="stat-value"><?= $ot + $ca ?></span>
        <span class="stat-label">OT &amp; Cash Advances</span>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;margin-bottom:2rem">
    <div style="padding:14px 20px;border-bottom:1px solid var(--line)">
        <strong>Awaiting Your Approval</strong>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Type</th>
                <th>Submitted</th>
                <th>HR Pre-approved</th>
                <th>Reason</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $r): ?>
        <tr>
            <td style="color:#9ca3af;font-size:.8rem"><?= (int) $r['request_id'] ?></td>
            <td>
                <strong><?= Formatter::escape((string) $r['employee_name']) ?></strong><br>
                <small style="color:#6b7280"><?= Formatter::escape((string) $r['employee_number']) ?></small>
            </td>
            <td>
                <?= $typeIcons[$r['type_name']] ?? '' ?>
                <?= Formatter::escape((string) $r['type_name']) ?>
            </td>
            <td style="white-space:nowrap;font-size:.85rem"><?= Formatter::escape((string) $r['submitted_at']) ?></td>
            <td style="white-space:nowrap;font-size:.85rem;color:#6366f1"><?= Formatter::escape((string) ($r['hr_reviewed_at'] ?? '—')) ?></td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.85rem">
                <?= Formatter::escape((string) $r['reason']) ?>
            </td>
            <td style="white-space:nowrap">
                <a href="<?= $base ?>/owner/requests/<?= (int) $r['request_id'] ?>"
                   class="btn btn-secondary" style="font-size:.8rem;padding:.3rem .75rem">
                    Review →
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<!-- ================================================================
     ACTIONED HISTORY — all Approved and Cancelled requests
     ================================================================ -->
<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
        <strong>Approval History</strong>
        <span style="font-size:.8rem;color:#6b7280"><?= count($actioned) ?> record(s)</span>
    </div>
    <?php if ($actioned === []): ?>
    <div style="padding:2rem;text-align:center;color:#9ca3af;font-size:.875rem">
        No actioned requests yet.
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Type</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actioned</th>
                <th>Note</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($actioned as $r):
            $isApproved = $r['status'] === 'Approved';
            $statusStyle = $isApproved
                ? 'background:#d1fae5;color:#065f46'
                : 'background:#f3f4f6;color:#374151';
        ?>
        <tr>
            <td style="color:#9ca3af;font-size:.8rem"><?= (int) $r['request_id'] ?></td>
            <td>
                <strong><?= Formatter::escape((string) $r['employee_name']) ?></strong><br>
                <small style="color:#6b7280"><?= Formatter::escape((string) $r['employee_number']) ?></small>
            </td>
            <td>
                <?= $typeIcons[$r['type_name']] ?? '' ?>
                <?= Formatter::escape((string) $r['type_name']) ?>
            </td>
            <td>
                <span style="<?= $statusStyle ?>;padding:2px 8px;border-radius:9999px;font-size:.75rem;font-weight:600">
                    <?= Formatter::escape((string) $r['status']) ?>
                </span>
            </td>
            <td style="white-space:nowrap;font-size:.85rem"><?= Formatter::escape((string) $r['submitted_at']) ?></td>
            <td style="white-space:nowrap;font-size:.85rem;color:#6b7280">
                <?= Formatter::escape((string) ($r['owner_reviewed_at'] ?? '—')) ?>
            </td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem;color:#6b7280">
                <?= $r['owner_notes'] ? Formatter::escape((string) $r['owner_notes']) : '—' ?>
            </td>
            <td style="white-space:nowrap">
                <a href="<?= $base ?>/owner/requests/<?= (int) $r['request_id'] ?>"
                   style="font-size:.8rem;color:#6366f1">
                    View →
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
