<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * HR — Employee detail (read-only).
 *
 * @var array<string, mixed> $employee
 * @var list<array<string,mixed>> $branchHistory
 * @var list<array<string,mixed>> $salaryHistory
 * @var int                  $currentDocCount
 * @var string               $base
 * @var string               $csrf
 */

$employee        ??= [];
$branchHistory   ??= [];
$salaryHistory   ??= [];
$currentDocCount ??= 0;

$empId   = (int) $employee['id'];
$empName = Formatter::escape($employee['last_name'] . ', ' . $employee['first_name']);

$statusBadge = match (strtolower((string) $employee['status'])) {
    'active'    => '<span class="badge badge-green">Active</span>',
    'inactive'  => '<span class="badge badge-yellow">Inactive</span>',
    'separated' => '<span class="badge badge-orange">Separated</span>',
    'archived'  => '<span class="badge badge-gray">Archived</span>',
    default     => '<span class="badge badge-gray">' . Formatter::escape((string) $employee['status']) . '</span>',
};

$field = static function (string $label, ?string $value, bool $mono = false) use ($employee): string {
    $display = ($value !== null && $value !== '' && $value !== '0' && $value !== '0.00')
        ? ($mono
            ? '<code style="font-size:.85rem">' . Formatter::escape($value) . '</code>'
            : Formatter::escape($value))
        : '<span class="muted">—</span>';
    return '<div class="detail-field"><span class="detail-label">' . $label . '</span>'
         . '<span class="detail-value">' . $display . '</span></div>';
};

$formatDate = static fn(?string $d): ?string =>
    ($d && $d !== '—') ? date('m/d/Y', strtotime($d)) : null;

$formatMoney = static fn(mixed $v): string =>
    '₱' . number_format((float) $v, 2);
?>

<div class="page-header">
    <div>
        <h1 style="margin-bottom:.2rem"><?= $empName ?></h1>
        <span style="font-size:.85rem;color:#6b7280"><?= Formatter::escape((string) $employee['employee_number']) ?></span>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <a href="<?= $base ?>/hr/employees" class="btn btn-secondary">← Employees</a>
        <a href="<?= $base ?>/hr/employees/<?= $empId ?>/edit"     class="btn btn-primary">Edit</a>
        <a href="<?= $base ?>/hr/employees/<?= $empId ?>/documents" class="btn btn-secondary">
            Documents<?= $currentDocCount > 0 ? ' <span class="doc-badge">' . $currentDocCount . '</span>' : '' ?>
        </a>
        <?php $st = strtolower((string) $employee['status']); ?>
        <?php if ($st === 'active' || $st === 'inactive' || $st === 'separated'): ?>
        <a href="<?= $base ?>/hr/employees/<?= $empId ?>/transfer" class="btn btn-warning">Transfer</a>
        <a href="<?= $base ?>/hr/employees/<?= $empId ?>/archive"  class="btn btn-secondary">Archive</a>
        <?php elseif ($st === 'archived'): ?>
        <a href="<?= $base ?>/hr/employees/<?= $empId ?>/rehire"   class="btn btn-primary">Rehire</a>
        <?php endif; ?>
    </div>
</div>

<!-- ------------------------------------------------------------------ -->
<!-- Status banner for non-active employees                              -->
<!-- ------------------------------------------------------------------ -->
<?php if ($st !== 'active'): ?>
<div class="flash flash-<?= $st === 'inactive' ? 'warning' : 'error' ?>"
     style="margin-bottom:1.25rem" role="alert">
    This employee is currently <strong><?= Formatter::escape(ucfirst($st)) ?></strong>
    and is not eligible for new payroll, schedules, or attendance imports.
</div>
<?php endif; ?>

<!-- ================================================================== -->
<!-- Grid: Personal + Employment + Gov't IDs                            -->
<!-- ================================================================== -->
<div class="detail-grid">

    <!-- Personal information -->
    <div class="card detail-section">
        <h2>Personal Information</h2>
        <?= $field('Last name',      $employee['last_name']) ?>
        <?= $field('First name',     $employee['first_name']) ?>
        <?= $field('Middle initial', $employee['middle_initial']) ?>
        <?= $field('Birthdate',      $formatDate($employee['birthdate'])) ?>
        <?= $field('Contact',        $employee['contact_number']) ?>
        <?= $field('Email',          $employee['email']) ?>
    </div>

    <!-- Employment details -->
    <div class="card detail-section">
        <h2>Employment</h2>
        <?= $field('Employee no.', $employee['employee_number'], true) ?>
        <?= $field('Type',         $employee['employee_type']) ?>
        <?= $field('Position',     $employee['position']) ?>
        <?= $field('Hire date',    $formatDate($employee['hire_date'])) ?>
        <?= $field('Status',       null) ?>
        <div class="detail-field" style="margin-top:-.5rem">
            <span class="detail-label"></span>
            <span class="detail-value"><?= $statusBadge ?></span>
        </div>
        <?php if ($employee['contract_review_date']): ?>
        <?= $field('Contract review due', $formatDate($employee['contract_review_date'])) ?>
        <?php endif; ?>
    </div>

    <!-- Government IDs -->
    <div class="card detail-section">
        <h2>Government IDs</h2>
        <?= $field('SSS',         $employee['sss_number'],        true) ?>
        <?= $field('PhilHealth',  $employee['philhealth_number'], true) ?>
        <?= $field('Pag-IBIG',    $employee['pagibig_number'],    true) ?>
        <?= $field('TIN',         $employee['tin_number'],        true) ?>
    </div>

    <!-- Assignment & Pay -->
    <div class="card detail-section">
        <h2>Assignment &amp; Pay</h2>
        <?= $field('Branch',      $employee['branch_name']) ?>
        <?= $field('Branch since',$formatDate($employee['branch_since'])) ?>
        <?= $field('Schedule',    $employee['schedule_name']) ?>
        <?php if ($employee['work_start_time'] && $employee['work_end_time']): ?>
        <?= $field('Hours',       $employee['work_start_time'] . ' – ' . $employee['work_end_time']) ?>
        <?php endif; ?>
        <?= $field('Daily rate',  $formatMoney($employee['daily_rate'])) ?>
        <?= $field('Rate since',  $formatDate($employee['salary_since'])) ?>
    </div>

    <!-- Biometric & account -->
    <div class="card detail-section">
        <h2>Biometric &amp; Login</h2>
        <?= $field('Device',       $employee['device_name']) ?>
        <?= $field('Enroll ID',    $employee['enrollment_code'], true) ?>
        <?php if ($employee['user_id']): ?>
        <?= $field('Username',     $employee['username'], true) ?>
        <?= $field('Account',      ucfirst((string) $employee['account_status'])) ?>
        <?php if ((bool) $employee['requires_password_change']): ?>
        <div class="detail-field">
            <span class="detail-label"></span>
            <span class="detail-value">
                <span class="badge badge-yellow">Password change required</span>
            </span>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="detail-field">
            <span class="detail-label">Login account</span>
            <span class="detail-value muted">Not linked</span>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /detail-grid -->

<!-- ================================================================== -->
<!-- Branch history                                                      -->
<!-- ================================================================== -->
<?php if ($branchHistory !== []): ?>
<h2 class="section-heading">Branch History</h2>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
    <table class="data-table" style="margin:0">
        <thead>
            <tr>
                <th>Branch</th>
                <th>From</th>
                <th>To</th>
                <th>Transfer reason</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($branchHistory as $row): ?>
        <tr>
            <td><?= Formatter::escape((string) ($row['branch_name'] ?? '—')) ?></td>
            <td><?= Formatter::escape($formatDate((string) $row['effective_from']) ?? '—') ?></td>
            <td><?= $row['effective_to'] ? Formatter::escape($formatDate((string) $row['effective_to']) ?? '—') : '<span class="badge badge-green">Current</span>' ?></td>
            <td class="muted"><?= Formatter::escape((string) ($row['transfer_reason'] ?? '—')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ================================================================== -->
<!-- Salary history                                                      -->
<!-- ================================================================== -->
<?php if ($salaryHistory !== []): ?>
<h2 class="section-heading">Salary History</h2>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem">
    <table class="data-table" style="margin:0">
        <thead>
            <tr>
                <th>Daily rate</th>
                <th>Effective from</th>
                <th>Effective to</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($salaryHistory as $row): ?>
        <tr>
            <td><?= Formatter::escape($formatMoney($row['daily_rate'])) ?></td>
            <td><?= Formatter::escape($formatDate((string) $row['effective_from']) ?? '—') ?></td>
            <td><?= $row['effective_to']
                ? Formatter::escape($formatDate((string) $row['effective_to']) ?? '—')
                : '<span class="badge badge-green">Current</span>' ?></td>
            <td><?= Formatter::escape((string) ($row['status'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
/* Detail page layout */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.detail-section h2 {
    margin: 0 0 .9rem;
    font-size: .875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: .45rem;
}
.detail-field {
    display: flex;
    gap: .5rem;
    padding: .3rem 0;
    border-bottom: 1px solid #f3f4f6;
    align-items: baseline;
}
.detail-field:last-child { border-bottom: none; }
.detail-label {
    font-size: .78rem;
    font-weight: 600;
    color: #6b7280;
    min-width: 110px;
    flex-shrink: 0;
}
.detail-value {
    font-size: .875rem;
    color: #111827;
    word-break: break-word;
}
.section-heading {
    font-size: .9rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #374151;
    margin: 1.5rem 0 .6rem;
}
.data-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.data-table th {
    background: #f9fafb;
    padding: .5rem .75rem;
    text-align: left;
    font-size: .78rem;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}
.data-table td {
    padding: .5rem .75rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: top;
}
.data-table tbody tr:last-child td { border-bottom: none; }

/* Badges */
.badge { display:inline-block; padding:.15rem .55rem; border-radius:9999px; font-size:.75rem; font-weight:600; }
.badge-green  { background:#d1fae5; color:#065f46; }
.badge-yellow { background:#fef3c7; color:#92400e; }
.badge-orange { background:#ffedd5; color:#9a3412; }
.badge-gray   { background:#e5e7eb; color:#374151; }

/* Document count badge on button */
.doc-badge {
    display: inline-block;
    background: #3b82f6;
    color: #fff;
    border-radius: 9999px;
    font-size: .7rem;
    font-weight: 700;
    padding: .05rem .4rem;
    vertical-align: middle;
    margin-left: .2rem;
    line-height: 1.4;
}

/* Flash variants */
.flash-warning { background:#fef3c7; border-color:#fbbf24; color:#92400e; }
.flash-error   { background:#fee2e2; border-color:#f87171; color:#991b1b; }
</style>
