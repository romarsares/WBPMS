<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — New payroll run: period/branch selection and employee preview.
 *
 * Step 1 (GET /hr/payroll/run):  Show selection form.
 * Step 2 (POST preview):         Show eligible employees and totals before generate.
 *
 * @var list<array{id:int,name:string}> $branches
 * @var list<array{id:int,label:string,start:string,end:string}> $periods   Available payroll periods
 * @var bool   $preview       True when previewing before generating
 * @var string $selectedBranch
 * @var string $selectedPeriod
 * @var list<array{
 *     employee_id: int,
 *     employee_name: string,
 *     employee_number: string,
 *     daily_rate: string,
 *     days_present: int,
 *     gross_pay: string,
 *     total_deductions: string,
 *     net_pay: string,
 *     flags: list<string>
 * }> $previewRows
 * @var string $grossTotal
 * @var string $netTotal
 * @var array<string,string> $errors
 * @var string $csrf
 */

$branches       ??= [];
$periods        ??= [];
$preview        ??= false;
$selectedBranch ??= '';
$selectedPeriod ??= '';
$previewRows    ??= [];
$grossTotal     ??= '';
$netTotal       ??= '';
$errors         ??= [];
$csrf           ??= '';

$err = static fn(string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . Formatter::escape($errors[$key]) . '</span>' : '';
?>

<div class="page-header">
    <h1>New Payroll Run</h1>
    <a href="/hr/payroll" class="btn btn-secondary">← Back to payroll</a>
</div>

<!-- Selection form -->
<div class="card" style="max-width:520px;margin-bottom:1.5rem">
    <h2 style="margin-top:0;font-size:1rem">Select Branch &amp; Period</h2>
    <form method="POST" action="/hr/payroll/run">
        <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
        <input type="hidden" name="action" value="preview">

        <div class="form-row">
            <div class="form-group">
                <label for="branch_id">Branch <span style="color:#dc2626">*</span></label>
                <select id="branch_id" name="branch_id"
                    class="<?= isset($errors['branch_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="">— select branch —</option>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>"
                        <?= ((string) $branch['id'] === $selectedBranch) ? 'selected' : '' ?>>
                        <?= Formatter::escape($branch['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('branch_id') ?>
            </div>
            <div class="form-group">
                <label for="period_id">Payroll period <span style="color:#dc2626">*</span></label>
                <select id="period_id" name="period_id"
                    class="<?= isset($errors['period_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="">— select period —</option>
                    <?php foreach ($periods as $period): ?>
                    <option value="<?= (int) $period['id'] ?>"
                        <?= ((string) $period['id'] === $selectedPeriod) ? 'selected' : '' ?>>
                        <?= Formatter::escape($period['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('period_id') ?>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error" role="alert">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <button type="submit" class="btn btn-secondary">Preview Employees</button>
    </form>
</div>

<?php if ($preview && !empty($previewRows)): ?>

<!-- Preview results -->
<div class="page-header" style="margin-bottom:.75rem">
    <h2 style="margin:0;font-size:1.05rem">
        Preview — <?= count($previewRows) ?> eligible
        employee<?= count($previewRows) !== 1 ? 's' : '' ?>
    </h2>
</div>

<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem">
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th style="text-align:right">Daily Rate</th>
                <th style="text-align:center">Days Present</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Deductions</th>
                <th style="text-align:right">Net Pay</th>
                <th>Flags</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($previewRows as $row): ?>
        <tr>
            <td>
                <div style="font-weight:500"><?= Formatter::escape($row['employee_name']) ?></div>
                <div style="font-size:.78rem;color:#6b7280"><?= Formatter::escape($row['employee_number']) ?></div>
            </td>
            <td style="text-align:right">₱<?= Formatter::escape($row['daily_rate']) ?></td>
            <td style="text-align:center"><?= (int) $row['days_present'] ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($row['gross_pay']) ?></td>
            <td style="text-align:right;color:#dc2626">₱<?= Formatter::escape($row['total_deductions']) ?></td>
            <td style="text-align:right;font-weight:600">₱<?= Formatter::escape($row['net_pay']) ?></td>
            <td style="font-size:.78rem;color:#6b7280">
                <?= Formatter::escape(implode(', ', $row['flags'])) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:600">
                <td colspan="3" style="padding:.6rem .85rem">Totals</td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= Formatter::escape($grossTotal) ?></td>
                <td></td>
                <td style="text-align:right;padding:.6rem .85rem">₱<?= Formatter::escape($netTotal) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>

<div class="flash flash-warning">
    Review the preview carefully. Once generated, the payroll snapshot cannot be edited.
    Payroll figures are derived from attendance and salary data — never entered manually.
</div>

<!-- Generate form -->
<form method="POST" action="/hr/payroll/run">
    <input type="hidden" name="_csrf"      value="<?= Formatter::escape($csrf) ?>">
    <input type="hidden" name="action"     value="generate">
    <input type="hidden" name="branch_id"  value="<?= Formatter::escape($selectedBranch) ?>">
    <input type="hidden" name="period_id"  value="<?= Formatter::escape($selectedPeriod) ?>">
    <div style="display:flex;gap:.75rem">
        <button type="submit" class="btn btn-primary">Generate &amp; Lock Payroll</button>
        <a href="/hr/payroll/run" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php elseif ($preview && empty($previewRows)): ?>
<div class="flash flash-warning" role="alert">
    No eligible employees found for the selected branch and period.
    Ensure attendance has been imported and employees are assigned to this branch.
</div>
<?php endif; ?>
