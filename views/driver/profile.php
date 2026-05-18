<?php
require_once '../../autoload.php';

$title = 'Edit Profile';
$page_css = '../../assets/css/driver-dashboard.css';

$driverUserId = (int) decrypt($_SESSION['id'] ?? '');
$driverObj = new Drivers($conn);
$driver = $driverUserId ? $driverObj->GetDriverByUserId($driverUserId) : [];

$fullName = $driver['full_name'] ?? '';
$email = $driver['login_email'] ?? '';
$contact = $driver['login_contact_number'] ?? ($driver['contact_number'] ?? '');
$license = $driver['license_number'] ?? '-';

ob_start();
?>

<div class="driver-page">
    <?php if (!$driver): ?>
        <section class="driver-empty-state">
            <i class="fas fa-id-card"></i>
            <h2>Driver account not linked</h2>
            <p>Please ask the administrator to link this login to a driver record.</p>
        </section>
    <?php else: ?>
        <section class="driver-welcome">
            <div>
                <span class="driver-eyebrow">Driver Account</span>
                <h1>Edit Profile</h1>
                <p>Keep your contact details current and change your temporary password when needed.</p>
            </div>
            <div class="driver-license">
                <span>License</span>
                <strong><?= htmlspecialchars($license) ?></strong>
            </div>
        </section>

        <section class="driver-profile-grid">
            <form class="driver-profile-card" method="POST" action="../../controllers/driver/ProfileController.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <div class="driver-section-title">
                    <i class="fas fa-user-pen"></i>
                    <span>Basic Information</span>
                </div>

                <div class="driver-form-grid">
                    <label class="driver-field span-full">
                        <span>Full Name</span>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($fullName) ?>" required>
                    </label>
                    <label class="driver-field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                    </label>
                    <label class="driver-field">
                        <span>Contact Number</span>
                        <input type="tel" name="contact_number" value="<?= htmlspecialchars($contact) ?>" placeholder="09XXXXXXXXX" required>
                    </label>
                    <label class="driver-field span-full">
                        <span>License Number</span>
                        <input type="text" value="<?= htmlspecialchars($license) ?>" readonly>
                    </label>
                </div>

                <div class="driver-form-actions">
                    <button type="submit" class="driver-action-btn">
                        <i class="fas fa-save"></i>
                        Save Profile
                    </button>
                </div>
            </form>

            <form class="driver-profile-card" method="POST" action="../../controllers/driver/ProfileController.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div class="driver-section-title">
                    <i class="fas fa-lock"></i>
                    <span>Change Password</span>
                </div>

                <div class="driver-form-grid">
                    <label class="driver-field span-full">
                        <span>Current Password</span>
                        <input type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <label class="driver-field">
                        <span>New Password</span>
                        <input type="password" name="new_password" autocomplete="new-password" required>
                    </label>
                    <label class="driver-field">
                        <span>Confirm New Password</span>
                        <input type="password" name="confirm_password" autocomplete="new-password" required>
                    </label>
                </div>

                <p class="driver-action-note">Use at least 8 characters with an uppercase letter and a number.</p>

                <div class="driver-form-actions">
                    <button type="submit" class="driver-action-btn">
                        <i class="fas fa-key"></i>
                        Change Password
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout/driver_layout.php';
?>
