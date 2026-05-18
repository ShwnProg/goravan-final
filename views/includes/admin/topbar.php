<?php
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$admin = new Admin($conn);
$admin->id = decrypt($_SESSION['id']);
$info = $admin->Read();
$adminName = ($info['firstname'] ?? '') . ' ' . ($info['lastname'] ?? 'Admin');
$initials = strtoupper(substr($adminName, 0, 1));
?>

<div class="topbar">
    <div class="topbar-left">
        <button class="burger-btn" id="burger-btn" aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="topbar-title">
            <p class="page-title"><?= htmlspecialchars(strtoupper($title ?? 'Dashboard')) ?></p>
            <p class="topbar-greeting"><?= $greeting ?>, <?= htmlspecialchars(ucwords($adminName)) ?></p>
        </div>
    </div>

    <div class="topbar-right">

        <!-- DARK MODE QUICK TOGGLE -->
        <button class="topbar-icon-btn" id="topbar-dark-toggle" title="Dark Mode">
            <i class="fas fa-moon"></i>
        </button>

        <!-- NOTIFICATIONS -->
        <!-- <button class="topbar-icon-btn" id="notif-toggle" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="notif-dot" id="notif-dot"></span>
        </button> -->

        <!-- NOTIFICATION DROPDOWN -->
        <!-- <div class="notif-dropdown" id="notif-dropdown"> -->
            <!-- <div class="notif-header">
                <p>Notifications</p>
                <span id="mark-all-read">Mark all as read</span>
            </div>
            <div class="notif-list" id="notif-list">
                <div class="notif-item">
                    <div class="notif-body">
                        <p class="notif-text">Loading notifications...</p>
                    </div>
                </div>
            </div>
            <div class="notif-footer">
                <a href="notifications.php">View all notifications</a>
            </div>
        </div> -->

        <!-- PROFILE DROPDOWN -->
        <div class="topbar-profile" id="profile-toggle">
            <div class="topbar-avatar"><?= $initials ?></div>
            <div class="topbar-profile-info">
                <span class="topbar-name"><?= htmlspecialchars(ucwords($adminName)) ?></span>
                <span class="topbar-role">Administrator</span>
            </div>
            <i class="fas fa-chevron-down topbar-caret" id="profile-caret"></i>

            <!-- DROPDOWN -->
            <div class="profile-dropdown-menu" id="profile-dropdown">
                <div class="dropdown-header">
                    <div class="dropdown-avatar"><?= $initials ?></div>
                    <div>
                        <p class="dropdown-name"><?= htmlspecialchars(ucwords($adminName)) ?></p>
                        <p class="dropdown-role">Administrator</p>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <!-- <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a> -->
                <div class="dropdown-divider"></div>
                <a href="../../controllers/users/LogoutController.php" class="dropdown-item dropdown-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

    </div>
</div>