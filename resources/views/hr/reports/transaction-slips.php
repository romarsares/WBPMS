<?php
/**
 * View: hr/reports/transaction-slips  (GET /hr/reports/transaction-slips)
 *
 * Generates printable BDO Cash Transaction Slips.
 * Layout matches the official BDO Network Bank slip image exactly:
 *   - Landscape A5 (210mm × 148mm)
 *   - Left panel (~58%): blue header + checkboxes + fields + footer
 *   - Right panel (~42%): light blue, Date + denomination grid + Total Amount
 *
 * Variables injected by ReportsController::transactionSlips():
 *   array  $slips            — rows from payroll + bank_details join
 *   array  $branches         — for filter dropdown
 *   array  $employees        — for filter dropdown
 *   array  $periods          — approved payroll periods for dropdown
 *   array  $filters          — current filter values
 *   int    $missingBankCount — number of slips with no bank account on file
 *   bool   $printMode        — true when ?print=1 (hides UI chrome)
 *   string $base
 */

use Wbpms\Http\View\Formatter;

$e = static fn(string $s): string => Formatter::escape($s);

$slips            ??= [];
$branches         ??= [];
$employees        ??= [];
$periods          ??= [];
$filters          ??= ['payroll_period_id' => 0, 'branch_id' => 0, 'employee_id' => 0];
$missingBankCount ??= 0;
$printMode        ??= false;
$base             ??= '';
?>

<?php if (!$printMode): ?>
<!-- ================================================================
     SCREEN MODE
     ================================================================ -->
<div class="page-head">
    <div>
        <h1>Transaction Slips</h1>
        <p>Generate printable BDO Cash Transaction Slips for an approved payroll period.</p>
    </div>
    <?php if (!empty($slips)): ?>
    <a href="<?= $e($base) ?>/hr/reports/transaction-slips?<?= http_build_query(array_merge($filters, ['print' => '1'])) ?>"
       target="_blank" class="btn btn-primary">🖨 Print Slips</a>
    <?php endif; ?>
</div>

<?php if ($missingBankCount > 0): ?>
<div class="alert info" role="alert" style="margin-bottom:1rem">
    <strong>⚠ <?= (int) $missingBankCount ?> employee(s) have no bank account on file.</strong>
    Their slips will print with blank account fields.
    Add bank account details through Employee Management to auto-fill these fields.
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.25rem">
    <form method="GET" action="<?= $e($base) ?>/hr/reports/transaction-slips"
          style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end">
        <div style="flex:1;min-width:180px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                Payroll Period <span style="color:var(--bad)">*</span>
            </label>
            <select name="payroll_period_id" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:4px;font-size:13px">
                <option value="">— Select period —</option>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int) $p['payroll_period_id'] ?>"
                        <?= (int) $filters['payroll_period_id'] === (int) $p['payroll_period_id'] ? 'selected' : '' ?>>
                        <?= $e($p['period_start']) ?> – <?= $e($p['period_end']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:160px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Branch</label>
            <select name="branch_id" style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:4px;font-size:13px">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['branch_id'] ?>"
                        <?= (int) $filters['branch_id'] === (int) $b['branch_id'] ? 'selected' : '' ?>>
                        <?= $e($b['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:180px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Employee</label>
            <select name="employee_id" style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:4px;font-size:13px">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int) $emp['employee_id'] ?>"
                        <?= (int) $filters['employee_id'] === (int) $emp['employee_id'] ? 'selected' : '' ?>>
                        <?= $e($emp['employee_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="padding:8px 20px;font-size:13px">
                Generate Slips
            </button>
        </div>
    </form>
</div>

<?php if ($filters['payroll_period_id'] === 0): ?>
    <div class="card" style="text-align:center;padding:2.5rem;color:var(--ink-2)">
        <p style="font-size:1.1rem;margin:0">Select a payroll period above to generate transaction slips.</p>
        <?php if (empty($periods)): ?>
        <p style="margin-top:.5rem;font-size:.875rem">No approved payroll runs found. Payroll must be approved before slips can be generated.</p>
        <?php endif; ?>
    </div>
<?php elseif (empty($slips)): ?>
    <div class="card" style="text-align:center;padding:2.5rem;color:var(--ink-2)">
        <p>No payroll records found for the selected filters.</p>
    </div>
<?php else: ?>
<div class="card" style="margin-bottom:1rem;padding:0">
    <div style="padding:12px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
        <strong><?= count($slips) ?> slip(s) found</strong>
        <a href="<?= $e($base) ?>/hr/reports/transaction-slips?<?= $e(http_build_query(array_merge($filters, ['print' => '1']))) ?>"
           target="_blank" class="btn btn-primary" style="font-size:12px;padding:6px 14px">
            🖨 Open Print Preview
        </a>
    </div>
    <table class="data-table" style="font-size:13px">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Branch</th>
                <th>Period</th>
                <th>Net Pay</th>
                <th>Account Name</th>
                <th>Account No.</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($slips as $slip): ?>
        <tr>
            <td>
                <div style="font-weight:600"><?= $e($slip['employee_name']) ?></div>
                <div style="font-size:11px;color:var(--ink-2)"><?= $e($slip['employee_number']) ?></div>
            </td>
            <td><?= $e($slip['branch_name']) ?></td>
            <td style="font-size:11px"><?= $e($slip['period_start']) ?> – <?= $e($slip['period_end']) ?></td>
            <td style="font-weight:700">₱<?= number_format((float) $slip['net_pay'], 2) ?></td>
            <td><?= $slip['account_name'] ? $e($slip['account_name']) : '<span style="color:var(--bad);font-size:11px">Not on file</span>' ?></td>
            <td><?= $slip['account_number'] ? $e($slip['account_number']) : '<span style="color:var(--bad);font-size:11px">Not on file</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ================================================================
     PRINT MODE — BDO Cash Transaction Slip
     Landscape A5 (210mm × 148mm) matching the official slip image.
     Left panel: blue header + checkboxes + fields
     Right panel: light blue, Date + denomination grid
     ================================================================ -->
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BDO Cash Transaction Slips — <?= $e(date('m/d/Y')) ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
    background: #e8e8e8;
    color: #000;
}

/* ── Screen toolbar ─────────────────────────── */
.no-print {
    background: #1a3a60;
    color: #f0f6ff;
    padding: 7px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    position: sticky;
    top: 0;
    z-index: 100;
}
.no-print button {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 5px 16px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 700;
}
.no-print a { color: #93c5fd; font-size: 11px; text-decoration: none; }

/* ── Slip container ──────────────────────────
   A5 landscape: exactly 210mm × 148mm
─────────────────────────────────────────────── */
.slip {
    width: 210mm;
    height: 148mm;
    margin: 8mm auto;
    background: #fff;
    border: 1px solid #999;
    display: flex;
    flex-direction: row;
    page-break-after: always;
    page-break-inside: avoid;
    overflow: hidden;
}
.slip:last-child { page-break-after: auto; }

/* ==============================================
   LEFT PANEL  (58% width, white)
   ============================================== */
.lp {
    width: 58%;
    display: flex;
    flex-direction: column;
    border-right: 1.5pt solid #1a56a0;
}

/* Blue header bar (full width of left panel) */
.lp-header {
    background: #1a56a0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 3mm 5mm 3mm 4mm;
    flex-shrink: 0;
}
.bdo-brand {
    display: flex;
    align-items: baseline;
    gap: 2mm;
}
.bdo-logo {
    color: #fff;
    font-size: 19pt;
    font-weight: 900;
    letter-spacing: .02em;
    line-height: 1;
}
.bdo-net {
    color: #d0e8ff;
    font-size: 9.5pt;
    font-weight: 600;
    letter-spacing: .02em;
}
.lp-title {
    color: #fff;
    font-size: 11pt;
    font-weight: 700;
    letter-spacing: .04em;
}

/* Body row: checkbox column + fields column */
.lp-body {
    display: flex;
    flex-direction: row;
    flex: 1;
    min-height: 0;
    padding: 2.5mm 3mm 1.5mm 3mm;
    gap: 2.5mm;
}

/* ── Checkbox column (left strip) ─────────── */
.cb-col {
    width: 29mm;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: .7mm;
    font-size: 7pt;
    color: #1a56a0;
}
.ci {                        /* checkbox item */
    display: flex;
    align-items: flex-start;
    gap: 1.5mm;
    line-height: 1.3;
}
.ci.i1 { padding-left: 4mm; }   /* 1-level indent */
.ci.i2 { padding-left: 8mm; }   /* 2-level indent */
.ci.inline { flex-direction: row; align-items: center; gap: 2.5mm; }
.sq {
    width: 2.8mm;
    height: 2.8mm;
    border: .8pt solid #1a56a0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 5.5pt;
    font-weight: 900;
    flex-shrink: 0;
    margin-top: .2mm;
    background: #fff;
    color: #1a56a0;
}
.sq.on { background: #1a56a0; color: #fff; }
.gap { height: 2.5mm; }       /* visual spacer between groups */
.mv {                         /* Machine Validation */
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 1.5mm;
    font-size: 6.5pt;
    color: #555;
    padding-top: 1mm;
}

/* ── Fields column ────────────────────────── */
.fc {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0;
    min-width: 0;
}
.fr {                         /* field row */
    display: flex;
    flex-direction: row;
    gap: 2.5mm;
    margin-bottom: 1.2mm;
}
.fg {                         /* field group */
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.fl {                         /* field label */
    font-size: 6.5pt;
    color: #1a56a0;
    margin-bottom: .3mm;
    white-space: nowrap;
}
.fv {                         /* field value line */
    border-bottom: .8pt solid #1a56a0;
    min-height: 5mm;
    font-size: 8pt;
    font-weight: 700;
    padding: 0 1mm .4mm 1mm;
    line-height: 1.3;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.fv.empty { color: #bbb; font-weight: 400; }

/* Institution Code tick-mark line */
.inst-line {
    border-bottom: .8pt solid #1a56a0;
    min-height: 5mm;
    display: flex;
    align-items: flex-end;
    padding-bottom: .4mm;
    gap: 1.3mm;
    padding-left: .5mm;
}
.tick {
    width: 5mm;
    height: 3mm;
    border-top: .6pt solid #1a56a0;
}

/* Footer "This serves as your receipt…" */
.lp-footer {
    flex-shrink: 0;
    text-align: center;
    font-size: 6pt;
    color: #666;
    font-style: italic;
    padding: 1.5mm 3mm 1.5mm;
    border-top: .4pt solid #ddd;
}

/* ==============================================
   RIGHT PANEL (42% width, light blue)
   ============================================== */
.rp {
    flex: 1;
    background: #d8ecfb;
    display: flex;
    flex-direction: column;
    padding: 2.5mm 3mm 2mm 3mm;
}

.rp-date {
    display: flex;
    align-items: center;
    gap: 2mm;
    margin-bottom: 2mm;
}
.rp-date-label {
    font-size: 7.5pt;
    color: #1a56a0;
    font-weight: 700;
    white-space: nowrap;
}
.rp-date-val {
    flex: 1;
    border-bottom: .8pt solid #1a56a0;
    min-height: 4.5mm;
    font-size: 8pt;
    font-weight: 700;
    padding: 0 1mm .3mm;
}

.rp-note {
    font-size: 6.5pt;
    color: #1a56a0;
    font-weight: 600;
    margin-bottom: 2mm;
    line-height: 1.3;
}

/* Denomination table */
.dt {
    width: 100%;
    border-collapse: collapse;
    flex: 1;
}
.dt thead th {
    font-size: 7pt;
    font-weight: 700;
    color: #1a56a0;
    text-align: center;
    border-bottom: 1pt solid #1a56a0;
    padding: .5mm 1mm;
}
.dt thead th:first-child { text-align: left; }
.dt tbody td {
    height: 8mm;
    border-bottom: .5pt solid #8bb8d8;
    padding: 0 1.5mm;
    vertical-align: middle;
    font-size: 7.5pt;
}
.dt tbody td.pc {            /* Pieces column */
    text-align: center;
    border-left: .8pt solid #8bb8d8;
    border-right: .8pt solid #8bb8d8;
}
.dt tbody td.am {            /* Amount column */
    text-align: right;
}
.dt tfoot tr td {
    border-top: 1.5pt solid #1a56a0;
    border-bottom: 1pt solid #1a56a0;
    height: 10mm;
    background: #b8d8f0;
    padding: 0 1.5mm;
    vertical-align: middle;
}
.dt tfoot td.tl {            /* "Total Amount" label */
    text-align: right;
    font-size: 8pt;
    font-weight: 700;
    color: #1a56a0;
    padding-right: 2mm;
}
.dt tfoot td.ta {            /* total amount value */
    text-align: right;
    font-size: 9.5pt;
    font-weight: 700;
    color: #1a56a0;
    border-left: .8pt solid #8bb8d8;
    white-space: nowrap;
}

/* ── Print ──────────────────────────────────── */
@media print {
    .no-print { display: none !important; }
    body       { background: #fff; margin: 0; }
    .slip      { margin: 0; border: .5pt solid #aaa; }
    @page      { size: A5 landscape; margin: 3mm; }
}
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">🖨 Print All Slips (<?= count($slips) ?>)</button>
    <a href="javascript:history.back()">← Back</a>
    <span style="margin-left:auto;opacity:.7">
        <?= count($slips) ?> slip(s) &nbsp;·&nbsp; <?= $e(date('m/d/Y')) ?>
    </span>
</div>

<?php foreach ($slips as $slip):
    $hasBankDetails = !empty($slip['account_number']);
    $accountName    = !empty($slip['account_name'])   ? $slip['account_name']   : '';
    $accountNo      = $hasBankDetails                 ? $slip['account_number'] : '';
    $accountType    = !empty($slip['account_type'])   ? strtolower($slip['account_type']) : 'savings';
    $isSavings      = $accountType === 'savings';
    $isCurrent      = $accountType === 'current';
    $referenceNo    = 'PR-' . date('Ymd', strtotime($slip['period_start']))
                      . '-' . str_pad((string) $slip['employee_id'], 4, '0', STR_PAD_LEFT);
    $netPay         = number_format((float) $slip['net_pay'], 2);
    $periodLabel    = $slip['period_start'] . ' – ' . $slip['period_end'];
?>
<div class="slip">

    <!-- ============ LEFT PANEL ============ -->
    <div class="lp">

        <!-- Blue header -->
        <div class="lp-header">
            <div class="bdo-brand">
                <span class="bdo-logo">BDO</span>
                <span class="bdo-net">Network Bank</span>
            </div>
            <span class="lp-title">Cash Transaction Slip</span>
        </div>

        <!-- Body -->
        <div class="lp-body">

            <!-- Checkboxes -->
            <div class="cb-col">
                <div class="ci">
                    <span class="sq on">✓</span><span>Deposits</span>
                </div>
                <div class="ci i1">
                    <span class="sq <?= $isCurrent ? 'on' : '' ?>"></span><span>Current</span>
                </div>
                <div class="ci i1">
                    <span class="sq <?= $isSavings ? 'on' : '' ?>"><?= $isSavings ? '✓' : '' ?></span><span>Savings</span>
                </div>
                <div class="ci i1">
                    <span class="sq"></span><span>Time Deposit /<br>Placement</span>
                </div>
                <div class="ci i2" style="margin-top:.5mm">
                    <span class="sq"></span><span>For Account with<br>Deposit Reference<br>Facility</span>
                </div>

                <div class="gap"></div>
                <div class="ci">
                    <span class="sq"></span><span>Bills payment</span>
                </div>

                <div class="gap"></div>
                <div class="ci">
                    <span class="sq"></span><span>Payment</span>
                </div>
                <div class="ci i1 inline">
                    <span class="sq"></span><span>Loan</span>
                    <span class="sq" style="margin-left:1mm"></span><span>Trade</span>
                </div>

                <div class="mv">
                    <span class="sq" style="border-color:#999"></span>
                    <span>Machine Validation</span>
                </div>
            </div>

            <!-- Fields -->
            <div class="fc">

                <!-- Account Name -->
                <div class="fr">
                    <div class="fg">
                        <div class="fl">Account Name</div>
                        <div class="fv <?= $accountName ? '' : 'empty' ?>">
                            <?= $accountName ? $e($accountName) : '&nbsp;' ?>
                        </div>
                    </div>
                </div>

                <!-- Account No -->
                <div class="fr">
                    <div class="fg">
                        <div class="fl">Account No.</div>
                        <div class="fv <?= $accountNo ? '' : 'empty' ?>">
                            <?= $accountNo ? $e($accountNo) : '&nbsp;' ?>
                        </div>
                    </div>
                </div>

                <!-- Payor's Name  |  Reference No -->
                <div class="fr">
                    <div class="fg">
                        <div class="fl">Payor's Name</div>
                        <div class="fv"><?= $e($slip['employee_name']) ?></div>
                    </div>
                    <div class="fg">
                        <div class="fl">Reference No.</div>
                        <div class="fv" style="font-size:7pt"><?= $e($referenceNo) ?></div>
                    </div>
                </div>

                <!-- Company Name  |  Institution Code  |  Product Code -->
                <div class="fr">
                    <div class="fg" style="flex:2">
                        <div class="fl">Company Name</div>
                        <div class="fv">Light Diamond Enterprises</div>
                    </div>
                    <div class="fg" style="flex:1.2">
                        <div class="fl">Institution Code</div>
                        <div class="inst-line">
                            <div class="tick"></div>
                            <div class="tick"></div>
                            <div class="tick"></div>
                            <div class="tick"></div>
                        </div>
                    </div>
                    <div class="fg" style="flex:.9">
                        <div class="fl">Product Code</div>
                        <div class="fv empty">&nbsp;</div>
                    </div>
                </div>

                <!-- Subscriber's Name  |  Subscriber's Account No -->
                <div class="fr">
                    <div class="fg">
                        <div class="fl">Subscriber's Name</div>
                        <div class="fv empty">&nbsp;</div>
                    </div>
                    <div class="fg">
                        <div class="fl">Subscriber's Account No.</div>
                        <div class="fv empty">&nbsp;</div>
                    </div>
                </div>

                <!-- Borrower's Name  |  Promissory Note No / Trade Reference No -->
                <div class="fr">
                    <div class="fg">
                        <div class="fl">Borrower's Name</div>
                        <div class="fv empty">&nbsp;</div>
                    </div>
                    <div class="fg">
                        <div class="fl">Promissory Note No. / Trade Reference No.</div>
                        <div class="fv empty">&nbsp;</div>
                    </div>
                </div>

            </div><!-- /.fc -->
        </div><!-- /.lp-body -->

        <div class="lp-footer">
            This serves as your receipt when machine validated.
        </div>

    </div><!-- /.lp -->

    <!-- ============ RIGHT PANEL ============ -->
    <div class="rp">

        <div class="rp-date">
            <span class="rp-date-label">Date</span>
            <div class="rp-date-val"><?= $e(date('m/d/Y')) ?></div>
        </div>

        <div class="rp-note">Use separate slip(s) for each type of transaction.</div>

        <table class="dt">
            <thead>
                <tr>
                    <th>Denomination</th>
                    <th>Pieces</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <!-- First row: payroll deposit amount -->
                <tr>
                    <td style="font-size:7pt;color:#1a56a0;font-weight:600">Payroll</td>
                    <td class="pc">1</td>
                    <td class="am" style="font-weight:700">₱ <?= $e($netPay) ?></td>
                </tr>
                <!-- Remaining rows blank for teller use -->
                <?php for ($i = 0; $i < 6; $i++): ?>
                <tr>
                    <td>&nbsp;</td>
                    <td class="pc">&nbsp;</td>
                    <td class="am">&nbsp;</td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="tl">Total Amount</td>
                    <td class="ta">₱ <?= $e($netPay) ?></td>
                </tr>
            </tfoot>
        </table>

    </div><!-- /.rp -->

</div><!-- /.slip -->
<?php endforeach; ?>

</body>
</html>
<?php
// Print mode outputs its own full HTML document; stop layout.php from wrapping it.
exit;
endif; // end printMode
?>
