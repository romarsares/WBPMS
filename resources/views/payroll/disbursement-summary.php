<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

$branchRuns ??= [];
$blockingRuns ??= [];
$entries ??= [];
$summary ??= ['employee_count' => 0, 'gross_total' => 0, 'deductions_total' => 0, 'cheque_total' => 0, 'missing_bdo_accounts' => 0];
$isReady ??= false;
$backPath ??= '/hr/payroll';
$backLabel ??= 'Back to Payroll';
$forwardNote = 'Payroll disbursement package for ' . (string) ($run['period_label'] ?? '') . ' (' . (string) ($run['branch_name'] ?? '') . '). ' . 'Attached: approved payroll summary, cut-off timesheet, and manual BDO deposit-slip preparation list. Total cheque requirement: ₱' . number_format((float) $summary['cheque_total'], 2) . '.';
?>

<div class="disbursement-page">
    <header class="disbursement-header">
        <div>
            <p class="disbursement-kicker">Payroll forwarding package</p>
            <h1>Disbursement Summary</h1>
            <p><?= Formatter::escape((string) $run['period_label']) ?> &middot; Pay date <?= Formatter::date((string) $run['pay_date']) ?></p>
        </div>
        <div class="disbursement-actions no-print">
            <a class="btn btn-secondary" href="<?= $base ?><?= Formatter::escape($backPath) ?>"><?= Formatter::escape($backLabel) ?></a>
            <?php if ($isReady): ?><button type="button" class="btn btn-secondary" onclick="copyForwardingNote()">Copy Forwarding Note</button><button type="button" class="btn btn-primary" onclick="window.print()">Print Package</button><?php endif; ?>
        </div>
    </header>

    <?php if (!$isReady): ?>
    <section class="disbursement-blocked">
        <h2>Not ready for disbursement</h2>
        <p>Every non-cancelled branch payroll run in this cut-off must be approved before the BDO preparation list and aggregate cheque amount can be produced.</p>
        <table><thead><tr><th>Branch</th><th>Payroll status</th><th class="number">Employees</th><th class="number">Net pay</th></tr></thead><tbody>
        <?php foreach ($branchRuns as $branchRun): ?><tr><td><?= Formatter::escape((string) $branchRun['branch_name']) ?></td><td><?= Formatter::escape((string) $branchRun['status']) ?></td><td class="number"><?= (int) $branchRun['employee_count'] ?></td><td class="number">₱<?= number_format((float) $branchRun['net_total'], 2) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </section>
    <?php else: ?>
    <section class="disbursement-forwarding">
        <strong>Forwarding note</strong>
        <p id="forwardingNote"><?= Formatter::escape($forwardNote) ?></p>
        <small>This is a printable/manual BDO preparation document; it does not create a bank transfer or upload file.</small>
    </section>

    <section class="disbursement-stats">
        <div><strong><?= (int) $summary['employee_count'] ?></strong><span>Employees to prepare</span></div>
        <div><strong>₱<?= number_format((float) $summary['gross_total'], 2) ?></strong><span>Gross payroll</span></div>
        <div><strong>₱<?= number_format((float) $summary['deductions_total'], 2) ?></strong><span>Total deductions</span></div>
        <div class="cheque"><strong>₱<?= number_format((float) $summary['cheque_total'], 2) ?></strong><span>One aggregate BDO cheque</span></div>
    </section>

    <?php if ((int) $summary['missing_bdo_accounts'] > 0): ?>
    <div class="disbursement-warning"><strong>Account setup required:</strong> <?= (int) $summary['missing_bdo_accounts'] ?> employee<?= (int) $summary['missing_bdo_accounts'] === 1 ? '' : 's' ?> have no active BDO account for the pay date. Resolve the account record before preparing that employee’s deposit slip.</div>
    <?php endif; ?>

    <section class="disbursement-card">
        <h2>Approved branch payroll summary</h2>
        <table><thead><tr><th>Branch</th><th class="number">Employees</th><th class="number">Gross pay</th><th class="number">Deductions</th><th class="number">Net pay</th></tr></thead><tbody>
        <?php foreach ($branchRuns as $branchRun): ?><tr><td><?= Formatter::escape((string) $branchRun['branch_name']) ?></td><td class="number"><?= (int) $branchRun['employee_count'] ?></td><td class="number">₱<?= number_format((float) $branchRun['gross_total'], 2) ?></td><td class="number">₱<?= number_format((float) $branchRun['deductions_total'], 2) ?></td><td class="number">₱<?= number_format((float) $branchRun['net_total'], 2) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </section>

    <section class="disbursement-card">
        <h2>Manual BDO deposit-slip preparation list</h2>
        <p>Prepare one deposit slip for each employee using the exact net-pay amount below.</p>
        <div class="disbursement-table-wrap"><table><thead><tr><th>#</th><th>Branch</th><th>Employee</th><th>BDO account name</th><th>BDO account number</th><th class="number">Exact net pay</th></tr></thead><tbody>
        <?php foreach ($entries as $index => $entry): ?><tr><td><?= $index + 1 ?></td><td><?= Formatter::escape((string) $entry['branch_name']) ?></td><td><strong><?= Formatter::escape((string) $entry['employee_name']) ?></strong><small><?= Formatter::escape((string) $entry['employee_number']) ?></small></td><td><?= Formatter::escape((string) ($entry['account_name'] ?? '—')) ?></td><td class="account"><?= empty($entry['account_number']) ? '<span class="missing">MISSING</span>' : Formatter::escape((string) $entry['account_number']) ?></td><td class="number net">₱<?= number_format((float) $entry['net_pay'], 2) ?></td></tr><?php endforeach; ?>
        </tbody><tfoot><tr><td colspan="5">Aggregate cheque amount</td><td class="number">₱<?= number_format((float) $summary['cheque_total'], 2) ?></td></tr></tfoot></table></div>
    </section>
    <?php endif; ?>
    <footer>Source documents: approved payroll runs for this cut-off and their corresponding attendance/timesheet records. Generated <?= Formatter::escape(date('M j, Y g:i A')) ?>.</footer>
</div>

<style>
.disbursement-page{max-width:1250px;margin:0 auto}.disbursement-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}.disbursement-header h1{margin:.15rem 0;font-size:1.55rem}.disbursement-header p{margin:0;color:#64748b}.disbursement-kicker{color:#4f46e5!important;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.disbursement-actions{display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end}.disbursement-blocked{background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:1.1rem}.disbursement-blocked h2{margin:0 0 .35rem;color:#92400e;font-size:1.1rem}.disbursement-blocked p{margin:0 0 .9rem;color:#78350f;font-size:.88rem}.disbursement-forwarding{background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:.85rem 1rem;margin-bottom:1rem}.disbursement-forwarding p{margin:.3rem 0;color:#1e3a8a;font-size:.88rem}.disbursement-forwarding small{color:#475569;font-size:.76rem}.disbursement-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.disbursement-stats div{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:.75rem}.disbursement-stats .cheque{border-top:3px solid #4f46e5}.disbursement-stats strong{display:block;font-size:1.15rem;color:#0f172a}.disbursement-stats span{font-size:.74rem;color:#64748b}.disbursement-warning{background:#fff7ed;border:1px solid #fdba74;border-radius:8px;color:#9a3412;padding:.75rem 1rem;margin-bottom:1rem;font-size:.86rem}.disbursement-card{border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:1rem;background:#fff}.disbursement-card h2{font-size:1rem;margin:0;padding:.85rem 1rem .15rem}.disbursement-card>p{margin:0;padding:0 1rem .8rem;color:#64748b;font-size:.8rem}.disbursement-card table,.disbursement-blocked table{width:100%;border-collapse:collapse;font-size:.84rem}.disbursement-card th,.disbursement-blocked th{background:#f8fafc;color:#475569;padding:.58rem .7rem;text-align:left;text-transform:uppercase;letter-spacing:.04em;font-size:.67rem}.disbursement-card td,.disbursement-blocked td{padding:.58rem .7rem;border-top:1px solid #edf2f7}.disbursement-card td small{display:block;color:#64748b;font-size:.72rem}.disbursement-card .number,.disbursement-blocked .number{text-align:right;font-variant-numeric:tabular-nums}.disbursement-card tfoot td{background:#f8fafc;font-weight:800}.disbursement-table-wrap{overflow:auto}.disbursement-card .account{font-family:monospace;letter-spacing:.04em}.disbursement-card .missing{font-family:inherit;letter-spacing:0;color:#be123c;font-weight:800}.disbursement-card .net{font-weight:800;color:#166534}.disbursement-page footer{color:#64748b;font-size:.74rem;margin-top:.75rem}
@media(max-width:800px){.disbursement-header{flex-direction:column}.disbursement-actions{justify-content:flex-start}.disbursement-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media print{@page{size:landscape;margin:8mm}.no-print,.sidebar,.topbar,.mobile-toggle,.overlay{display:none!important}.app,.main,.content{display:block!important;margin:0!important;padding:0!important;width:auto!important}.disbursement-page{max-width:none}.disbursement-header{margin-bottom:4mm}.disbursement-header h1{font-size:17pt}.disbursement-forwarding{padding:2.5mm;margin-bottom:4mm}.disbursement-stats{gap:3mm;margin-bottom:4mm}.disbursement-stats div{padding:2.5mm}.disbursement-card{break-inside:avoid}.disbursement-card table{font-size:8pt}.disbursement-card th,.disbursement-card td{padding:2mm}.disbursement-card thead{display:table-header-group}.disbursement-card tr{break-inside:avoid}}
</style>
<script>
function copyForwardingNote() {
    var note = document.getElementById('forwardingNote');
    if (!note || !navigator.clipboard) return;
    navigator.clipboard.writeText(note.textContent.trim());
}
</script>
