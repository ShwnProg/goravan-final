<?php
if (!defined('GORAVAN_DATABASE_STATUS_MODE')) {
    header('Location: ../auth/login.php');
    exit;
}

$loginUrl = '/views/auth/login.php';
$dashboardUrl = $loginUrl;

if (!empty($_SESSION['is_login']) && !empty($_SESSION['role'])) {
    $dashboardMap = [
        'admin' => '/views/admin/index.php',
        'user' => '/views/users/index.php',
        'driver' => '/views/driver/index.php',
    ];

    $dashboardUrl = $dashboardMap[$_SESSION['role']] ?? '/views/auth/login.php';
}

$checkedAt = date('F j, Y, g:i A');
$databaseStatusError = trim((string)($databaseStatusError ?? 'No database error details were provided.'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Database Not Found | GoraVan System Status</title>
    <script>
        (function () {
            var theme = 'light';
            try {
                theme = localStorage.getItem('admin_theme') || localStorage.getItem('theme') || 'light';
            } catch (e) { }
            var dark = theme === 'dark';
            document.documentElement.classList.add(dark ? 'dark-init' : 'light-init');
            document.documentElement.classList.add(dark ? 'admin-dark-mode-active' : 'admin-light-mode-active');
            document.documentElement.dataset.adminTheme = dark ? 'dark' : 'light';
            document.addEventListener('DOMContentLoaded', function () {
                document.body.classList.add(dark ? 'admin-dark-mode-active' : 'admin-light-mode-active');
            });
        })();
    </script>
    <style>
        html,
        body {
            min-height: 100%;
            background: #f8fafc;
            color-scheme: light;
        }

        html.dark-init,
        html.dark-init body {
            background: #0f172a !important;
            color-scheme: dark;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="/images/logo_white.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/system-status.css">
</head>

<body class="system-status-page">
    <main class="status-shell" aria-labelledby="status-title">
        <section class="status-card">
            <div class="status-card__brand" aria-label="GoraVan">
                <img src="/images/logo.png" alt="GoraVan logo">
                <span>Gora<span>Van</span></span>
            </div>

            <div class="status-hero">
                <div class="status-icon" aria-hidden="true">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div class="status-copy">
                    <p class="status-label">GORAVAN SYSTEM STATUS</p>
                    <h1 id="status-title">Database not found</h1>
                    <p class="status-message">
                        The configured database may have been dropped, moved, or not restored yet. GoraVan has paused the system to protect booking, payment, trip, and user records from incomplete transactions.
                    </p>
                </div>
            </div>

            <div class="vanny-status-panel">
                <img src="/images/vanny-error.png" alt="Vanny assistant" class="vanny-status-panel__mascot">
                <div>
                    <strong>Hi, I'm Vanny.</strong>
                    <p>I detected that the database is missing. Please restore a valid backup before using GoraVan again.</p>
                </div>
            </div>

            <div class="status-grid">
                <article class="status-info-card">
                    <span class="status-info-card__icon"><i class="fa-solid fa-circle-exclamation"></i></span>
                    <div>
                        <small>Status</small>
                        <strong>Service unavailable</strong>
                    </div>
                </article>

                <article class="status-info-card">
                    <span class="status-info-card__icon"><i class="fa-solid fa-clock"></i></span>
                    <div>
                        <small>Checked</small>
                        <strong id="statusCheckedAt" data-server-time="<?= htmlspecialchars(date(DATE_ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($checkedAt, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </article>

                <article class="status-info-card">
                    <span class="status-info-card__icon"><i class="fa-solid fa-rotate"></i></span>
                    <div>
                        <small>Recommended Action</small>
                        <strong>Restore the database backup, then retry.</strong>
                    </div>
                </article>
            </div>

            <section class="status-error-details" aria-labelledby="database-error-title">
                <div class="status-error-details__header">
                    <span class="status-error-details__icon"><i class="fa-solid fa-bug"></i></span>
                    <div>
                        <small>Debug Details</small>
                        <h2 id="database-error-title">Actual database error</h2>
                    </div>
                </div>
                <pre><?= htmlspecialchars($databaseStatusError, ENT_QUOTES, 'UTF-8') ?></pre>
            </section>

            <details class="restore-guide">
                <summary>
                    <span><i class="fa-solid fa-circle-info"></i> Restore Instructions</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <p>
                    If the database was dropped, recreate the configured database and import a valid SQL backup before using the system again.
                </p>
            </details>

            <div class="status-actions" aria-label="System status actions">
                <a class="status-btn status-btn--primary" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-rotate-right"></i>
                    Retry
                </a>
                <a class="status-btn status-btn--ghost" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Login Page
                </a>
            </div>
        </section>
    </main>

    <script>
        (function () {
            var target = document.getElementById('statusCheckedAt');
            if (!target) {
                return;
            }

            function updateCheckedTime() {
                var now = new Date();
                target.textContent = now.toLocaleString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            }

            updateCheckedTime();
            window.setInterval(updateCheckedTime, 30000);
        })();
    </script>
</body>

</html>
