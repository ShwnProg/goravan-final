<?php
require_once '../../autoload.php';

if (empty($_SESSION['is_login']) || empty($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    $_SESSION['error'] = 'Driver access only.';
    header('Location: ../auth/login.php');
    exit;
}

$driverUserId = (int) decrypt($_SESSION['id']);
$driverObj = new Drivers($conn);
$driverInfo = $driverObj->GetDriverByUserId($driverUserId);
$driverName = $driverInfo['full_name'] ?? 'Driver';
$initials = strtoupper(substr($driverName, 0, 1));
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Driver Dashboard') ?> - GoraVan</title>
    <?php $admin_shell = true; include '../includes/shared/head.php'; ?>
    <?php if (!empty($page_css)): ?>
        <link rel="stylesheet" href="<?= $page_css ?>">
    <?php endif; ?>
</head>

<body>
    <script>
        (function () {
            var theme = document.documentElement.dataset.adminTheme || window.__adminTheme || 'light';
            var dark = theme === 'dark';
            document.body.classList.add(dark ? 'admin-dark-mode-active' : 'admin-light-mode-active');
            document.body.style.backgroundColor = dark ? '#0f172a' : '#f8fafc';
        })();
    </script>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                (window.AdminUI ? AdminUI.notify('success', <?= json_encode($_SESSION['success']) ?>) : Swal.fire('Success', <?= json_encode($_SESSION['success']) ?>, 'success'));
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <?php $firstError = is_array($_SESSION['error']) ? $_SESSION['error'][0] : $_SESSION['error']; ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                (window.AdminUI ? AdminUI.notify('error', <?= json_encode($firstError) ?>) : Swal.fire('Unable to Complete Request', <?= json_encode($firstError) ?>, 'error'));
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../images/logo.png" alt="GoraVan logo">
            <p>Gora<span>Van</span></p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">
                <label>Driver</label>
                <a href="index.php" class="menu-btn <?= $current === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-gauge"></i><span>Dashboard</span>
                </a>
            </div>
            <div class="menu-section">
                <label>Account</label>
                <a href="profile.php" class="menu-btn <?= $current === 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-pen"></i><span>Edit Profile</span>
                </a>
                <a href="../../controllers/users/LogoutController.php" class="menu-btn logout-btn">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="burger-btn" id="burger-btn" aria-label="Toggle sidebar">
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-title">
                    <p class="page-title"><?= htmlspecialchars(strtoupper($title ?? 'Driver Dashboard')) ?></p>
                    <p class="topbar-greeting">
                        <?= $current === 'profile.php'
                            ? 'Manage your driver account, ' . htmlspecialchars(ucwords($driverName))
                            : 'Here are your assigned trips, ' . htmlspecialchars(ucwords($driverName)) ?>
                    </p>
                </div>
            </div>
            <div class="topbar-right">
                <button class="topbar-icon-btn" id="topbar-dark-toggle" title="Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="topbar-profile" id="profile-toggle">
                    <div class="topbar-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="topbar-profile-info">
                        <span class="topbar-name"><?= htmlspecialchars(ucwords($driverName)) ?></span>
                        <span class="topbar-role">Driver</span>
                    </div>
                    <i class="fas fa-chevron-down topbar-caret" id="profile-caret"></i>
                    <div class="profile-dropdown-menu" id="profile-dropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar"><?= htmlspecialchars($initials) ?></div>
                            <div>
                                <p class="dropdown-name"><?= htmlspecialchars(ucwords($driverName)) ?></p>
                                <p class="dropdown-role">Driver</p>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-pen"></i>
                            <span>Edit Profile</span>
                        </a>
                        <a href="../../controllers/users/LogoutController.php" class="dropdown-item dropdown-logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <section class="page-content" id="page-content">
            <?= $content ?>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../assets/js/admin-ui.js"></script>
    <script src="../../assets/js/nav.js"></script>
    <?php if (!empty($page_js)): ?>
        <script src="<?= $page_js ?>"></script>
    <?php endif; ?>
</body>

</html>
