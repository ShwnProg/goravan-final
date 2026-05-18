<!-- Fonts -->
<?php $isAdminShell = !empty($admin_shell); ?>
<?php if ($isAdminShell): ?>
    <script>
        (function () {
            var theme = 'light';
            try {
                theme = localStorage.getItem('admin_theme') || 'light';
            } catch (e) { }
            var dark = theme === 'dark';
            window.__adminTheme = theme;
            document.documentElement.classList.add('theme-loading');
            document.documentElement.classList.add(dark ? 'dark-init' : 'light-init');
            document.documentElement.classList.add(dark ? 'admin-dark-mode-active' : 'admin-light-mode-active');
            document.documentElement.dataset.adminTheme = theme;
            document.addEventListener('DOMContentLoaded', function () {
                document.body.classList.toggle('admin-dark-mode-active', dark);
                document.body.classList.toggle('admin-light-mode-active', !dark);
                document.body.style.backgroundColor = dark ? '#0f172a' : '#f8fafc';
                requestAnimationFrame(function () {
                    document.documentElement.classList.remove('theme-loading');
                });
            });
        })();
    </script>
    <style>
        html,
        body {
            background: #f8fafc;
            min-height: 100%;
            color-scheme: light;
        }

        html.dark-init,
        html.dark-init body {
            background: #0f172a !important;
            background-color: #0f172a !important;
            color: #dbe4ef;
            color-scheme: dark;
        }

        html.light-init,
        html.light-init body {
            background: #f8fafc !important;
            background-color: #f8fafc !important;
            color-scheme: light;
        }

        html.dark-init .main-content,
        html.dark-init .page-content {
            background: #0f172a !important;
            background-color: #0f172a !important;
        }

        html.dark-init .sidebar,
        html.dark-init .topbar {
            background: #111827 !important;
            border-color: rgba(255, 255, 255, .07) !important;
        }

        html.dark-init .modal-content,
        html.dark-init .dropdown-menu,
        html.dark-init .profile-dropdown-menu,
        html.dark-init .notif-dropdown {
            background: #1b2233 !important;
            color: #dbe4ef !important;
        }

        html.theme-loading *,
        html.theme-loading *::before,
        html.theme-loading *::after {
            transition: none !important;
            animation-duration: .001ms !important;
        }
    </style>
<?php else: ?>
    <style>
        html,
        body {
            background: #f8fafc;
            min-height: 100%;
            color-scheme: light;
        }
    </style>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="icon" href="../../images/logo_white.png" type="image/png">
<!-- App CSS -->
<link rel="stylesheet" href="../../assets/css/base.css">
<link rel="stylesheet" href="../../assets/css/admin-layout.css">
<link rel="stylesheet" href="../../assets/css/admin-common.css">
<link rel="stylesheet" href="../../assets/css/vans.css">
<link rel="stylesheet" href="../../assets/css/drivers.css">
<link rel="stylesheet" href="../../assets/css/routes.css">
<link rel="stylesheet" href="../../assets/css/schedules.css">
<link rel="stylesheet" href="../../assets/css/dashboard.css">
<link rel="stylesheet" href="../../assets/css/bookings.css">
<link rel="stylesheet" href="../../assets/css/users.css">
<link rel="stylesheet" href="../../assets/css/payments.css">
<link rel="stylesheet" href="../../assets/css/auth.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<!-- <link rel="stylesheet" href="../../assets/css/users.css"> -->


<!-- Dark mode system  must load last so it overrides everything above -->
<link rel="stylesheet" href="../../assets/css/profile.css">
<link rel="stylesheet" href="../../assets/css/admin-tables.css">

<!-- Page-specific CSS -->
<!-- <?php if (!empty($page_css)): ?>
    <link rel="stylesheet" href="<?= $page_css ?>">
<?php endif; ?> -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>