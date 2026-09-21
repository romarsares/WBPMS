<?php

use Wbpms\Http\View\Formatter;

/**
 * View: hr/requests/show
 *
 * Variables:
 *   array<string,mixed>  $request — full row from RequestService::findOrFail()
 *   array<string,string> $errors
 *   string $base, $csrf — injected by ViewRenderer
 */

$request ??= [];
$errors  ??= [];
$base    ??= '';
$csrf    ??= '';

$status    = (string) ($request['status'] ?? '');
$typeName  = (string) ($request['type_name'] ?? '');

$statusColors = [
    'Pending'    => '#f59e0b',
    'HRApproved' => '#6366f1',
    'Approved'   => '#10b981',
    'Returned'   => '#f97316',
    'Cancelled'  => '#6b7280',
];
$statusLabels = [
    'Pending'    => 'Pending',
    'HRApproved' => 'Awaiting Owner Approval',
    'Approved'   => 'Approved',
    'Returned'   => 'Returned to HR',
    'Cancelled'  => 'Cancelled',
];
$statusColor = $statusColors[$status] ?? '#6b7280';
$statusLabel = $statusLabels[$status] ?? $status;
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
    <div>
        <h1 style="margin:0 0 .2rem">Request #<?= (int) $request['request_id'] ?></h1>
        <p style="margin:0;font-size:.875rem">
            <a href="<?= $base ?>/hr/requests" style="color:#6366f1">← Back to Requests</a>
        </p>
    </div>
    <span style="background:<?= $statusColor ?>;color:#fff;padding:4px 14px;border-radius:9999px;font-size:.875rem;font-weight:600">
        <?= Formatter::escape($statusLabel) ?>
    </span>
</div>

<?php if (!empty($errors)): ?>
<div class="alert error" role="alert" style="margin-bottom:1rem">
    <?php foreach ($errors as $e): ?>
    <p style="margin:.25rem 0"><?= Formatter::escape((string) $e) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

    <!-- ── Request details ─────────────────────────────────────────────── -->
    <div class="card" style="padding:1.25rem">
        <h3 style="margin:0 0 1rem;font-size:1rem">Request Details</h3>
        <table style="width:100%;border-collapse:collapse;font-size:.875rem">
            <tr>
                <th style="text-align:left;width:38%;padding:.4rem 0;color:#6b7280;font-weight:500;vertical-align:top">Employee</th>
                <td style="padding:.4rem 0">
                    <?= Formatter::escape((string) $request['employee_name']) ?>
                    <span style="color:#9ca3af;font-size:.8rem">(<?= Formatter::escape((string) $request['employee_number']) ?>)</span>
                </td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Type</th>
                <td style="padding:.4rem 0"><?= Formatter::escape($typeName) ?></td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Submitted</th>
                <td style="padding:.4rem 0"><?= Formatter::escape((string) $request['submitted_at']) ?></td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500;vertical-align:top">Reason</th>
                <td style="padding:.4rem 0;white-space:pre-wrap"><?= Formatter::escape((string) ($request['reason'] ?? '')) ?></td>
            </tr>

            <?php if ($typeName === 'Leave' && isset($request['start_date'])): ?>
            <tr><td colspan="2" style="padding:.5rem 0 0"><hr style="border:0;border-top:1px solid #f3f4f6;margin:0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Leave period</th>
                <td style="padding:.4rem 0">
                    <?= Formatter::escape((string) $request['start_date']) ?>
                    <?php if ($request['start_date'] !== $request['end_date']): ?>
                        → <?= Formatter::escape((string) $request['end_date']) ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Days requested</th>
                <td style="padding:.4rem 0"><?= number_format((float) ($request['days_requested'] ?? 0), 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ($typeName === 'Overtime' && isset($request['overtime_date'])): ?>
            <tr><td colspan="2" style="padding:.5rem 0 0"><hr style="border:0;border-top:1px solid #f3f4f6;margin:0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">OT date</th>
                <td style="padding:.4rem 0"><?= Formatter::escape((string) $request['overtime_date']) ?></td>
            </tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Time</th>
                <td style="padding:.4rem 0">
                    <?= Formatter::escape((string) $request['overtime_start_time']) ?>
                    → <?= Formatter::escape((string) $request['overtime_end_time']) ?>
                    (<?= (int) ($request['requested_minutes'] ?? 0) ?> min)
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($typeName === 'CashAdvance' && isset($request['amount_requested'])): ?>
            <tr><td colspan="2" style="padding:.5rem 0 0"><hr style="border:0;border-top:1px solid #f3f4f6;margin:0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Amount</th>
                <td style="padding:.4rem 0;font-weight:600">₱<?= number_format((float) $request['amount_requested'], 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($request['review_notes'])): ?>
            <tr><td colspan="2" style="padding:.5rem 0 0"><hr style="border:0;border-top:1px solid #f3f4f6;margin:0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500;vertical-align:top">HR note</th>
                <td style="padding:.4rem 0;white-space:pre-wrap"><?= Formatter::escape((string) $request['review_notes']) ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($request['reviewed_at'])): ?>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">HR reviewed</th>
                <td style="padding:.4rem 0"><?= Formatter::escape((string) $request['reviewed_at']) ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($request['owner_notes'])): ?>
            <tr><td colspan="2" style="padding:.5rem 0 0"><hr style="border:0;border-top:1px solid #f3f4f6;margin:0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#f97316;font-weight:500;vertical-align:top">Owner note</th>
                <td style="padding:.4rem 0;white-space:pre-wrap;color:#f97316"><?= Formatter::escape((string) $request['owner_notes']) ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($request['owner_reviewed_at'])): ?>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Owner reviewed</th>
                <td style="padding:.4rem 0"><?= Formatter::escape((string) $request['owner_reviewed_at']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- ── Actions panel ───────────────────────────────────────────────── -->
    <div class="card" style="padding:1.25rem">
        <h3 style="margin:0 0 1rem;font-size:1rem">Actions</h3>

        <?php if (in_array($status, ['Pending', 'Returned'], true)): ?>
        <!-- HR pre-approve -->
        <form method="POST"
              action="<?= $base ?>/hr/requests/<?= (int) $request['request_id'] ?>/approve"
              style="margin-bottom:1rem"
              onsubmit="return confirm('Pre-approve this request and send it to the Business Owner for final approval?')">
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <button type="submit" class="btn btn-primary" style="width:100%">
                ✓ Pre-approve &amp; Send to Owner
            </button>
        </form>
        <p style="font-size:.8rem;color:#6b7280;margin:0 0 1rem">
            The Business Owner will review and give the final decision.
        </p>

        <!-- HR cancel -->
        <form method="POST"
              action="<?= $base ?>/hr/requests/<?= (int) $request['request_id'] ?>/cancel"
              onsubmit="return confirm('Cancel this request?')">
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <div style="margin-bottom:.75rem">
                <label for="review_notes"
                       style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                    Cancellation note <span style="color:#ef4444">*</span>
                </label>
                <textarea id="review_notes" name="review_notes" rows="3"
                          placeholder="Required reason for cancellation…"
                          style="width:100%;padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;resize:vertical"
                          required></textarea>
            </div>
            <button type="submit" class="btn btn-danger"
                    style="width:100%;background:#6b7280;color:#fff;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-size:.875rem">
                ✗ Cancel Request
            </button>
        </form>

        <?php elseif ($status === 'HRApproved'): ?>
        <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;padding:.9rem 1rem;font-size:.875rem;color:#4338ca">
            <strong>Awaiting Owner approval.</strong><br>
            This request has been pre-approved by HR and is in the Business Owner's queue.
            No further HR action is required unless the Owner returns it.
        </div>

        <?php else: ?>
        <p style="color:#6b7280;font-size:.875rem;margin:0;text-align:center;padding:1.5rem 0">
            No further actions available for a
            <strong><?= Formatter::escape($statusLabel) ?></strong> request.
        </p>
        <?php endif; ?>
    </div>

</div>

<!-- Archive -->
<?php if (empty($request['archived_at']) && !in_array($status, ['Pending', 'HRApproved'], true)): ?>
<div style="margin-top:1rem">
    <form method="POST"
          action="<?= $base ?>/hr/requests/<?= (int) $request['request_id'] ?>/archive"
          style="display:inline"
          onsubmit="return confirm('Archive this request? It will be hidden from the default list.')">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
        <button type="submit" class="btn btn-secondary" style="font-size:.8rem">
            Archive Request
        </button>
    </form>
</div>
<?php endif; ?>
