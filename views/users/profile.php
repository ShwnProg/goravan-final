<?php
require_once '../../autoload.php';
if (!isset($_SESSION['is_login'])) {
    header('Location: ../auth/login.php');
    exit;
}

ob_start();
$title      = 'Profile';
$active_page = 'profile';
$page_css   = '../../assets/css/user-profile.css';
$page_js    = '../../assets/js/user-profile.js';

// Fetch user
$userId = decrypt($_SESSION['id']);
$um     = new Users($conn);
$um->id = $userId;
$user   = $um->GetUserById();

// Fetch verification (latest record)
$verif              = new Verification($conn);
$verif->user_id_fk  = $userId;
$verif_data         = $verif->GetVerficationStatus();

$verif_status  = $verif_data['status']           ?? null;
$verif_type    = ucfirst($verif_data['document_type'] ?? '');
$verif_reason  = htmlspecialchars($verif_data['rejection_reason'] ?? '');
$approved_type = ucfirst($verif_data['approved_document_type'] ?? '');
$has_approved  = !empty($approved_type);

// Used by the profile header badge (preserved from original)
$user_status   = $verif_data;
$type          = $verif_type;
?>

<div class="u-body">

    <div class="u-prof-header">
        <div class="u-prof-avatar">
            <?= strtoupper(substr($user['firstname'] ?? 'U', 0, 1))
              . strtoupper(substr($user['lastname']  ?? '',  0, 1)) ?>
        </div>
        <div class="u-prof-main">
            <div class="u-prof-identity">
                <h2 class="u-prof-name">
                    <?= htmlspecialchars(ucfirst($user['firstname'] ?? '')) ?>
                    <?= htmlspecialchars(ucfirst($user['lastname']  ?? '')) ?>
                </h2>
                <p class="u-prof-email"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                <?php if ($verif_status || $has_approved): ?>
                    <span class="u-prof-badge <?= htmlspecialchars($has_approved ? 'approved' : $verif_status) ?>">
                        <?php if ($has_approved && $verif_status === 'pending'): ?>
                            <i class="fa-solid fa-clock"></i> Verified <?= htmlspecialchars($approved_type) ?> - update under review
                        <?php elseif ($has_approved && $verif_status === 'rejected'): ?>
                            <i class="fa-solid fa-circle-check"></i> Verified <?= htmlspecialchars($approved_type) ?> - update rejected
                        <?php elseif ($verif_status === 'pending'): ?>
                            <i class="fa-solid fa-clock"></i> <?= htmlspecialchars($type) ?> under review
                        <?php elseif ($verif_status === 'approved'): ?>
                            <i class="fa-solid fa-circle-check"></i> Verified <?= htmlspecialchars($type) ?>
                        <?php elseif ($verif_status === 'rejected'): ?>
                            <i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($type) ?> rejected
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="u-prof-verif">
                <?php if (!$verif_status): ?>
                    <div class="u-verif-row">
                        <div class="u-verif-info">
                            <p class="u-verif-title">Unverified Account</p>
                            <p class="u-verif-desc">
                                Verify as a Student, Senior Citizen, or PWD to unlock exclusive fare discounts.
                            </p>
                        </div>
                        <button type="button"
                                class="u-btn u-btn-primary verif-trigger"
                                data-action="submit_verification"
                                data-bs-toggle="modal" data-bs-target="#verifModal">
                            <i class="fa-solid fa-upload"></i> Verify Now
                        </button>
                    </div>
                <?php elseif ($verif_status === 'pending' && !$has_approved): ?>
                    <div class="u-verif-row">
                        <div class="u-verif-info">
                            <p class="u-verif-title">Verification Pending</p>
                            <p class="u-verif-desc">
                                We are reviewing your documents.
                            </p>
                        </div>
                    </div>
                <?php elseif ($verif_status === 'approved' || $has_approved): ?>
                    <div class="u-verif-row">
                        <div class="u-verif-info">
                            <p class="u-verif-title"><?= htmlspecialchars($has_approved ? $approved_type : $verif_type) ?> Verified</p>
                            <p class="u-verif-desc">
                                <?php if ($verif_status === 'pending'): ?>
                                    Your current verification remains active while your update is under review.
                                <?php elseif ($verif_status === 'rejected' && $verif_reason): ?>
                                    Your current verification remains active. Latest update was rejected: <?= $verif_reason ?>
                                <?php elseif ($verif_status === 'rejected'): ?>
                                    Your current verification remains active. Latest update was rejected.
                                <?php else: ?>
                                    Your identity is verified. You're eligible for <?= htmlspecialchars($has_approved ? $approved_type : $verif_type) ?> fare discounts.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($verif_status !== 'pending'): ?>
                            <button type="button"
                                    class="u-sec-link verif-trigger"
                                    data-action="update_verification"
                                    data-bs-toggle="modal" data-bs-target="#verifModal">
                                <i class="fa-solid fa-rotate"></i> Update
                            </button>
                        <?php endif; ?>
                    </div>
                <?php elseif ($verif_status === 'rejected'): ?>
                    <div class="u-verif-row">
                        <div class="u-verif-info">
                            <p class="u-verif-title">Verification Rejected</p>
                            <?php if ($verif_reason): ?>
                                <p class="u-verif-desc u-verif-reason">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <?= $verif_reason ?>
                                </p>
                            <?php else: ?>
                                <p class="u-verif-desc">
                                    Your verification was not approved. Please upload a valid document and resubmit.
                                </p>
                            <?php endif; ?>
                        </div>
                        <button type="button"
                                class="u-btn u-btn-primary verif-trigger"
                                data-action="resubmit_verification"
                                data-bs-toggle="modal" data-bs-target="#verifModal">
                            <i class="fa-solid fa-rotate-right"></i> Resubmit
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="u-sec">
        <div class="u-sec-head">
            <h2 class="u-sec-title">Edit Profile</h2>
        </div>
        <div class="u-form-card">
            <form id = 'editform'>
                <?= csrf_field() ?>
                <!-- <input type="hidden" name="action" value="update_profile"> -->

                <div class="u-form-row">
                    <div class="u-form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="first_name"
                               value="<?= htmlspecialchars($user['firstname'] ?? '') ?>" required>
                    </div>
                    <div class="u-form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="last_name"
                               value="<?= htmlspecialchars($user['lastname'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="u-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>

                <div class="u-form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="contact" name="contact"
                           value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>"
                           placeholder="09XX XXX XXXX">
                </div>

                <button type="submit" class="u-btn u-btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

    <div class="u-sec">
        <div class="u-sec-head">
            <h2 class="u-sec-title">Change Password</h2>
        </div>
        <div class="u-form-card">
            <form id="changePasswordForm">
                <?= csrf_field() ?>
                <!-- <input type="hidden" name="action" value="change_password"> -->

                <div class="u-form-group">
                    <label for="currentPassword">Current Password</label>
                    <input type="password" id="currentPassword" name="current_password" required>
                </div>

                <div class="u-form-row">
                    <div class="u-form-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="new_password"
                               required minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    <div class="u-form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password"
                               required minlength="8" placeholder="Re-enter new password">
                    </div>
                </div>

                <button type="submit" class="u-btn u-btn-primary">Update Password</button>
            </form>
        </div>
    </div>

    <div class="u-sec">
        <div class="u-menu-list">
            <button type="button" class="u-menu-item" data-bs-toggle="modal" data-bs-target="#notificationPreferencesModal">
                <i class="fa-solid fa-bell"></i>
                <span>Notification Preferences</span>
                <i class="fa-solid fa-chevron-right caret"></i>
            </button>
            <!-- <a href="#" class="u-menu-item">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Privacy &amp; Security</span>
                <i class="fa-solid fa-chevron-right caret"></i>
            </a> -->
            <button type="button" class="u-menu-item" data-bs-toggle="modal" data-bs-target="#helpSupportModal">
                <i class="fa-solid fa-circle-question"></i>
                <span>Help &amp; Support</span>
                <i class="fa-solid fa-chevron-right caret"></i>
            </button>
            <div class="u-menu-divider"></div>
            <a href="../../controllers/users/LogoutController.php" class="u-menu-item danger">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </div>

</div><!-- /u-body -->

<div class="modal fade" id="notificationPreferencesModal" tabindex="-1"
     aria-labelledby="notificationPreferencesTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content user-profile-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationPreferencesTitle">
                    <i class="fa-solid fa-bell"></i>
                    Notification Preferences
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="u-pref-list">
                    <label class="u-pref-row">
                        <span class="u-pref-icon"><i class="fa-solid fa-ticket"></i></span>
                        <span class="u-pref-copy">
                            <strong>Booking updates</strong>
                            <small>Approvals, cancellations, and completed trips.</small>
                        </span>
                        <input type="checkbox" data-notif-pref="booking">
                    </label>
                    <label class="u-pref-row">
                        <span class="u-pref-icon"><i class="fa-solid fa-clock"></i></span>
                        <span class="u-pref-copy">
                            <strong>Trip reminders</strong>
                            <small>Upcoming boarding reminders for approved bookings.</small>
                        </span>
                        <input type="checkbox" data-notif-pref="reminder">
                    </label>
                    <label class="u-pref-row">
                        <span class="u-pref-icon"><i class="fa-solid fa-receipt"></i></span>
                        <span class="u-pref-copy">
                            <strong>Payment receipts</strong>
                            <small>Successful payment and receipt notifications.</small>
                        </span>
                        <input type="checkbox" data-notif-pref="payment">
                    </label>
                    <label class="u-pref-row">
                        <span class="u-pref-icon"><i class="fa-solid fa-id-card-clip"></i></span>
                        <span class="u-pref-copy">
                            <strong>Verification</strong>
                            <small>Student, Senior Citizen, and PWD verification results.</small>
                        </span>
                        <input type="checkbox" data-notif-pref="verification">
                    </label>
                    <label class="u-pref-row">
                        <span class="u-pref-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                        <span class="u-pref-copy">
                            <strong>New schedules</strong>
                            <small>Fresh available route schedules.</small>
                        </span>
                        <input type="checkbox" data-notif-pref="schedule">
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="resetNotificationPrefs">Reset</button>
                <button type="button" class="btn btn-primary" id="saveNotificationPrefs">Save Preferences</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="helpSupportModal" tabindex="-1"
     aria-labelledby="helpSupportTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content user-profile-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="helpSupportTitle">
                    <i class="fa-solid fa-circle-question"></i>
                    Help &amp; Support
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="u-help-grid">
                    <a href="schedule.php" class="u-help-card">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                        <span>
                            <strong>Find a trip</strong>
                            <small>Search available routes and seats.</small>
                        </span>
                    </a>
                    <a href="my-bookings.php" class="u-help-card">
                        <i class="fa-solid fa-list-check"></i>
                        <span>
                            <strong>Manage bookings</strong>
                            <small>Review status, seats, driver, and van details.</small>
                        </span>
                    </a>
                    <a href="my-payments.php" class="u-help-card">
                        <i class="fa-solid fa-wallet"></i>
                        <span>
                            <strong>Payment receipts</strong>
                            <small>Open your receipt history and download copies.</small>
                        </span>
                    </a>
                </div>
                <div class="u-support-note">
                    <i class="fa-solid fa-headset"></i>
                    <span>For urgent trip concerns, show your booking reference at the terminal support desk.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="verifModal" tabindex="-1"
     aria-labelledby="verifModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="verifModalLabel">
                    <i class="fa-solid fa-id-card me-2" style="color:var(--u-accent);"></i>
                    <span id="verifModalTitle">Submit Verification</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="verifAction" value="">
                <input type="hidden" id="verifCsrf"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="u-form-group">
                    <label for="verifType">Verification Type</label>
                    <select id="verifType" class="form-select">
                        <option value="">Select type</option>
                        <option value="student">Student</option>
                        <option value="senior">Senior Citizen</option>
                        <option value="pwd">PWD (Person with Disability)</option>
                    </select>
                </div>

                <div class="u-form-group">
                    <label for="verifDoc">Upload Supporting Document</label>
                    <label for="verifDoc" class="u-file-upload">
                        <input type="file" id="verifDoc"
                               accept=".jpg,.jpeg,.png,.pdf">
                        <span class="u-file-copy">
                            <span class="u-file-title" id="verifFileName">No file selected</span>
                        </span>
                        <span class="u-file-action">
                            <i class="fa-solid fa-folder-open"></i>
                            Choose
                        </span>
                    </label>
                    <small class="u-verif-hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Accepted: JPG, PNG, PDF &mdash; max 5 MB
                    </small>
                </div>

                <div id="verifAlert" class="u-verif-alert d-none"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSubmitVerif">
                    <span id="verifBtnText">Submit</span>
                    <span id="verifBtnSpinner"
                          class="spinner-border spinner-border-sm ms-1 d-none"
                          role="status" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/user_layout.php';
?>
