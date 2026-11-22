<?php

use Wbpms\Http\View\Formatter;

/**
 * Employee portal — Payslips list.
 *
 * @var list<array{
 *     id: int,
 *     period_label: string,
 *     branch_name: string,
 *     gross_pay: string,
 *     total_deductions: string,
 *     net_pay: string,
 *     approved_at: string
 * }> $payslips
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var int $pageSize
 */

$payslips ??= [];
$page ??= 1;
$totalPages ??= 1;
$pageSize ??= 50;
$total ??= 0;
?>
<div class="page-header">
    <h1>My Payslips</h1>
</div>

<!-- Per-page selector -->
<div class="card" style="padding:.75rem 1.25rem;margin-bottom:1rem">
    <form method="GET" action="<?= $base ?>/employee/payslips" style="display:flex;gap:.75rem;align-items:flex-end">
        <div style="display:flex;align-items:flex-end;margin-left:auto">
            <label style="font-size:.8rem;font-weight:500;display:block;margin-bottom:.25rem">Per page</label>
            <select name="page_size" style="padding:.45rem .7rem;border:1px solid #d1d5db;border-radius:4px;font-size:.875rem">
                <option value="10" <?= $pageSize === 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $pageSize === 20 ? 'selected' : '' ?>>20</option>
                <option value="30" <?= $pageSize === 30 ? 'selected' : '' ?>>30</option>
                <option value="50" <?= $pageSize === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $pageSize === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    </form>
</div>

<?php if (empty($payslips)): ?>
<div class="card" style="text-align:center;padding:2.5rem">
    <p style="color:#6b7280;font-size:.95rem;margin:0">
        No payslips available yet. Payslips appear here once a payroll run is approved.
    </p>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Branch</th>
                <th style="text-align:right">Gross Pay</th>
                <th style="text-align:right">Deductions</th>
                <th style="text-align:right;font-weight:700">Net Pay</th>
                <th>Approved</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($payslips as $ps): ?>
        <tr>
            <td style="font-weight:500"><?= Formatter::escape($ps['period_label']) ?></td>
            <td><?= Formatter::escape($ps['branch_name']) ?></td>
            <td style="text-align:right">₱<?= Formatter::escape($ps['gross_pay']) ?></td>
            <td style="text-align:right;color:#dc2626">₱<?= Formatter::escape($ps['total_deductions']) ?></td>
            <td style="text-align:right;font-weight:700">₱<?= Formatter::escape($ps['net_pay']) ?></td>
            <td><?= Formatter::date($ps['approved_at']) ?></td>
            <td style="white-space:nowrap">
                <a href="<?= $base ?>/employee/payslips/<?= (int) $ps['id'] ?>"
                   class="btn btn-secondary btn-sm">View</a>
                <a href="<?= $base ?>/employee/payslips/<?= (int) $ps['id'] ?>/download"
                   class="btn btn-secondary btn-sm" style="margin-left:.35rem">Download</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="card" style="padding:1rem 1.25rem;margin-top:1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem">
    <div style="font-size:.875rem;color:#374151">
        Showing
        <strong><?= (int) (($page - 1) * $pageSize + 1) ?></strong>
        to
        <strong><?= (int) min($page * $pageSize, $total) ?></strong>
        of
        <strong><?= (int) $total ?></strong>
        payslips
    </div>
    <nav aria-label="Payslips pagination">
        <ul style="display:flex;gap:.25rem;list-style:none;margin:0;padding:0">
            <!-- Previous -->
            <?php if ($page > 1): ?>
            <li>
                <a href="<?= $base ?>/employee/payslips?page=<?= $page - 1 ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Previous page">« Prev</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">« Prev</span>
            </li>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1): ?>
            <li>
                <a href="<?= $base ?>/employee/payslips?page=1&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm">1</a>
            </li>
            <?php if ($start > 2): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <li>
                <?php if ($p === $page): ?>
                <span class="btn btn-primary btn-sm" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="<?= $base ?>/employee/payslips?page=<?= $p ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <li><span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:default">…</span></li>
            <?php endif; ?>
            <li>
                <a href="<?= $base ?>/employee/payslips?page=<?= $totalPages ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <li>
                <a href="<?= $base ?>/employee/payslips?page=<?= $page + 1 ?>&page_size=<?= $pageSize ?>"
                   class="btn btn-secondary btn-sm" aria-label="Next page">Next »</a>
            </li>
            <?php else: ?>
            <li>
                <span class="btn btn-secondary btn-sm" style="opacity:.5;cursor:not-allowed" aria-disabled="true">Next »</span>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
<?php endif; ?>