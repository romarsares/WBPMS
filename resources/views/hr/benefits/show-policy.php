<?php
/**
 * View: hr/benefits/show-policy
 * Variables: $policy, $asOf, $isPolicyEffective, $sssBrackets, $philhealthRate, $pagibigRate
 */

use Wbpms\Http\View\Formatter;

$money = static fn($value): string => '₱' . number_format((float) $value, 2);
$dateRange = static function (?string $from, ?string $to): string {
    $start = $from ? Formatter::date($from) : '—';
    $end = $to ? Formatter::date($to) : 'No declared end date';
    return $start . ' – ' . $end;
};
$policyRange = $dateRange((string) $policy['effective_from'], $policy['effective_to']);
?>

<div class="page-head">
    <div>
        <h1>Contribution Tables</h1>
        <p><?= htmlspecialchars($policy['policy_code']) ?> · <?= htmlspecialchars($policy['version']) ?></p>
    </div>
    <a href="<?= $base ?>/hr/benefits/policies" class="btn btn-secondary">← Back to policies</a>
</div>

<div class="card" style="margin-bottom:1.5rem">
    <form method="GET" action="<?= $base ?>/hr/benefits/policies/<?= (int) $policy['contribution_policy_id'] ?>" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:.75rem">
        <div>
            <label for="as_of" style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem">Show rules effective on</label>
            <input id="as_of" name="as_of" type="date" value="<?= htmlspecialchars($asOf) ?>">
        </div>
        <button type="submit" class="btn btn-primary">View effective tables</button>
        <div style="font-size:.875rem;color:#6b7280">Policy period: <?= htmlspecialchars($policyRange) ?></div>
    </form>
    <p style="margin:1rem 0 0;color:#6b7280;font-size:.8rem">“No declared end date” means no replacement policy has been entered in WBPMS. HR should review official agency issuances before relying on an open-ended policy for a new payroll period.</p>
</div>

<?php if (!$isPolicyEffective): ?>
<div class="card" style="border-left:4px solid #f59e0b;margin-bottom:1.5rem">
    <strong>This policy is not effective on <?= htmlspecialchars(Formatter::date($asOf)) ?>.</strong>
    <span style="color:#6b7280"> Its policy period is <?= htmlspecialchars($policyRange) ?>. The table below is shown for review only.</span>
</div>
<?php endif; ?>

<div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card"><span class="stat-value"><?= count($sssBrackets) ?></span><span class="stat-label">Active SSS salary brackets</span></div>
    <div class="stat-card"><span class="stat-value"><?= $philhealthRate ? number_format((float) $philhealthRate['rate_decimal'] * 100, 2) . '%' : '—' ?></span><span class="stat-label">PhilHealth total rate</span></div>
    <div class="stat-card"><span class="stat-value"><?= $pagibigRate ? ($pagibigRate['rate_decimal'] !== null ? number_format((float) $pagibigRate['rate_decimal'] * 100, 2) . '%' : 'Fixed') : '—' ?></span><span class="stat-label">Pag-IBIG contribution method</span></div>
</div>

<div class="card" style="margin-bottom:1.5rem">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:baseline;flex-wrap:wrap;margin-bottom:1rem">
        <div><h2 style="margin:0">SSS</h2><p style="margin:.25rem 0 0;color:#6b7280">Salary brackets effective on <?= htmlspecialchars(Formatter::date($asOf)) ?>.</p></div>
        <span class="badge badge-green"><?= array_filter($sssBrackets, static fn(array $row): bool => $row['effective_to'] === null) !== [] ? 'No declared end date' : 'End date recorded' ?></span>
    </div>
    <?php if ($sssBrackets === []): ?>
        <p style="color:#6b7280">No SSS brackets in this policy are effective on the selected date.</p>
    <?php else: ?>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr><th>Monthly salary range</th><th style="text-align:right">Employee share</th><th style="text-align:right">Employer share</th><th>Effective period</th></tr></thead>
            <tbody>
            <?php foreach ($sssBrackets as $bracket): ?>
                <tr>
                    <td><?= $money($bracket['salary_from']) ?><?= $bracket['salary_to'] === null ? ' and above' : ' – ' . $money($bracket['salary_to']) ?></td>
                    <td style="text-align:right"><?= $money($bracket['employee_share']) ?></td>
                    <td style="text-align:right"><?= $money($bracket['employer_share']) ?></td>
                    <td><?= htmlspecialchars($dateRange($bracket['effective_from'], $bracket['effective_to'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem">
    <div class="card">
        <h2 style="margin-top:0">PhilHealth</h2>
        <p style="color:#6b7280">Effective period: <?= htmlspecialchars($policyRange) ?>.</p>
        <?php if ($philhealthRate === null): ?>
            <p style="color:#6b7280">No PhilHealth rule is recorded for this policy.</p>
        <?php else: ?>
            <table class="data-table"><tbody>
                <tr><th>Total premium rate</th><td><?= number_format((float) $philhealthRate['rate_decimal'] * 100, 2) ?>%</td></tr>
                <tr><th>Employee share</th><td><?= number_format((float) $philhealthRate['employee_share_decimal'] * 100, 2) ?>%</td></tr>
                <tr><th>Employer share</th><td><?= number_format(((float) $philhealthRate['rate_decimal'] - (float) $philhealthRate['employee_share_decimal']) * 100, 2) ?>%</td></tr>
                <tr><th>Monthly salary floor</th><td><?= $philhealthRate['basis_floor'] === null ? 'None recorded' : $money($philhealthRate['basis_floor']) ?></td></tr>
                <tr><th>Monthly salary ceiling</th><td><?= $philhealthRate['basis_ceiling'] === null ? 'No declared ceiling' : $money($philhealthRate['basis_ceiling']) ?></td></tr>
            </tbody></table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Pag-IBIG</h2>
        <p style="color:#6b7280">Effective period: <?= htmlspecialchars($policyRange) ?>.</p>
        <?php if ($pagibigRate === null): ?>
            <p style="color:#6b7280">No Pag-IBIG rule is recorded for this policy.</p>
        <?php elseif ($pagibigRate['rate_decimal'] !== null): ?>
            <table class="data-table"><tbody>
                <tr><th>Contribution rate</th><td><?= number_format((float) $pagibigRate['rate_decimal'] * 100, 2) ?>%</td></tr>
                <tr><th>Monthly salary ceiling</th><td><?= $pagibigRate['basis_ceiling'] === null ? 'No declared ceiling' : $money($pagibigRate['basis_ceiling']) ?></td></tr>
                <tr><th>Maximum employee share</th><td><?= $pagibigRate['basis_ceiling'] === null ? '—' : $money((float) $pagibigRate['basis_ceiling'] * (float) $pagibigRate['rate_decimal']) ?></td></tr>
                <tr><th>Maximum employer share</th><td><?= $pagibigRate['basis_ceiling'] === null ? '—' : $money((float) $pagibigRate['basis_ceiling'] * (float) $pagibigRate['rate_decimal']) ?></td></tr>
            </tbody></table>
        <?php else: ?>
            <table class="data-table"><tbody>
                <tr><th>Contribution method</th><td>Fixed amount</td></tr>
                <tr><th>Employee share</th><td><?= $money($pagibigRate['employee_fixed_amount']) ?></td></tr>
                <tr><th>Employer share</th><td><?= $money($pagibigRate['employer_fixed_amount']) ?></td></tr>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
