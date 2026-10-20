<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Employee — Single payslip detail view (REQ074, REQ081).
 *
 * Variables injected by EmployeePortalController::payslipDetail():
 *   array{
 *     period:string, gross:string, deductions:string, net:string,
 *     items:list<array{label:string, amount:string, type:string}>,
 *     data:array<string,mixed>
 *   } $payslip
 *   string $downloadUrl  — URL to /employee/payslips/{id}/download (PDF)
 */

$payslip     = $payslip    ?? null;
$downloadUrl = $downloadUrl ?? null;
$data        = $payslip['data'] ?? [];
?>

<div class="page-head">
    <div>
        <h1>Payslip</h1>
        <p>
            <?= Formatter::escape((string) ($data['employee_name'] ?? '')) ?>
            &nbsp;&middot;&nbsp;
            <?= Formatter::escape($payslip['period'] ?? '') ?>
        </p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <a class="btn btn-secondary" href="<?= $base ?>/employee/payslips">← Back to Payslips</a>
        <?php if ($downloadUrl): ?>
        <a class="btn btn-primary" href="<?= Formatter::escape($downloadUrl) ?>" target="_blank">
            🖨 Print / Download
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding:1.25rem;margin-bottom:1.25rem">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.25rem">
        <div>
            <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Employee</div>
            <div style="font-weight:600;margin-top:.2rem"><?= Formatter::escape((string) ($data['employee_name'] ?? '')) ?></div>
            <div style="font-size:.82rem;color:#6b7280"><?= Formatter::escape((string) ($data['employee_number'] ?? '')) ?></div>
        </div>
        <div>
            <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Position</div>
            <div style="font-weight:600;margin-top:.2rem"><?= Formatter::escape((string) ($data['position'] ?? '—')) ?></div>
        </div>
        <div>
            <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Branch</div>
            <div style="font-weight:600;margin-top:.2rem"><?= Formatter::escape((string) ($data['branch_name'] ?? '')) ?></div>
        </div>
        <div>
            <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Pay Period</div>
            <div style="font-weight:600;margin-top:.2rem"><?= Formatter::escape($payslip['period'] ?? '') ?></div>
        </div>
        <div>
            <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Pay Date</div>
            <div style="font-weight:600;margin-top:.2rem"><?= Formatter::escape((string) ($data['pay_date'] ?? '')) ?></div>
        </div>
        <div>
            <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Daily Rate</div>
            <div style="font-weight:600;margin-top:.2rem">₱<?= number_format((float) ($data['daily_rate'] ?? 0), 2) ?></div>
        </div>
    </div>

    <!-- Summary stats -->
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-bottom:1.25rem">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.85rem">
            <div style="font-size:.72rem;color:#166534;text-transform:uppercase;letter-spacing:.05em">Gross Pay</div>
            <div style="font-size:1.25rem;font-weight:700;color:#15803d;margin-top:.25rem">₱<?= Formatter::escape($payslip['gross'] ?? '0.00') ?></div>
        </div>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.85rem">
            <div style="font-size:.72rem;color:#991b1b;text-transform:uppercase;letter-spacing:.05em">Total Deductions</div>
            <div style="font-size:1.25rem;font-weight:700;color:#dc2626;margin-top:.25rem">₱<?= Formatter::escape($payslip['deductions'] ?? '0.00') ?></div>
        </div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.85rem">
            <div style="font-size:.72rem;color:#1e40af;text-transform:uppercase;letter-spacing:.05em">Net Pay</div>
            <div style="font-size:1.25rem;font-weight:700;color:#1d4ed8;margin-top:.25rem">₱<?= Formatter::escape($payslip['net'] ?? '0.00') ?></div>
        </div>
    </div>

    <!-- Itemized breakdown -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th style="text-align:right">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($payslip['items'] ?? []) as $item): ?>
                <tr>
                    <td style="<?= ($item['type'] ?? '') === 'deduction' ? 'color:#dc2626' : 'color:#15803d' ?>">
                        <?= Formatter::escape($item['label']) ?>
                    </td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;<?= ($item['type'] ?? '') === 'deduction' ? 'color:#dc2626' : 'color:#15803d' ?>">
                        <?= Formatter::escape($item['amount']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($payslip['items'])): ?>
                <tr><td colspan="2" style="text-align:center;color:#6b7280;padding:1.5rem">No itemized details available.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td style="padding:.65rem .85rem">NET PAY</td>
                    <td style="text-align:right;padding:.65rem .85rem;font-size:1.1rem;color:#1d4ed8">₱<?= Formatter::escape($payslip['net'] ?? '0.00') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($downloadUrl): ?>
    <div style="margin-top:1.25rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= Formatter::escape($downloadUrl) ?>">
            ⬇ Download PDF
        </a>
        <span style="font-size:.8rem;color:#6b7280">Generates a downloadable PDF payslip.</span>
    </div>
    <?php endif; ?>
</div>
