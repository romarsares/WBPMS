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
$notifCount  = $notifCount ?? 0;

// $flash and $flashMessages are injected by the caller:
//   - ViewRenderer path  → $flashMessages is a pre-built list<[type,msg]>; $flash is null
//   - Direct require path (old-style controllers) → $flash is array of [type,msg] pairs; $flashMessages is not set
// Do NOT reassign $flash here — it would shadow the injected value.
if (!isset($flashMessages)) {
    $flashMessages = [];
}

// Base URL for asset and route links (no trailing slash)
$base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');

// ---- Role-aware nav items ------------------------------------------------
// Format: [route_path, label, icon_character, active_key]
$nav = match ($roleName) {
    'BusinessOwner' => [
        ['owner/dashboard',  'Dashboard',          '⌂', 'dashboard'],
        ['users',            'User Management',    '♙', 'users'],
        ['owner/payroll',    'Payroll Approval',   '₱', 'payroll'],
        ['owner/requests',   'Request Approvals',  '✉', 'requests'],
        ['hr/reports',       'Reports',            '▤', 'reports'],
    ],
    'HRHead' => [
        ['hr/dashboard',     'Dashboard',              '⌂', 'dashboard'],
        ['hr/employees',     'Employee Management',    '♟', 'employees'],
        ['hr/attendance',    'Attendance',             '◷', 'attendance'],
        ['hr/schedules',     'Work Schedule',          '▣', 'schedule'],
        ['hr/requests',      'Requests',               '▱', 'requests'],
        ['hr/payroll',       'Payroll Processing',     '₱', 'payroll'],
        ['hr/salary',        'Salary Management',      '₱', 'salary'],
        ['hr/benefits',      'Benefits & Deductions',  '＋', 'benefits'],
        ['hr/reports',       'Reports',                '▤', 'reports'],
        ['users',            'User Management',        '👤', 'users'],
        ['hr/settings',      'Settings',               '⚙', 'settings'],
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

        <!-- Version badge — helps collaborators verify they are on the same build -->
        <div class="side-version" title="<?= htmlspecialchars(\Wbpms\Http\View\AppVersion::hash(), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(\Wbpms\Http\View\AppVersion::label(), ENT_QUOTES, 'UTF-8') ?>
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
                <svg class="bell" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
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
                    <p><strong>Payroll Runs Pending Approval</strong><br>
                        <?= (int) $notifCount ?> payroll run(s) waiting for your final approval.<br>
                        <a href="<?= $base ?>/owner/payroll">Review pending payroll</a></p>
                    <p><a href="<?= $base ?>/owner/requests">View pending request approvals →</a></p>
                <?php elseif ($roleName === 'HRHead'): ?>
                    <p><strong>Attendance Alerts</strong><br>
                        <?= (int) $notifCount ?> attendance record(s) with missing punch data.</p>
                <?php else: ?>
                    <p><strong>HR Notices</strong><br>
                        <?= (int) $notifCount ?> notice(s) awaiting your acknowledgment.<br>
                        <a href="<?= $base ?>/employee/notices">View HR notices</a></p>
                <?php endif; ?>
            </div>
        </header>

        <!-- Page content -->
        <section class="content">

        <?php
            // Flash normalization.
            //
            // ViewRenderer path: $flashMessages is already populated as list<[type,msg]>.
            //
            // Direct-require path (old-style controllers: UserController, etc.):
            //   $flash is array of ['success'|'error', 'message'] pairs — merge into $flashMessages.
            //   $flashError may be a string set by those controllers directly.
            //
            // Both paths feed the toast container below. No inline .alert is
            // rendered here for action feedback; validation errors stay inside $content.

            if (!empty($flash)) {
                if (is_string($flash) && $flash !== '') {
                    $flashMessages[] = ['success', $flash];
                } elseif (is_array($flash)) {
                    foreach ($flash as $item) {
                        if (is_array($item) && count($item) === 2) {
                            $flashMessages[] = [$item[0], $item[1]];
                        }
                    }
                }
            }
            if (!empty($flashError) && is_string($flashError)) {
                $flashMessages[] = ['error', $flashError];
            }
        ?>

            <?= $content ?? '' ?>

        </section>

    </div><!-- /.main -->

</div><!-- /.app -->

<!-- ================================================================
     TOAST NOTIFICATION CONTAINER
     Flash messages of type 'success' and 'error' that come from
     redirects (CRUD actions) are shown as auto-dismissing toasts.
     Validation errors on forms remain as inline .alert divs.
     ================================================================ -->
<div id="toastContainer" class="toast-container" role="status" aria-live="polite" aria-atomic="false"></div>

<?php
// Build toast data from flash messages for JS injection.
// Only redirect-based action messages become toasts;
// form validation errors stay as inline alerts (handled above in content).
$toastData = [];
foreach ($flashMessages as [$ft, $fm]) {
    $toastData[] = ['type' => $ft, 'message' => $fm];
}
?>

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

function showToast(type, message) {
    var container = document.getElementById('toastContainer');
    if (!container) return;

    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.setAttribute('role', 'alert');

    var icon = type === 'success' ? '✓' : (type === 'error' ? '✕' : (type === 'warning' ? '⚠' : 'ℹ'));
    toast.innerHTML =
        '<span class="toast-icon">' + icon + '</span>' +
        '<span class="toast-msg">' + message.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>' +
        '<button class="toast-close" onclick="this.parentElement.remove()" aria-label="Dismiss">×</button>';

    container.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { toast.classList.add('toast-show'); });
    });

    // Auto-dismiss after 4.5 s
    setTimeout(function () {
        toast.classList.remove('toast-show');
        toast.classList.add('toast-hide');
        setTimeout(function () { if (toast.parentElement) toast.remove(); }, 400);
    }, 4500);
}

// Fire any queued flash toasts now that showToast is defined.
<?php if (!empty($toastData)): ?>
(function () {
    var toasts = <?= json_encode($toastData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    toasts.forEach(function (t) { showToast(t.type, t.message); });
})();
<?php endif; ?>
</script>

<!-- Build version — visible only in page source for non-sidebar views -->
<meta name="app-version" content="<?= htmlspecialchars(\Wbpms\Http\View\AppVersion::label(), ENT_QUOTES, 'UTF-8') ?>">
<meta name="app-commit" content="<?= htmlspecialchars(\Wbpms\Http\View\AppVersion::hash(), ENT_QUOTES, 'UTF-8') ?>">

</body>
</html>
