<?php


use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Http\View\Formatter;

/**
 * WBPMS application shell — sidebar + topbar layout.
 *
 * Variables injected by the controller:
 *   string  $title        — page <title> text
 *   string  $activePage   — nav key that should be highlighted (optional)
 *   string  $content      — pre-rendered inner HTML (optional; for include-style use)
 *   array   $flash        — list of [type, message] pairs (optional)
 *   int     $notifCount   — badge count for the topbar bell (optional)
 *
 * The sidebar navigation is built entirely from the authenticated session
 * identity; no backend logic lives in this file.
 */

$identity    = AuthMiddleware::identity();
$roleName    = $identity['role_name']    ?? '';
$displayName = $identity['display_name'] ?? ($identity['username'] ?? '');
$activePage  = $activePage ?? '';
$flash       = $flash ?? [];
$notifCount  = $notifCount ?? 0;

// Base URL for asset and route links (no trailing slash)
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');

// ---- Role-aware nav items ------------------------------------------------
// Format: [route_path, label, icon_character, active_key]
$nav = match ($roleName) {
    'BusinessOwner' => [
        ['owner/dashboard',  'Dashboard',          '⌂', 'dashboard'],
        ['users',            'User Management',    '♙', 'users'],
        ['owner/payroll',    'Payroll Approval',   '₱', 'payroll'],
        ['hr/reports',       'Reports',            '▤', 'reports'],
    ],
    'HRHead' => [
        ['hr/dashboard',     'Dashboard',              '⌂', 'dashboard'],
        ['hr/employees',     'Employee Management',    '♟', 'employees'],
        ['hr/attendance',    'Attendance',             '◷', 'attendance'],
        ['hr/schedules',     'Work Schedule',          '▣', 'schedule'],
        ['hr/requests',      'Requests',               '▱', 'requests'],
        ['hr/payroll',       'Payroll Processing',     '₱', 'payroll'],
        ['hr/salary',        'Salary Management',      '💲', 'salary'],
        ['hr/benefits',      'Benefits & Deductions',  '＋', 'benefits'],
        ['hr/reports',       'Reports',                '▤', 'reports'],
    ],
    default => [ // Employee
        ['employee/dashboard',  'Dashboard',                   '⌂', 'dashboard'],
        ['employee/attendance', 'My Attendance',               '◷', 'my-attendance'],
        ['employee/requests',   'Leave / OT / Cash Advance',   '▱', 'my-requests'],
        ['employee/payslips',   'My Payslips',                 '▤', 'my-payslips'],
    ],
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Formatter::escape($title ?? 'WBPMS') ?> | Light Diamond Enterprises</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= filemtime(APP_ROOT . '/public/assets/app.css') ?>">
</head>
<body>

<button class="mobile-toggle" onclick="document.body.classList.toggle('menu-open')" aria-label="Toggle menu">☰</button>
<div class="overlay" onclick="document.body.classList.remove('menu-open')"></div>

<div class="app">

    <!-- ================================================================
         SIDEBAR
         ================================================================ -->
    <aside class="sidebar" role="navigation" aria-label="Main navigation">

        <div class="brand">
            <img src="<?= $base ?>/assets/light-diamond-logo.png" alt="Light Diamond Enterprises logo">
            <div>
                <b>Light Diamond</b>
                <span>Enterprises</span>
            </div>
        </div>

        <nav>
            <?php foreach ($nav as [$path, $label, $icon, $key]): ?>
                <a class="<?= $activePage === $key ? 'active' : '' ?>"
                   href="<?= $base ?>/<?= Formatter::escape($path) ?>">
                    <i class="nav-icon" aria-hidden="true"><?= $icon ?></i>
                    <span><?= Formatter::escape($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="side-user">
            <b><?= Formatter::escape($displayName) ?></b>
            <span><?= Formatter::escape($roleName) ?></span>
            <form method="POST" action="<?= $base ?>/logout" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CsrfMiddleware::token(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="side-user-logout" style="
                    display:inline-block;padding:10px 18px;background:#666;
                    color:#fff;border:1px solid #aaa;border-radius:2px;
                    font-weight:700;cursor:pointer;">
                    ↪ Logout
                </button>
            </form>
        </div>

    </aside>

    <!-- ================================================================
         MAIN AREA
         ================================================================ -->
    <div class="main">

        <!-- Topbar -->
        <header class="topbar">
            <div class="global-search" role="search">
                <span aria-hidden="true">⌕</span>
                <input id="globalSearch"
                       type="search"
                       placeholder="Search modules..."
                       aria-label="Search navigation modules"
                       oninput="searchNav(this.value)">
            </div>

            <button class="notif-btn"
                    onclick="toggleNotif()"
                    aria-label="Notifications"
                    aria-haspopup="true">
                <span class="bell" aria-hidden="true">🔔</span>
                <?php if ($notifCount > 0): ?>
                    <em class="badge-count" aria-label="<?= (int) $notifCount ?> notifications">
                        <?= (int) $notifCount ?>
                    </em>
                <?php endif; ?>
            </button>

            <div class="topbar-date" aria-live="off">
                <?= date('m/d/y') ?>
                <span><?= date('H:i') ?></span>
            </div>

            <div id="notifPanel" class="notif-panel" hidden role="status" aria-live="polite">
                <b>Notifications</b>
                <?php if ($roleName === 'BusinessOwner'): ?>
                    <p><strong>Requests Pending Approval</strong><br>
                        <?= (int) $notifCount ?> request(s) waiting for your final approval.</p>
                <?php elseif ($roleName === 'HRHead'): ?>
                    <p><strong>Attendance Alerts</strong><br>
                        <?= (int) $notifCount ?> attendance record(s) with missing punch data.</p>
                <?php else: ?>
                    <p><strong>Request Updates</strong><br>
                        <?= (int) $notifCount ?> of your requests have a new decision.</p>
                <?php endif; ?>
            </div>
        </header>

        <!-- Page content -->
        <section class="content">

            <?php
            // Normalize flash: ViewRenderer injects $flash/$flashError as strings;
            // older controllers inject $flash as array of [$type, $msg] pairs.
            $flashMessages = [];
            if (!empty($flash)) {
                if (is_string($flash)) {
                    $flashMessages[] = ['success', $flash];
                } elseif (is_array($flash)) {
                    foreach ($flash as $item) {
                        if (is_array($item) && count($item) === 2) {
                            $flashMessages[] = $item;
                        }
                    }
                }
            }
            if (!empty($flashError) && is_string($flashError)) {
                $flashMessages[] = ['error', $flashError];
            }
            foreach ($flashMessages as [$flashType, $flashMessage]):
            ?>
                <div class="alert <?= Formatter::escape($flashType) ?>" role="alert">
                    <?= Formatter::escape($flashMessage) ?>
                </div>
            <?php endforeach; ?>

            <?= $content ?? '' ?>

        </section>

    </div><!-- /.main -->

</div><!-- /.app -->

<script>
function searchNav(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.sidebar nav a').forEach(function(a) {
        a.style.display = (!q || a.innerText.toLowerCase().includes(q)) ? 'flex' : 'none';
    });
}

function toggleNotif() {
    var p = document.getElementById('notifPanel');
    if (p) p.hidden = !p.hidden;
}

function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('show');
}

function filterRows(input, tableId) {
    var q = input.value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function(r) {
        r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

</body>
</html>
