<?php
require_once "../../autoload.php";
ob_start();

$title = "Login";
$page_js = '../../assets/js/user.js';

$left_headline = "Your seat is<br><em>waiting.</em>";
$left_desc = "Sign back in to GoraVan and manage bookings, check schedules, and travel across Southern Leyte with ease.";
$left_features = [
    [
        'icon' => 'fas fa-couch',
        'title' => 'Pick Your Seat',
        'desc' => 'Choose your preferred seat from a visual van layout before confirming.',
    ],
    [
        'icon' => 'fas fa-ticket',
        'title' => 'Manage Bookings',
        'desc' => 'Track the status of all your reservations in one place.',
    ],
    [
        'icon' => 'fas fa-location-dot',
        'title' => 'Live Trip Status',
        'desc' => 'Know when your van is boarding, departed, or has arrived.',
    ],
];

$oldLogin = $_SESSION['old_login'] ?? [];
unset($_SESSION['old_login']);
?>

<section class="auth-container auth-title-with-vanny">
    <div>
        <h2>Welcome <span>back</span></h2>
        <span class="sub-header">Sign in to your GoraVan account to continue.</span>
    </div>

    <?= vanny_mascot('wave', 'small', 'auth-title-vanny', 'Vanny welcomes you back') ?>
</section>

<div class="auth-input">
    <form id="loginForm" method="POST" action="../../controllers/LoginController.php">
        <?= csrf_field() ?>

        <div class="input-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($oldLogin['email'] ?? '') ?>"
                placeholder="example@gmail.com" autocomplete="email" required>
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Enter your password"
                    autocomplete="current-password" required>
                <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>

        <button type="submit" class="btn-login">Log In</button>
    </form>

    <div class="auth-divider"></div>

    <p class="auth-footer">
        Don't have an account? <a href="register.php">Create one here</a>
    </p>
</div>

<?php
$content = ob_get_clean();
include "../layout/auth.php";
?>