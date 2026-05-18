<?php
require_once "../../autoload.php";
ob_start();

$title = "Forgot Password";
$page_js = '../../assets/js/user.js';

$left_headline = "Reset and<br><em>ride again.</em>";
$left_desc = "Update your GoraVan password and get back to managing your bookings.";
$left_features = [
    [
        'icon' => 'fas fa-shield-halved',
        'title' => 'Simple Reset',
        'desc' => 'Use your registered email and choose a new password.',
    ],
    [
        'icon' => 'fas fa-key',
        'title' => 'Secure Password',
        'desc' => 'Your new password is stored as a protected hash.',
    ],
    [
        'icon' => 'fas fa-right-to-bracket',
        'title' => 'Back to Login',
        'desc' => 'Sign in right away after resetting your password.',
    ],
];
?>

<section class="auth-container">
    <h2>Reset <span>password</span></h2>
    <span class="sub-header">Enter your account email and set a new password.</span>
</section>

<div class="auth-input">
    <form method="POST" action="../../controllers/ForgotPasswordController.php">
        <?= csrf_field() ?>

        <div class="input-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" placeholder="example@gmail.com" required>
        </div>

        <div class="input-group">
            <label for="password">New Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="At least 8 characters" required minlength="8">
                <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div class="input-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat new password" required minlength="8">
                <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">Update Password</button>
    </form>

    <div class="auth-divider"></div>

    <p class="auth-footer">
        Remembered your password? <a href="login.php">Back to login</a>
    </p>
</div>

<?php
$content = ob_get_clean();
include "../layout/auth.php";
?>
