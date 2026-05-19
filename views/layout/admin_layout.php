<?php
require_once '../../autoload.php';

if (empty($_SESSION['is_login']) || empty($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Admin access only.';
    header("Location: ../auth/login.php");
    exit;
}

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(ucfirst(strtolower($title ?? 'GoraVan'))) ?></title>

    <?php $admin_shell = true; include '../includes/shared/head.php'; ?>

    <?php if (!empty($page_css)): ?>
        <link rel="stylesheet" href="<?= $page_css ?>">
    <?php endif; ?>

    <style id="admin-theme-final-guard">
        html.dark-init,
        html.dark-init body,
        html.dark-init .main-content,
        html.dark-init .page-content,
        html.admin-dark-mode-active,
        html.admin-dark-mode-active body,
        html.admin-dark-mode-active .main-content,
        html.admin-dark-mode-active .page-content,
        body.admin-dark-mode-active,
        body.admin-dark-mode-active .main-content,
        body.admin-dark-mode-active .page-content {
            background: #0f172a !important;
            background-color: #0f172a !important;
        }

        html.dark-init .page-card,
        html.dark-init .vans-card,
        html.dark-init .drivers-card,
        html.dark-init .routes-card,
        html.dark-init .schedules-card,
        html.dark-init .bookings-card,
        html.dark-init .users-card,
        html.dark-init .payments-card,
        html.dark-init .db-card,
        html.dark-init .db-stat,
        html.dark-init .db-tbl-card,
        html.admin-dark-mode-active .page-card,
        html.admin-dark-mode-active .vans-card,
        html.admin-dark-mode-active .drivers-card,
        html.admin-dark-mode-active .routes-card,
        html.admin-dark-mode-active .schedules-card,
        html.admin-dark-mode-active .bookings-card,
        html.admin-dark-mode-active .users-card,
        html.admin-dark-mode-active .payments-card,
        html.admin-dark-mode-active .db-card,
        html.admin-dark-mode-active .db-stat,
        html.admin-dark-mode-active .db-tbl-card {
            background: #1b2233 !important;
            border-color: rgba(148, 163, 184, .18) !important;
            color: #dbe4ef !important;
        }

        html.dark-init .page-table tbody tr,
        html.dark-init .vans-table tbody tr,
        html.dark-init .drivers-table tbody tr,
        html.dark-init .routes-table tbody tr,
        html.dark-init .schedules-table tbody tr,
        html.dark-init .bookings-table tbody tr,
        html.dark-init .users-table tbody tr,
        html.dark-init .payments-table tbody tr,
        html.admin-dark-mode-active .page-table tbody tr,
        html.admin-dark-mode-active .vans-table tbody tr,
        html.admin-dark-mode-active .drivers-table tbody tr,
        html.admin-dark-mode-active .routes-table tbody tr,
        html.admin-dark-mode-active .schedules-table tbody tr,
        html.admin-dark-mode-active .bookings-table tbody tr,
        html.admin-dark-mode-active .users-table tbody tr,
        html.admin-dark-mode-active .payments-table tbody tr {
            background: #1b2233 !important;
        }

        html.dark-init .page-table thead th,
        html.dark-init .vans-table thead th,
        html.dark-init .drivers-table thead th,
        html.dark-init .routes-table thead th,
        html.dark-init .schedules-table thead th,
        html.dark-init .bookings-table thead th,
        html.dark-init .users-table thead th,
        html.dark-init .payments-table thead th,
        html.admin-dark-mode-active .page-table thead th,
        html.admin-dark-mode-active .vans-table thead th,
        html.admin-dark-mode-active .drivers-table thead th,
        html.admin-dark-mode-active .routes-table thead th,
        html.admin-dark-mode-active .schedules-table thead th,
        html.admin-dark-mode-active .bookings-table thead th,
        html.admin-dark-mode-active .users-table thead th,
        html.admin-dark-mode-active .payments-table thead th {
            background: #111827 !important;
            color: #94a3b8 !important;
            border-color: rgba(148, 163, 184, .18) !important;
        }

        html.dark-init .page-table td,
        html.dark-init .vans-table td,
        html.dark-init .drivers-table td,
        html.dark-init .routes-table td,
        html.dark-init .schedules-table td,
        html.dark-init .bookings-table td,
        html.dark-init .users-table td,
        html.dark-init .payments-table td,
        html.admin-dark-mode-active .page-table td,
        html.admin-dark-mode-active .vans-table td,
        html.admin-dark-mode-active .drivers-table td,
        html.admin-dark-mode-active .routes-table td,
        html.admin-dark-mode-active .schedules-table td,
        html.admin-dark-mode-active .bookings-table td,
        html.admin-dark-mode-active .users-table td,
        html.admin-dark-mode-active .payments-table td {
            color: #dbe4ef !important;
            border-color: rgba(148, 163, 184, .12) !important;
        }

        html.dark-init .search-box,
        html.dark-init .filter-select,
        html.dark-init input,
        html.dark-init select,
        html.dark-init textarea,
        html.dark-init .modal-content,
        html.admin-dark-mode-active .search-box,
        html.admin-dark-mode-active .filter-select,
        html.admin-dark-mode-active input,
        html.admin-dark-mode-active select,
        html.admin-dark-mode-active textarea,
        html.admin-dark-mode-active .modal-content {
            background: #1b2233 !important;
            color: #dbe4ef !important;
            border-color: rgba(148, 163, 184, .18) !important;
        }

        html.theme-loading *,
        html.theme-loading *::before,
        html.theme-loading *::after {
            transition: none !important;
            animation-duration: .001ms !important;
        }
    </style>

</head>

<body>
    <script>
        window.GV_BASE_URL = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
        (function () {
            var theme = document.documentElement.dataset.adminTheme || window.__adminTheme || 'light';
            var dark = theme === 'dark';
            document.body.classList.add(dark ? 'admin-dark-mode-active' : 'admin-light-mode-active');
            document.body.style.backgroundColor = dark ? '#0f172a' : '#f8fafc';
        })();
    </script>

    <!-- Flash messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                (window.AdminUI ? AdminUI.notify('success', <?= json_encode($_SESSION['success']) ?>, 'Success') : Swal.fire({ title: 'Success', text: <?= json_encode($_SESSION['success']) ?>, icon: 'success' }));
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <?php $firstError = is_array($_SESSION['error']) ? $_SESSION['error'][0] : $_SESSION['error']; ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                (window.AdminUI ? AdminUI.notify('error', <?= json_encode($firstError) ?>) : Swal.fire({ title: 'Unable to Complete Request', text: <?= json_encode($firstError) ?>, icon: 'error' }));
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- ── Sidebar ────────────────────────────── -->
    <?php include '../includes/admin/sidebar.php'; ?>

    <!-- ── Main ──────────────────────────────── -->
    <main class="main-content">
        <?php include '../includes/admin/topbar.php'; ?>
        <section class="page-content" id="page-content">
            <?= $content ?>
        </section>
    </main>

    <!-- ── Core scripts ───────────────────────── -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../assets/js/vanny-ui.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/admin-ui.js"></script>
    <script src="../../assets/js/nav.js"></script>
    <script src="../../assets/js/notifications.js"></script>

    <!-- ── Page-specific script ───────────────── -->
    <?php if (!empty($page_js)): ?>
        <script src="<?= $page_js ?>"></script>
    <?php endif; ?>

    <script>
        // AUTO-INIT SETTINGS PAGE
        document.addEventListener('DOMContentLoaded', function () {
            if (window.initSettingsPage && document.getElementById('page-content')) {
                window.initSettingsPage();
            }
        });
    </script>

</body>

</html>
