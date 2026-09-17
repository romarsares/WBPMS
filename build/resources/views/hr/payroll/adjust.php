<?php
/** @var int $runId */
/** @var array<string,mixed> $payroll */
/** @var list<string> $errors */
/** @var array<string,string> $old */

use Wbpms\Http\View\Formatter;

$value = static fn (string $key, string $fallback = ''): string => (string) ($old[$key] ?? $fallback);
?>

<div class="page-header">
    <div>
        <h1>Manual Payroll Adjustment</h1>
        <p>Add a documented earning or deduction before submitting payroll for approval.</p>
    </div>
    <a href="<?= $base ?>/hr/payroll/<?= $runId ?>" class="btn btn-secondary">Back to Payroll Run</a>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?><div><?= Formatter::escape($error) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem">
    <strong><?= Formatter::escape((string) $payroll['employee_name']) ?></strong>
    <small style="color:#6b7280"> · <?= Formatter::escape((string) $payroll['employee_number']) ?></small>
    <div style="display:flex;gap:1.5rem;margin-top:.5rem;font-size:.9rem">
        <span>Gross: <strong>₱<?= number_format((float) $payroll['gross_pay'], 2) ?></strong></span>
        <span>Deductions: <strong>₱<?= number_format((float) $payroll['total_deductions'], 2) ?></strong></span>
        <span>Net: <strong>₱<?= number_format((float) $payroll['net_pay'], 2) ?></strong></span>
    </div>
</div>

<div class="card" style="max-width:700px">
    <form method="post" action="<?= $base ?>/hr/payroll/<?= $runId ?>/employees/<?= (int) $payroll['payroll_id'] ?>/adjust">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">

        <div>
            <label for="adjustment_kind">Adjustment type</label>
            <select id="adjustment_kind" name="adjustment_kind" required>
                <option value="">Select one</option>
                <option value="earning" <?= $value('adjustment_kind') === 'earning' ? 'selected' : '' ?>>Additional pay / earning</option>
                <option value="deduction" <?= $value('adjustment_kind') === 'deduction' ? 'selected' : '' ?>>Manual deduction</option>
            </select>
        </div>
        <div style="margin-top:1rem">
            <label for="amount">Amount</label>
            <input id="amount" name="amount" type="number" min="0.01" max="1000000" step="0.01" required value="<?= Formatter::escape($value('amount')) ?>" style="max-width:240px">
        </div>
        <div style="margin-top:1rem">
            <label for="reason">Reason and supporting reference</label>
            <textarea id="reason" name="reason" required maxlength="1000" rows="4" placeholder="e.g. Approved allowance, loan repayment, or correction reference."><?= Formatter::escape($value('reason')) ?></textarea>
        </div>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.75rem 1rem;margin:1rem 0;color:#92400e;font-size:.875rem">
            This action is logged, updates the employee and payroll-run totals immediately, and is unavailable after approval.
        </div>
        <button class="btn btn-primary" type="submit">Save manual adjustment</button>
    </form>
</div>
