<?php
require_once "../../autoload.php";
ob_start();

$title    = 'Profile';
$page_css = '../../assets/css/profile.css';
$page_js  = '../../assets/js/profile-js.js';

// Fetch current admin info
$adminId = decrypt($_SESSION['id']);
if (!$adminId || !is_numeric($adminId)) {
    header("Location: ../../views/auth/login.php");
    exit;
}

$admin     = new Admin($conn);
$admin->id = (int)$adminId;
$info      = $admin->Read();

if (!$info) {
    header("Location: ../../views/auth/login.php");
    exit;
}

$firstName = htmlspecialchars($info['firstname'] ?? '');
$lastName  = htmlspecialchars($info['lastname']  ?? '');
$email     = htmlspecialchars($info['email']           ?? '');
$contact   = htmlspecialchars($info['contact_number']  ?? '');
?>

<!-- Page header -->
<!-- <div class="toolbar">
    <div>
        <p style="margin:0;font-size:20px;font-weight:700;color:var(--color-primary);">Settings</p>
        <p style="margin:0;font-size:12px;color:var(--text-muted);margin-top:2px;">Manage your account and preferences</p>
    </div>
</div> -->

<!-- ── SETTINGS GRID ─────────────────────────────────────────────── -->
<div class="settings-grid">

<!-- Profile settings -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <h2>Profile Settings</h2>
                <p>Update your personal information</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form id="form-profile" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="rfield name-row">
                    <div class="rfield-half">
                        <label class="rfield-label">First Name</label>
                        <input
                            type="text"
                            name="first_name"
                            class="rinput"
                            value="<?= $firstName ?>"
                            placeholder="First name"
                            required
                        >
                    </div>
                    <div class="rfield-half">
                        <label class="rfield-label">Last Name</label>
                        <input
                            type="text"
                            name="last_name"
                            class="rinput"
                            value="<?= $lastName ?>"
                            placeholder="Last name"
                            required
                        >
                    </div>
                </div>

                <div class="rfield">
                    <label class="rfield-label">Email Address</label>
                    <input
                        type="email"
                        name="email"
                        class="rinput"
                        value="<?= $email ?>"
                        placeholder="your@email.com"
                        required
                    >
                </div>

                <div class="rfield">
                    <label class="rfield-label">Contact Number</label>
                    <input
                        type="text"
                        name="contact_number"
                        class="rinput"
                        value="<?= $contact ?>"
                        placeholder="e.g. 09xxxxxxxxx"
                    >
                </div>

                <div id="profile-feedback" class="settings-feedback"></div>

                <div style="display:flex;justify-content:flex-end;margin-top:4px;">
                    <button type="submit" class="rbtn rbtn-primary" data-submit>
                        <i class="fas fa-save" style="margin-right:7px;"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- Change password -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">
                <i class="fas fa-lock"></i>
            </div>
            <div>
                <h2>Change Password</h2>
                <p>Update your login password</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form id="form-password" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="rfield">
                    <label class="rfield-label">Current Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="current_password"
                            class="rinput"
                            placeholder="Enter current password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="rfield">
                    <label class="rfield-label">New Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="new_password"
                            class="rinput"
                            placeholder="Min. 8 characters"
                            autocomplete="new-password"
                            required
                        >
                        <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="rfield-hint">At least 8 characters</span>
                </div>

                <div class="rfield">
                    <label class="rfield-label">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="confirm_password"
                            class="rinput"
                            placeholder="Repeat new password"
                            autocomplete="new-password"
                            required
                        >
                        <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div id="password-feedback" class="settings-feedback"></div>

                <div style="display:flex;justify-content:flex-end;margin-top:4px;">
                    <button type="submit" class="rbtn rbtn-primary" data-submit>
                        <i class="fas fa-key" style="margin-right:7px;"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── APPEARANCE ─────────────────────────────────────────────── -->
    <div class="settings-card settings-full">
        <div class="settings-card-header">
            <div class="settings-card-icon">
                <i class="fas fa-moon"></i>
            </div>
            <div>
                <h2>Appearance</h2>
                <p>Customize your admin interface</p>
            </div>
        </div>
        <div class="settings-card-body">
            <div class="settings-toggle-row">
                <div class="settings-toggle-info">
                    <h3>Dark Mode</h3>
                    <p>Switch to a darker color scheme. Your preference is saved automatically.</p>
                </div>
                <label class="toggle-switch" title="Toggle dark mode">
                    <input type="checkbox" id="dark-mode-toggle">
                    <span class="toggle-track"></span>
                </label>
            </div>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>