<?php
/**
 * View: hr/benefits/policies
 * Variables: $policies
 */
?>
<div class="page-head">
    <div>
        <h1>Contribution Policies</h1>
        <p>SSS, PhilHealth, and Pag-IBIG policy versions. <a href="/hr/benefits">← Back to Benefits</a></p>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Program</th>
                <th>Version</th>
                <th>Effective From</th>
                <th>Effective To</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($policies === []): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:2rem">No policies found. Run seeders to load the ADR-0001 demo contribution fixture.</td></tr>
        <?php else: ?>
            <?php foreach ($policies as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['policy_code']) ?></strong></td>
                <td><?= htmlspecialchars($p['version']) ?></td>
                <td><?= htmlspecialchars($p['effective_from']) ?></td>
                <td><?= $p['effective_to'] ? htmlspecialchars($p['effective_to']) : '—' ?></td>
                <td>
                    <?php
                    $colors = ['Draft'=>'#6b7280','Approved'=>'#10b981','Retired'=>'#ef4444'];
                    $c = $colors[$p['status']] ?? '#6b7280';
                    ?>
                    <span style="background:<?= $c ?>;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.75rem"><?= htmlspecialchars($p['status']) ?></span>
                </td>
                <td style="font-size:.8rem"><?= htmlspecialchars((string)$p['created_at']) ?></td>
                <td>
                    <?php if ($p['status'] === 'Draft'): ?>
                    <form method="post" action="/hr/benefits/policies/<?= (int)$p['contribution_policy_id'] ?>/approve" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-primary"
                                onclick="return confirm('Approve this contribution policy version?')">Approve</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
