<?php
/**
 * View: hr/requests/show
 * Variables: $request (array), $errors (array)
 */
$statusColors = ['Pending'=>'#f59e0b','Approved'=>'#10b981','Rejected'=>'#ef4444','Cancelled'=>'#6b7280'];
$statusColor  = $statusColors[$request['status']] ?? '#6b7280';
?>
<div class="page-head">
    <div>
        <h1>Request #<?= (int)$request['request_id'] ?></h1>
        <p><a href="/hr/requests">← Back to Requests</a></p>
    </div>
    <span style="background:<?= $statusColor ?>;color:#fff;padding:4px 14px;border-radius:9999px;font-size:.875rem;align-self:center"><?= htmlspecialchars($request['status']) ?></span>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-error" style="margin-bottom:1rem;padding:.75rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b">
    <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <!-- Details -->
    <div class="card">
        <h3 style="margin:0 0 1rem">Request Details</h3>
        <table style="width:100%;border-collapse:collapse">
            <tr><th style="text-align:left;width:40%;padding:.4rem 0;color:#6b7280">Employee</th><td><?= htmlspecialchars($request['employee_name']) ?> (<?= htmlspecialchars($request['employee_number']) ?>)</td></tr>
            <tr><th style="text-align:left;padding:.4rem 0;color:#6b7280">Type</th><td><?= htmlspecialchars($request['type_name']) ?></td></tr>
            <tr><th style="text-align:left;padding:.4rem 0;color:#6b7280">Submitted</th><td><?= htmlspecialchars((string)$request['submitted_at']) ?></td></tr>
            <tr><th style="text-align:left;padding:.4rem 0;color:#6b7280">Reason</th><td style="white-space:pre-wrap"><?= htmlspecialchars((string)$request['reason']) ?></td></tr>
            <?php if ($request['review_notes']): ?>
            <tr><th style="text-align:left;padding:.4rem 0;color:#6b7280">HR Note</th><td style="white-space:pre-wrap"><?= htmlspecialchars((string)$request['review_notes']) ?></td></tr>
            <?php endif; ?>
            <?php if ($request['reviewed_at']): ?>
            <tr><th style="text-align:left;padding:.4rem 0;color:#6b7280">Reviewed At</th><td><?= htmlspecialchars((string)$request['reviewed_at']) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Actions -->
    <?php if ($request['status'] === 'Pending'): ?>
    <div class="card">
        <h3 style="margin:0 0 1rem">Actions</h3>

        <!-- Approve -->
        <form method="post" action="/hr/requests/<?= (int)$request['request_id'] ?>/approve" style="margin-bottom:1rem">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Approve this request?')">✓ Approve Request</button>
        </form>

        <!-- Reject -->
        <form method="post" action="/hr/requests/<?= (int)$request['request_id'] ?>/reject">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-group" style="margin-bottom:.75rem">
                <label for="review_notes" style="display:block;font-weight:500;margin-bottom:.25rem">Rejection Note <span style="color:#ef4444">*</span></label>
                <textarea id="review_notes" name="review_notes" rows="3" class="form-control" placeholder="Required reason for rejection…" style="width:100%;resize:vertical" required></textarea>
            </div>
            <button type="submit" class="btn btn-danger" style="background:#ef4444;color:#fff;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer" onclick="return confirm('Reject this request?')">✗ Reject Request</button>
        </form>
    </div>
    <?php else: ?>
    <div class="card" style="display:flex;align-items:center;justify-content:center;color:#6b7280">
        <p>No actions available for a <?= htmlspecialchars($request['status']) ?> request.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Archive -->
<?php if (!isset($request['archived_at']) || $request['archived_at'] === null): ?>
<div style="margin-top:1rem">
    <form method="post" action="/hr/requests/<?= (int)$request['request_id'] ?>/archive" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-secondary" onclick="return confirm('Archive this request? It will be hidden from the default list.')">Archive</button>
    </form>
</div>
<?php endif; ?>
