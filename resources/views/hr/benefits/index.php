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
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
        <h3 style="margin:0">Recent Contribution Records</h3>
        <?php if ($isFiltered): ?>
        <span style="font-size:.8rem;color:#6b7280">Filters active</span>
        <?php endif; ?>
    </div>
    <p style="margin:0 0 1rem;color:#6b7280;font-size:.875rem">Records are generated when payroll is computed and become final when the payroll run is approved.</p>

    <!-- Filter form -->
    <form method="GET" action="<?= $base ?>/hr/benefits" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;margin-bottom:1.25rem;padding:.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem">
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Program</label>
            <select name="program" style="min-width:9rem">
                <option value="">All programs</option>
                <?php foreach (['SSS', 'PhilHealth', 'PagIBIG'] as $prog): ?>
                <option value="<?= htmlspecialchars($prog) ?>" <?= ($filters['program'] ?? '') === $prog ? 'selected' : '' ?>><?= htmlspecialchars($prog) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Employee</label>
            <input type="search" name="employee_search" value="<?= htmlspecialchars($filters['employee_search'] ?? '') ?>" placeholder="Name or employee no." style="min-width:13rem">
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Period from</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        </div>
        <div>
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Period to</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="<?= $base ?>/hr/benefits" class="btn btn-secondary">Clear</a>
    </form>
    <?php if ($records === []): ?>
    <p style="color:#6b7280">
        <?php if ($isFiltered): ?>
        No contribution records match the selected filters.
        <?php else: ?>
        No contribution records yet. Records are created automatically when payroll is computed.
        <?php endif; ?>
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Program</th>
                <th>Period</th>
                <th>Pay Date</th>
                <th style="text-align:right">Employee Share</th>
                <th style="text-align:right">Employer Share</th>
                <th>Status</th>
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
            <td><?= htmlspecialchars($r['pay_date']) ?></td>
            <td style="text-align:right">₱<?= number_format((float)$r['employee_share'], 2) ?></td>
            <td style="text-align:right">₱<?= number_format((float)$r['employer_share'], 2) ?></td>
            <td>
                <?php $c = $r['status'] === 'Locked' ? '#f59e0b' : '#10b981'; ?>
                <span style="background:<?= $c ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem"><?= htmlspecialchars($r['status']) ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
