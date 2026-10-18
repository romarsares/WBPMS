<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * View: hr/requests/leave-credits-employee  (GET /hr/requests/leave-credits/{id})
 *
 * @var array{employee_id:int,employee_number:string,employee_name:string} $employee
 * @var float   $balance
 * @var list<array{entry_id:int,entry_type:string,days_delta:float,notes:string,created_at:string,recorded_by_name:string}> $ledger
 * @var int     $year
 * @var list<string> $errors
 */

$employee ??= [];
$balance  ??= 0.0;
$ledger   ??= [];
$year     ??= (int) date('Y');
$errors   ??= [];
?>

<div class="page-header">
    <div>
        <h1>Leave Credits — <?= Formatter::escape($employee['employee_name'] ?? '') ?></h1>
        <p style="font-size:.875rem;color:#6b7280">
            <?= Formatter::escape($employee['employee_number'] ?? '') ?> &nbsp;·&nbsp; <?= $year ?> sick leave
        </p>
    </div>
    <a href="<?= $base ?>/hr/requests/leave-credits?year=<?= $year ?>" class="btn btn-secondary">← Back to All Employees</a>
</div>

<?php if ($errors !== []): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:.75rem 1rem;margin-bottom:1.25rem;color:#991b1b">
    <?php foreach ($errors as $err): ?>
    <p style="margin:0"><?= Formatter::escape($err) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

    <!-- Balance card + ledger -->
    <div>
        <!-- Balance summary -->
        <div class="card" style="padding:1.25rem;margin-bottom:1rem">
            <div style="font-size:.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">
                Current Balance (<?= $year ?>)
            </div>
            <div style="font-size:2.5rem;font-weight:700;color:<?= $balance > 0 ? '#059669' : '#dc2626' ?>">
                <?= number_format($balance, 2) ?>
                <span style="font-size:1rem;font-weight:400;color:#6b7280">/ 4.00 days</span>
            </div>
            <?php
            $pct = min(100, max(0, ($balance / 4.0) * 100));
            $bar = $pct > 50 ? '#10b981' : ($pct > 25 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-top:.75rem">
                <div style="height:100%;width:<?= round($pct) ?>%;background:<?= $bar ?>;border-radius:4px"></div>
            </div>
        </div>

        <!-- Ledger history -->
        <div class="card" style="padding:0;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb">
                <strong style="font-size:.875rem">Ledger History</strong>
            </div>
            <?php if ($ledger === []): ?>
            <p style="padding:1.5rem;color:#6b7280;font-size:.875rem;margin:0">No ledger entries yet.</p>
            <?php else: ?>
            <table class="data-table" style="font-size:.82rem">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th style="text-align:right">Days</th>
                        <th>Notes</th>
                        <th>Recorded By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ledger as $entry):
                    $isCredit = (float) $entry['days_delta'] > 0;
                    $typeColor = match ($entry['entry_type']) {
                        'Grant'      => '#059669',
                        'Adjustment' => '#6366f1',
                        'Usage'      => '#dc2626',
                        default      => '#6b7280',
                    };
                ?>
                <tr>
                    <td>
                        <span style="background:<?= $typeColor ?>;color:#fff;padding:1px 7px;border-radius:9999px;font-size:.72rem">
                            <?= Formatter::escape($entry['entry_type']) ?>
                        </span>
                    </td>
                    <td style="text-align:right;font-weight:600;color:<?= $isCredit ? '#059669' : '#dc2626' ?>">
                        <?= $isCredit ? '+' : '' ?><?= number_format((float) $entry['days_delta'], 2) ?>
                    </td>
                    <td style="color:#374151"><?= Formatter::escape($entry['notes']) ?></td>
                    <td style="color:#6b7280"><?= Formatter::escape($entry['recorded_by_name']) ?></td>
                    <td style="color:#6b7280;white-space:nowrap"><?= Formatter::escape($entry['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Adjustment form -->
    <div class="card" style="padding:1.25rem">
        <h3 style="margin:0 0 .25rem;font-size:1rem">Manual Adjustment</h3>
        <?php
        $maxGrant = round(max(0, 4.00 - (float) $balance), 2);
        ?>
        <p style="font-size:.8rem;color:#6b7280;margin:0 0 1rem">
            Current balance: <strong><?= number_format((float) $balance, 2) ?> / 4.00 days</strong>
            <?php if ($maxGrant > 0): ?>
            &nbsp;·&nbsp; Max grantable: <strong style="color:#059669"><?= $maxGrant ?> days</strong>
            <?php else: ?>
            &nbsp;·&nbsp; <strong style="color:#dc2626">Balance is at the 4-day limit.</strong>
            <?php endif; ?>
        </p>
        <form method="POST"
              action="<?= $base ?>/hr/requests/leave-credits/<?= (int) $employee['employee_id'] ?>/adjust">
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <input type="hidden" name="year"  value="<?= $year ?>">

            <div style="margin-bottom:1rem">
                <label style="font-size:.85rem;font-weight:500;display:block;margin-bottom:.3rem">
                    Entry Type <span style="color:#ef4444">*</span>
                </label>
                <select name="entry_type" required
                        style="width:100%;padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                    <option value="">— Select —</option>
                    <option value="Grant">Grant — add credits (e.g. annual grant, correction)</option>
                    <option value="Adjustment">Adjustment — add credits (HR discretion)</option>
                    <option value="Usage">Usage — deduct credits (manual deduction)</option>
                </select>
            </div>

            <div style="margin-bottom:1rem">
                <label style="font-size:.85rem;font-weight:500;display:block;margin-bottom:.3rem">
                    Days <span style="color:#ef4444">*</span>
                </label>
                <input type="number" name="days" min="0.5" step="0.5" required
                       placeholder="e.g. 1 or 0.5"
                       style="width:100%;padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <small style="color:#6b7280">Minimum 0.5 (half day). Always enter a positive number — for Usage, the system deducts automatically.</small>
            </div>

            <div style="margin-bottom:1.25rem">
                <label style="font-size:.85rem;font-weight:500;display:block;margin-bottom:.3rem">
                    Reason / Notes <span style="color:#ef4444">*</span>
                </label>
                <textarea name="notes" rows="3" required
                          placeholder="Required — briefly explain the reason for this adjustment"
                          style="width:100%;padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem;resize:vertical"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%"
                    onclick="return confirm('Apply this leave credit adjustment?')">
                Apply Adjustment
            </button>
        </form>
    </div>

</div>
