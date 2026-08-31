<?php

declare(strict_types=1);

use Wbpms\Http\View\Formatter;

/**
 * Main application layout.
 *
 * Variables injected by the controller via extract():
 * @var string      $title       Page title
 * @var string      $content     Pre-rendered page body (HTML)
 * @var string      $roleName    Authenticated role: 'HRHead'|'BusinessOwner'|'Employee'|''
 * @var string      $userName    Authenticated user display name
 * @var string|null $flash       One-time success flash message
 * @var string|null $flashError  One-time error flash message
 * @var string      $csrf        Raw CSRF token for the logout form
 */

$roleName   ??= '';
$userName   ??= '';
$flash      ??= null;
$flashError ??= null;
$csrf       ??= '';

$isHR    = $roleName === 'HRHead';
$isOwner = $roleName === 'BusinessOwner';
$isEmp   = $roleName === 'Employee';
$isAuth  = $roleName !== '';

// Active-link helper — marks the current nav anchor
$reqUri = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
$active = static fn(string $path): string =>
    ($reqUri === $path || str_starts_with((string) $reqUri, $path . '/'))
        ? ' class="active"' : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($csrf !== ''): ?>
    <meta name="csrf-token" content="<?= Formatter::escape($csrf) ?>">
    <?php endif; ?>
    <title><?= Formatter::escape($title) ?> · WBPMS</title>
    <style>
        /* ── Reset & base ───────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        html { font-size: 15px; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f4f5f7;
            color: #1a202c;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        a { color: #4f46e5; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ── Top navigation ─────────────────────────────────────────── */
        .nav {
            background: #1e1b4b;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            height: 52px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand {
            font-weight: 700;
            font-size: 1.05rem;
            color: #fff;
            letter-spacing: .02em;
            margin-right: 2rem;
            white-space: nowrap;
        }
        .nav-links {
            display: flex;
            align-items: center;
            flex: 1;
        }
        .nav-links a {
            padding: 0 .85rem;
            line-height: 52px;
            font-size: .875rem;
            color: #c7d2fe;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            display: block;
            transition: color .12s, border-color .12s;
        }
        .nav-links a:hover { color: #fff; text-decoration: none; border-bottom-color: #818cf8; }
        .nav-links a.active { color: #fff; border-bottom-color: #a5b4fc; font-weight: 500; }

        /* Dropdown groups */
        .nav-group { position: relative; }
        .nav-group > .nav-label {
            padding: 0 .85rem;
            line-height: 52px;
            font-size: .875rem;
            color: #c7d2fe;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        .nav-group > .nav-label::after { content: ' ▾'; font-size: .65rem; }
        .nav-group:hover > .nav-label { color: #fff; }
        .nav-dropdown {
            display: none;
            position: absolute;
            top: 52px;
            left: 0;
            background: #312e81;
            min-width: 185px;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 6px 16px rgba(0,0,0,.3);
            z-index: 200;
        }
        .nav-group:hover > .nav-dropdown { display: block; }
        .nav-dropdown a {
            line-height: 1;
            padding: .65rem 1rem;
            border-bottom: none;
            color: #c7d2fe;
            display: block;
            font-size: .875rem;
        }
        .nav-dropdown a:hover { background: #4338ca; color: #fff; text-decoration: none; }
        .nav-dropdown a.active { color: #fff; font-weight: 500; }

        /* Right side user area */
        .nav-user {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: .825rem;
            color: #c7d2fe;
            white-space: nowrap;
        }
        .nav-user form { margin: 0; }
        .nav-user button {
            background: none;
            border: 1px solid #6366f1;
            color: #c7d2fe;
            padding: .3rem .7rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: .8rem;
            transition: background .12s;
        }
        .nav-user button:hover { background: #4338ca; color: #fff; }

        /* ── Page wrapper ───────────────────────────────────────────── */
        .page-wrapper {
            flex: 1;
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 1.75rem 1.5rem;
        }

        /* ── Flash banners ──────────────────────────────────────────── */
        .flash {
            padding: .7rem 1rem;
            border-radius: 5px;
            margin-bottom: 1.25rem;
            font-size: .9rem;
        }
        .flash-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .flash-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .flash-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

        /* ── Common page elements ───────────────────────────────────── */
        h1 { font-size: 1.5rem; margin: 0 0 1.25rem; color: #111827; }
        h2 { font-size: 1.15rem; margin: 1.5rem 0 .75rem; color: #1f2937; }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Tables */
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        thead th {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            padding: .6rem .85rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
        }
        tbody tr:hover { background: #f9fafb; }
        tbody td { padding: .55rem .85rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tbody tr:last-child td { border-bottom: none; }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: .45rem .9rem;
            border-radius: 4px;
            font-size: .875rem;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: background .12s;
        }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-primary:hover { background: #4338ca; text-decoration: none; }
        .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #e5e7eb; text-decoration: none; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-sm { padding: .3rem .65rem; font-size: .8rem; }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: .2rem .55rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
        }
        .badge-gray    { background: #f3f4f6; color: #6b7280; }
        .badge-blue    { background: #eff6ff; color: #1d4ed8; }
        .badge-yellow  { background: #fffbeb; color: #92400e; }
        .badge-green   { background: #f0fdf4; color: #166534; }
        .badge-red     { background: #fef2f2; color: #991b1b; }
        .badge-indigo  { background: #eef2ff; color: #3730a3; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .875rem; font-weight: 500; color: #374151; margin-bottom: .3rem; }
        .form-group input[type=text],
        .form-group input[type=number],
        .form-group input[type=date],
        .form-group input[type=email],
        .form-group input[type=password],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: .55rem .75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: .9rem;
            font-family: inherit;
            outline: none;
            transition: border-color .15s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79,70,229,.15); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .field-error { font-size: .8rem; color: #dc2626; margin-top: .25rem; }
        .form-group input.is-invalid,
        .form-group select.is-invalid { border-color: #dc2626; }

        /* Page header row */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .page-header h1 { margin: 0; }

        /* Stat cards for dashboards */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.1rem 1.25rem; }
        .stat-card .stat-label { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .35rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: #111827; line-height: 1; }
        .stat-card .stat-sub { font-size: .8rem; color: #9ca3af; margin-top: .25rem; }

        /* ── Footer ─────────────────────────────────────────────────── */
        footer {
            background: #fff;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            padding: .65rem 1rem;
            font-size: .75rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<?php if ($isAuth): ?>
<nav class="nav" aria-label="Main navigation">
    <span class="nav-brand">WBPMS</span>

    <div class="nav-links">

        <?php if ($isHR): ?>

        <a href="/hr/dashboard"<?= $active('/hr/dashboard') ?>>Dashboard</a>

        <div class="nav-group">
            <span class="nav-label">Employees</span>
            <div class="nav-dropdown">
                <a href="/hr/employees"<?= $active('/hr/employees') ?>>All Employees</a>
                <a href="/hr/employees/new"<?= $active('/hr/employees/new') ?>>Add Employee</a>
            </div>
        </div>

        <div class="nav-group">
            <span class="nav-label">Schedules</span>
            <div class="nav-dropdown">
                <a href="/hr/schedules"<?= $active('/hr/schedules') ?>>Work Schedules</a>
            </div>
        </div>

        <div class="nav-group">
            <span class="nav-label">Attendance</span>
            <div class="nav-dropdown">
                <a href="/hr/attendance/import"<?= $active('/hr/attendance/import') ?>>Import Workbook</a>
                <a href="/hr/attendance"<?= $active('/hr/attendance') ?>>Timesheet Review</a>
            </div>
        </div>

        <div class="nav-group">
            <span class="nav-label">Requests</span>
            <div class="nav-dropdown">
                <a href="/hr/requests"<?= $active('/hr/requests') ?>>All Requests</a>
            </div>
        </div>

        <div class="nav-group">
            <span class="nav-label">Payroll</span>
            <div class="nav-dropdown">
                <a href="/hr/payroll"<?= $active('/hr/payroll') ?>>Payroll Runs</a>
                <a href="/hr/payroll/run"<?= $active('/hr/payroll/run') ?>>New Run</a>
            </div>
        </div>

        <?php elseif ($isOwner): ?>

        <a href="/owner/dashboard"<?= $active('/owner/dashboard') ?>>Dashboard</a>

        <div class="nav-group">
            <span class="nav-label">Payroll</span>
            <div class="nav-dropdown">
                <a href="/owner/payroll"<?= $active('/owner/payroll') ?>>Pending Approvals</a>
            </div>
        </div>

        <?php elseif ($isEmp): ?>

        <a href="/employee/dashboard"<?= $active('/employee/dashboard') ?>>Dashboard</a>
        <a href="/employee/attendance"<?= $active('/employee/attendance') ?>>My Attendance</a>
        <a href="/employee/requests"<?= $active('/employee/requests') ?>>My Requests</a>
        <a href="/employee/payslips"<?= $active('/employee/payslips') ?>>My Payslips</a>

        <?php endif; ?>

    </div>

    <div class="nav-user">
        <span>
            <?= Formatter::escape($userName) ?>
            <span style="opacity:.55;font-size:.75rem">(<?= Formatter::escape($roleName) ?>)</span>
        </span>
        <form method="POST" action="/logout">
            <input type="hidden" name="_csrf" value="<?= Formatter::escape($csrf) ?>">
            <button type="submit">Sign out</button>
        </form>
    </div>
</nav>
<?php else: ?>
<header style="background:#1e1b4b;color:#fff;padding:.8rem 1.25rem;font-weight:700;font-size:1rem;">
    WBPMS &mdash; Web-Based Payroll Management System
</header>
<?php endif; ?>

<div class="page-wrapper">

    <?php if ($flash !== null): ?>
    <div class="flash flash-success" role="status">✓ <?= Formatter::escape($flash) ?></div>
    <?php endif; ?>

    <?php if ($flashError !== null): ?>
    <div class="flash flash-error" role="alert">⚠ <?= Formatter::escape($flashError) ?></div>
    <?php endif; ?>

    <?= $content ?>

</div>

<footer>
    WBPMS &copy; Light Diamond Enterprises &mdash; Internal use only. All access is logged and audited.
</footer>

</body>
</html>
