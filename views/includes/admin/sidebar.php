<div class="sidebar" id="sidebar">

    <div class="logo">
        <img src="../../images/logo.png" alt="logo">
        <p>Gora<span>Van</span></p>
    </div>

    <div class="sidebar-menu">

        <div class="menu-section">
            <label>Overview</label>
            <a href="index.php" class="menu-btn <?= $current == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
        </div>

        <div class="menu-section">
            <label>Bookings</label>
            <a href="bookings.php" class="menu-btn <?= $current == 'bookings.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i><span>Bookings</span>
            </a>
            <a href="schedules.php" class="menu-btn <?= $current == 'schedules.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar"></i><span>Schedules</span>
            </a>
            <a href="payments.php" class="menu-btn <?= $current == 'payments.php' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i><span>Payments</span>
            </a>
        </div>

        <div class="menu-section">
            <label>Fleet</label>
            <a href="routes.php" class="menu-btn <?= $current == 'routes.php' ? 'active' : '' ?>">
                <i class="fas fa-road"></i><span>Routes</span>
            </a>
            <a href="vans.php" class="menu-btn <?= $current == 'vans.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-van-shuttle"></i><span>Vans</span>
            </a>
            <a href="drivers.php" class="menu-btn <?= $current == 'drivers.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-tie"></i><span>Drivers</span>
            </a>
        </div>

        <div class="menu-section">
            <label>Verification</label>
            <a href="users.php" class="menu-btn <?= $current == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-file-circle-check"></i><span>Verifications</span>
            </a>
        </div>

        <div class="menu-section">
            <label>Account</label>
            <!-- <a href="settings.php" class="menu-btn <?= $current == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i><span>Settings</span>
            </a> -->
            <a href="profile.php" class="menu-btn <?= $current == 'profile.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-user"></i><span>Profile</span>
            </a>
            <a href="../../controllers/users/LogoutController.php" class="menu-btn logout-btn">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>

    </div>
</div>
