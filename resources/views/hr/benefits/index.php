<?php
/**
 * View: hr/benefits/index
 * Variables: $policies, $records, $totalPolicies, $active, $totalRecords, $programs
 */
?>
<div class="page-head">
    <div>
        <h1>Benefits &amp; Deductions</h1>
        <p>Government contributions: SSS, PhilHealth, and Pag-IBIG.</p>
    </div>
    <a href="<?= $base ?>/hr/benefits/policies" class="btn btn-secondary">Manage Policies</a>
</div>

<!-- Summary -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= (int)$totalPolicies ?></span><span class="stat-label">Policy Versions</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$active ?></span><span class="stat-label">Approved Policies</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$programs ?></span><span class="stat-label">Programs</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$totalRecords ?></span><span class="stat-label">Contribution Records</span></div>
</div>

<!-- Active policies summary -->
<div class="card" style="margin-bottom:1.5rem">
    <h3 style="margin:0 0 1rem">Active Contribution Policies</h3>
    <?php $approvedPolicies = array_filter($policies, fn($p) => $p['status'] === 'Approved'); ?>
    <?php if ($approvedPolicies === []): ?>
    <p style="color:#6b7280">No approved policies. <a href="<?= $base ?>/hr/benefits/policies">Set up contribution policies →</a></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Program</th><th>Version</th><th>Effective From</th><th>Effective To</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($approvedPolicies as $p): ?>
        <tr>
            <td><strong><?= htmlspecialchars($p['program']) ?></strong></td>
            <td><?= htmlspecialchars($p['version']) ?></td>
            <td><?= htmlspecialchars($p['effective_from']) ?></td>
            <td><?= $p['effective_to'] ? htmlspecialchars($p['effective_to']) : '—' ?></td>
            <td><span style="background:#10b981;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem">Approved</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Contribution records -->
<div class="card">
    <h3 style="margin:0 0 1rem">Recent Contribution Records</h3>
    <?php if ($records === []): ?>
    <p style="color:#6b7280">No contribution records yet. Records are created automatically when payroll is computed.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Program</th>
                <th>Period</th>
                <th style="text-align:right">Employee Share</th>
                <th style="text-align:right">Employer Share</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($records as $r): ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
                <small style="color:#6b7280"><?= htmlspecialchars($r['employee_number']) ?></small>
            </td>
            <td><?= htmlspecialchars($r['program']) ?></td>
            <td style="font-size:.8rem"><?= htmlspecialchars($r['period_start'].' – '.$r['period_end']) ?></td>
            <td style="text-align:right">₱<?= number_format((float)$r['employee_share'], 2) ?></td>
            <td style="text-align:right">₱<?= number_format((float)$r['employer_share'], 2) ?></td>
            <td>
                <?php $c = $r['status'] === 'Locked' ? '#f59e0b' : '#10b981'; ?>
                <span style="background:<?= $c ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem"><?= htmlspecialchars($r['status']) ?></span>
            </td>
            <td>
                <?php if ($r['status'] !== 'Locked'): ?>
                <form method="post" action="<?= $base ?>/hr/benefits/contributions/<?= (int)$r['contribution_id'] ?>/lock" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Lock this contribution record?')">Lock</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?= $base ?>/hr/benefits/contributions/<?= (int)$r['contribution_id'] ?>/unlock" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Unlock this contribution record?')">Unlock</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
