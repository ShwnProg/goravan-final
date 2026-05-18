<?php
require_once '../../autoload.php';
if (!isset($_SESSION['is_login'])) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'user') {
    $_SESSION['error'] = 'Passenger access only.';
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

$userId = decrypt($_SESSION['id']);
$um = new Users($conn);
$um->id = $userId;
$user = $um->GetUserById();
$_SESSION['user_firstname'] = $user['firstname'] ?? '';
$_SESSION['user_lastname'] = $user['lastname'] ?? '';
// Determine relative path depth for assets
$depth = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'GoraVan') ?> — GoraVan</title>
    <script>
        (function () {
            try {
                if (localStorage.getItem('gv-theme') === 'dark') {
                    document.documentElement.classList.add('user-dark-preload');
                }
            } catch (e) { }
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $depth ?>assets/css/base.css">
    <link rel="stylesheet" href="<?= $depth ?>assets/css/user-common.css">
    <?php if (!empty($page_css)): ?>
        <link rel="stylesheet" href="<?= $page_css ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="../../images/logo_white.png" type="image/png">
</head>

<body class="user-page" id="userBody">
    <script>
        window.GV_BASE_URL = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
    </script>
    <!-- <?php if (isset($_SESSION['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({ title: 'Success', text: <?= json_encode($_SESSION['success']) ?>, icon: 'success' });
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <?php $firstError = is_array($_SESSION['error']) ? $_SESSION['error'][0] : $_SESSION['error']; ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({ title: 'Error', text: <?= json_encode($firstError) ?>, icon: 'error' });
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?> -->

    <nav class="u-topnav">
        <!-- <img src="../../images/logo.png" alt="GoraVan Logo"> -->
        <a href="<?= $depth ?>views/users/index.php" class="u-logo">
            <img src="<?= $depth ?>images/logo.png" alt="GoraVan Logo" id="logoImg"
                data-light-logo="<?= $depth ?>images/logo.png" data-dark-logo="<?= $depth ?>images/logo_white.png">
            <span>Gora<span class='accent'>Van</span></span>
        </a>

        <div class="u-navlinks">
            <a href="<?= $depth ?>views/users/index.php"
                class="u-navlink <?= ($active_page ?? '') === 'home' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i> Home
            </a>
            <a href="<?= $depth ?>views/users/schedule.php"
                class="u-navlink <?= ($active_page ?? '') === 'schedule' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i> Schedule
            </a>
            <a href="<?= $depth ?>views/users/my-bookings.php"
                class="u-navlink <?= ($active_page ?? '') === 'bookings' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-ul"></i> My Bookings
            </a>
            <a href="<?= $depth ?>views/users/my-payments.php"
                class="u-navlink <?= ($active_page ?? '') === 'payments' ? 'active' : '' ?>">
                <i class="fa-solid fa-wallet"></i> Payments
            </a>
        </div>

        <div class="u-navright">
            <!-- Dark mode toggle -->
            <button class="u-iconbtn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>

            <!-- Notifications -->
            <div class="u-notif-wrap">
                <button class="u-iconbtn u-notif-btn" id="userNotifToggle" aria-label="Notifications"
                    title="Notifications"
                    data-notification-url="<?= $depth ?>controllers/users/NotificationController.php?action=list">
                    <i class="fa-regular fa-bell"></i>
                    <span class="u-notif-dot" id="userNotifDot" hidden></span>
                </button>
                <div class="u-notif-panel" id="userNotifPanel">
                    <div class="u-notif-head">
                        <div>
                            <strong>Notifications</strong>
                            <span id="userNotifSummary">Latest activity</span>
                        </div>
                        <button type="button" id="userNotifMarkRead">Mark read</button>
                    </div>
                    <div class="u-notif-list" id="userNotifList">
                        <div class="u-notif-empty">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <p>Loading notifications...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile -->
            <div class="u-profile-wrap">
                <div class="u-profile-chip" id="profileChip">
                    <div class="u-chip-avatar">
                        <?= strtoupper(substr(ucfirst($user['firstname'] ?? $_SESSION['user_firstname'] ?? 'U'), 0, 1)) . strtoupper(substr(ucfirst($user['lastname'] ?? $_SESSION['user_lastname'] ?? ''), 0, 1)) ?>
                    </div>
                    <span class="u-chip-name">
                        <?= htmlspecialchars(ucfirst($user['firstname'] ?? $_SESSION['user_firstname'] ?? '') . ' ' . ucfirst($user['lastname'] ?? $_SESSION['user_lastname'] ?? '')) ?>
                    </span>
                    <i class="fa-solid fa-chevron-down u-chip-caret" id="profileCaret"></i>
                </div>
                <div class="u-dropdown" id="profileDropdown">
                    <div class="u-dd-header">
                        <p class="u-dd-name">
                            <?= htmlspecialchars(ucfirst($user['firstname'] ?? $_SESSION['user_firstname'] ?? '') . ' ' . ucfirst($user['lastname'] ?? $_SESSION['user_lastname'] ?? '')) ?>
                        </p>
                        <p class="u-dd-role">Passenger</p>
                    </div>
                    <a href="<?= $depth ?>views/users/profile.php" class="u-dd-item">
                        <i class="fa-regular fa-user"></i> My profile
                    </a>
                    <a href="<?= $depth ?>views/users/my-bookings.php" class="u-dd-item">
                        <i class="fa-solid fa-list-ul"></i> My bookings
                    </a>
                    <div class="u-dd-divider"></div>
                    <a href="<?= $depth ?>controllers/users/LogoutController.php" class="u-dd-item u-dd-danger">
                        <i class="fa-solid fa-right-from-bracket"></i> Sign out
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="u-main">
        <?= $content ?>
    </main>

    <!-- Mobile bottom tab bar -->
    <nav class="u-bottomnav">
        <a href="<?= $depth ?>views/users/index.php"
            class="u-bn-item <?= ($active_page ?? '') === 'home' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i><span>Home</span>
        </a>
        <a href="<?= $depth ?>views/users/schedule.php"
            class="u-bn-item <?= ($active_page ?? '') === 'schedule' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i><span>Schedule</span>
        </a>
        <a href="<?= $depth ?>views/users/my-bookings.php"
            class="u-bn-item <?= ($active_page ?? '') === 'bookings' ? 'active' : '' ?>">
            <i class="fa-solid fa-list-ul"></i><span>My Bookings</span>
        </a>
        <a href="<?= $depth ?>views/users/my-payments.php"
            class="u-bn-item <?= ($active_page ?? '') === 'payments' ? 'active' : '' ?>">
            <i class="fa-solid fa-wallet"></i><span>Payments</span>
        </a>
        <a href="<?= $depth ?>views/users/profile.php"
            class="u-bn-item <?= ($active_page ?? '') === 'profile' ? 'active' : '' ?>">
            <i class="fa-regular fa-user"></i><span>Profile</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (!empty($page_js)): ?>
        <script src="<?= $page_js ?>"></script>
    <?php endif; ?>
    <script src="<?= $depth ?>assets/js/user-nav.js"></script>
</body>

</html>
