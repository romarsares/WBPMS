<?php

use Wbpms\Http\View\Formatter;

/**
 * View: owner/requests/show  (GET /owner/requests/{id})
 *
 * Variables:
 *   array<string,mixed>  $request — full row from RequestService::findOrFail()
 *   string $base, $csrf — injected by ViewRenderer
 */

$request ??= [];
$base    ??= '';
$csrf    ??= '';

$status   = (string) ($request['status'] ?? '');
$typeName = (string) ($request['type_name'] ?? '');

$statusColors = [
    'HRApproved' => '#6366f1',
    'Approved'   => '#10b981',
    'Returned'   => '#f97316',
    'Rejected'   => '#ef4444',
];
$statusLabels = [
    'HRApproved' => 'Awaiting Your Approval',
    'Approved'   => 'Approved',
    'Returned'   => 'Returned to HR',
    'Rejected'   => 'Rejected',
];
$statusColor = $statusColors[$status] ?? '#6b7280';
$statusLabel = $statusLabels[$status] ?? $status;
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
    <div>
        <h1 style="margin:0 0 .2rem">Request #<?= (int) $request['request_id'] ?></h1>
        <p style="margin:0;font-size:.875rem">
            <a href="<?= $base ?>/owner/requests" style="color:#6366f1">← Back to Request Approvals</a>
        </p>
    </div>
    <span style="background:<?= $statusColor ?>;color:#fff;padding:4px 14px;border-radius:9999px;font-size:.875rem;font-weight:600">
        <?= Formatter::escape($statusLabel) ?>
    </span>
</div>

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
            <tr><td colspan="2"><hr style="border:0;border-top:1px solid #f3f4f6;margin:.4rem 0"></td></tr>
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
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Days</th>
                <td style="padding:.4rem 0"><?= number_format((float) ($request['days_requested'] ?? 0), 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ($typeName === 'Overtime' && isset($request['overtime_date'])): ?>
            <tr><td colspan="2"><hr style="border:0;border-top:1px solid #f3f4f6;margin:.4rem 0"></td></tr>
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
            <tr><td colspan="2"><hr style="border:0;border-top:1px solid #f3f4f6;margin:.4rem 0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">Amount</th>
                <td style="padding:.4rem 0;font-weight:600;font-size:1rem">
                    ₱<?= number_format((float) $request['amount_requested'], 2) ?>
                </td>
            </tr>
            <?php endif; ?>

            <!-- HR review trail -->
            <?php if (!empty($request['reviewed_at'])): ?>
            <tr><td colspan="2"><hr style="border:0;border-top:1px solid #f3f4f6;margin:.4rem 0"></td></tr>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500">HR pre-approved</th>
                <td style="padding:.4rem 0;color:#6366f1;font-weight:500">
                    <?= Formatter::escape((string) $request['reviewed_at']) ?>
                </td>
            </tr>
            <?php if (!empty($request['review_notes'])): ?>
            <tr>
                <th style="text-align:left;padding:.4rem 0;color:#6b7280;font-weight:500;vertical-align:top">HR note</th>
                <td style="padding:.4rem 0;white-space:pre-wrap"><?= Formatter::escape((string) $request['review_notes']) ?></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>
    </div>

    <!-- ── Owner action panel ──────────────────────────────────────────── -->
    <div>
        <?php if ($status === 'HRApproved'): ?>
        <div class="card" style="padding:1.25rem">
            <h3 style="margin:0 0 .25rem;font-size:1rem">Your Decision</h3>
            <p style="margin:0 0 1.25rem;font-size:.8rem;color:#6b7280">
                HR has reviewed and pre-approved this request. Your approval is final and will
                trigger the relevant side-effects (leave deduction, cash advance obligation, etc.).
            </p>

            <!-- Final approve -->
            <form method="POST"
                  action="<?= $base ?>/owner/requests/<?= (int) $request['request_id'] ?>/approve"
                  style="margin-bottom:1rem"
                  onsubmit="return confirm('Finally approve this request? This action cannot be undone.')">
                <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                <button type="submit" class="btn btn-primary" style="width:100%;padding:.6rem 1rem;font-size:.95rem">
                    ✓ Approve Request
                </button>
            </form>

            <!-- Return to HR -->
            <form method="POST"
                  action="<?= $base ?>/owner/requests/<?= (int) $request['request_id'] ?>/return"
                  onsubmit="return confirm('Return this request to HR for revision?')">
                <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
                <div style="margin-bottom:.75rem">
                    <label for="owner_notes"
                           style="display:block;font-weight:500;font-size:.875rem;margin-bottom:.3rem">
                        Return note <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="owner_notes" name="owner_notes" rows="3"
                              placeholder="Explain what needs to be corrected before you can approve…"
                              style="width:100%;padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;resize:vertical"
                              required></textarea>
                </div>
                <button type="submit"
                        style="width:100%;background:#f97316;color:#fff;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-size:.875rem;font-weight:500">
                    ↩ Return to HR
                </button>
            </form>
        </div>

        <?php else: ?>
        <div class="card" style="padding:1.25rem;text-align:center;color:#6b7280;min-height:120px;display:flex;align-items:center;justify-content:center">
            <p style="margin:0;font-size:.875rem">
                This request has already been
                <strong><?= Formatter::escape(strtolower($statusLabel)) ?></strong>.
                No further action is needed.
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($request['owner_notes'])): ?>
        <div class="card" style="margin-top:1rem;padding:1rem;border-left:4px solid #f97316;background:#fff7ed">
            <p style="margin:0 0 .25rem;font-size:.8rem;font-weight:600;color:#c2410c">Your previous note</p>
            <p style="margin:0;font-size:.875rem;white-space:pre-wrap"><?= Formatter::escape((string) $request['owner_notes']) ?></p>
            <p style="margin:.5rem 0 0;font-size:.75rem;color:#9ca3af"><?= Formatter::escape((string) $request['owner_reviewed_at']) ?></p>
        </div>
        <?php endif; ?>
    </div>

</div>
