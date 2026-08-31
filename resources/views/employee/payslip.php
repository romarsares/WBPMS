<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Employee — My Payslips view.
 *
 * Variables injected by controller:
 *   array{
 *     period:string, gross:string, deductions:string, net:string,
 *     items:list<array{label:string, amount:string}>
 *   }|null $payslip
 *   string|null $downloadUrl
 *   list<array{payroll_id:int, period:string, net:string, status:string}> $payslips
 */
$payslip    = $payslip    ?? null;
$payslips   = $payslips   ?? [];
$downloadUrl = $downloadUrl ?? null;
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
?>

<div class="page-head">
    <div>
        <h1>My Payslips</h1>
        <p>View and download your approved payslips.</p>
    </div>
</div>

<?php if ($payslip !== null): ?>
    <!-- ---- Single payslip detail ---- -->
    <div class="panel">
        <h2>Payslip: <?= Formatter::escape($payslip['period']) ?></h2>

        <div class="cards four" style="margin-bottom:16px">
            <div class="stat">
                <strong><?= Formatter::escape($payslip['gross']) ?></strong>
                <span>Gross Pay</span>
            </div>
            <div class="stat">
                <strong><?= Formatter::escape($payslip['deductions']) ?></strong>
                <span>Total Deductions</span>
            </div>
            <div class="stat">
                <strong><?= Formatter::escape($payslip['net']) ?></strong>
                <span>Net Pay</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>DESCRIPTION</th><th>AMOUNT</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($payslip['items'] as $item): ?>
                        <tr>
                            <td><?= Formatter::escape($item['label']) ?></td>
                            <td><?= Formatter::escape($item['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($downloadUrl): ?>
            <div style="margin-top:16px">
                <a class="btn-secondary" href="<?= Formatter::escape($downloadUrl) ?>">
                    ▤ Download Payslip
                </a>
            </div>
        <?php endif; ?>

        <div style="margin-top:12px">
            <a class="text-btn" href="<?= $base ?>/my-payslips">← Back to payslips</a>
        </div>
    </div>

<?php else: ?>
    <!-- ---- Payslip list ---- -->
    <div class="panel table-wrap">
        <table>
            <thead>
                <tr>
                    <th>PERIOD</th>
                    <th>NET PAY</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($payslips)): ?>
                <tr>
                    <td colspan="4" class="muted" style="text-align:center;padding:28px">
                        No payslips available yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($payslips as $p): ?>
                    <tr>
                        <td><?= Formatter::escape($p['period']) ?></td>
                        <td><?= Formatter::escape($p['net']) ?></td>
                        <td>
                            <?php
                            $badgeClass = match ($p['status']) {
                                'Approved' => 'ok',
                                'Returned' => 'bad',
                                default    => 'wait',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>">
                                <?= Formatter::escape($p['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a class="text-btn"
                               href="<?= $base ?>/my-payslips?id=<?= (int) $p['payroll_id'] ?>">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
