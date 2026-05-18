<?php
require_once "../../autoload.php";

$title    = 'Drivers';
$page_css = '../../assets/css/drivers.css';
$page_js  = '../../assets/js/drivers-js.js';

ob_start();

$driverObj = new Drivers($conn);
$drivers   = $driverObj->GetAllDrivers();
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="driver-search" placeholder="Search drivers...">
    </div>
    <div class="admin-date-filters" data-filter-scope="drivers">
        <label>
            <span>Status</span>
            <select id="driver-status-filter">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>
        <label><span>From</span><input type="date" id="driver-date-from"></label>
        <label><span>To</span><input type="date" id="driver-date-to"></label>
        <button type="button" class="filter-btn ghost" id="driver-date-clear">Clear</button>
    </div>
    <button class="btn-add" id="open-add-modal">
        <i class="fas fa-plus"></i> Add Driver
    </button>
</div>

<div class="drivers-wrapper">

    <div class="drivers-card">
        <div class="drivers-card-header">
            <h2>
                <i class="fas fa-user-tie" style="margin-right:7px;color:var(--color-accent)"></i>
                All Drivers
            </h2>
            <span id="driver-count"></span>
        </div>
        <div class="drivers-table-wrap">
            <table class="drivers-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>License Number</th>
                        <th>Contact</th>
                        <th>Login Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="drivers-tbody">
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-user-tie"></i>
                                    <p>No drivers yet. Add your first driver.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $driverStatusGroups = [
                            'active' => ['label' => 'Active Drivers', 'icon' => 'fas fa-circle-check', 'hint' => 'Available for schedules'],
                            'inactive' => ['label' => 'Inactive Drivers', 'icon' => 'fas fa-ban', 'hint' => 'Not assignable'],
                        ];
                        $currentGroup = '';
                        foreach ($drivers as $i => $d):
                            $group = $driverStatusGroups[$d['status']] ?? ['label' => 'Other Drivers', 'icon' => 'fas fa-user-tie', 'hint' => 'Other statuses'];
                            if ($currentGroup !== $d['status']):
                                $currentGroup = $d['status'];
                        ?>
                            <tr class="admin-status-group-row" data-group-key="<?= htmlspecialchars($currentGroup, ENT_QUOTES) ?>">
                                <td colspan="8">
                                    <div class="admin-status-group-label">
                                        <i class="<?= htmlspecialchars($group['icon'], ENT_QUOTES) ?>"></i>
                                        <span><?= htmlspecialchars($group['label']) ?></span>
                                        <small><?= htmlspecialchars($group['hint']) ?></small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                            <tr class="driver-row status-<?= htmlspecialchars($d['status'], ENT_QUOTES) ?>"
                                data-id="<?= $d['driver_id_pk'] ?>"
                                data-fullname="<?= htmlspecialchars($d['full_name'], ENT_QUOTES) ?>"
                                data-license="<?= htmlspecialchars($d['license_number'], ENT_QUOTES) ?>"
                                data-contact="<?= htmlspecialchars($d['contact_number'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($d['login_email'] ?? '', ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars($d['status'], ENT_QUOTES) ?>"
                                data-created="<?= htmlspecialchars($d['created_at'] ?? '', ENT_QUOTES) ?>">

                                <td class="text-muted-sm"><?= $i + 1 ?></td>

                                <td>
                                    <div class="name-display">
                                        <i class="fas fa-user" style="color:#9ca3af;font-size:11px"></i>
                                        <?= htmlspecialchars($d['full_name']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="license-display">
                                        <i class="fas fa-id-badge" style="color:var(--color-accent);font-size:11px"></i>
                                        <span class="license-number"><?= htmlspecialchars($d['license_number']) ?></span>
                                    </div>
                                </td>

                                <td><?= htmlspecialchars($d['contact_number']) ?></td>

                                <td class="text-muted-sm"><?= htmlspecialchars($d['login_email'] ?? 'No login') ?></td>

                                <td>
                                    <span class="badge <?= $d['status'] ?>">
                                        <?= ucfirst($d['status']) ?>
                                    </span>
                                </td>

                                <td class="text-muted-sm">
                                    <?= date('M d, Y', strtotime($d['created_at'])) ?>
                                </td>

                                <td>
                                    <div class="row-actions">
                                        <button class="icon-btn edit" title="Edit"
                                            data-id="<?= $d['driver_id_pk'] ?>"
                                            data-fullname="<?= htmlspecialchars($d['full_name'], ENT_QUOTES) ?>"
                                            data-license="<?= htmlspecialchars($d['license_number'], ENT_QUOTES) ?>"
                                            data-contact="<?= htmlspecialchars($d['contact_number'], ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($d['login_email'] ?? '', ENT_QUOTES) ?>"
                                            data-status="<?= htmlspecialchars($d['status'], ENT_QUOTES) ?>">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="icon-btn toggle" title="Toggle Status"
                                            data-id="<?= $d['driver_id_pk'] ?>"
                                            data-status="<?= htmlspecialchars($d['status'], ENT_QUOTES) ?>">
                                            <i class="fas fa-<?= $d['status'] === 'active' ? 'toggle-on' : 'toggle-off' ?>"></i>
                                        </button>
                                        <button class="icon-btn delete" title="Delete"
                                            data-id="<?= $d['driver_id_pk'] ?>"
                                            data-fullname="<?= htmlspecialchars($d['full_name'], ENT_QUOTES) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DRIVER PREVIEW CARD -->
    <div class="driver-card">
        <div class="driver-card-header">
            <i class="fas fa-id-badge"></i>
            <p>Driver Details</p>
            <span id="driver-label">Select a driver</span>
        </div>

        <div id="driver-empty" class="driver-empty">
            <i class="fas fa-user-tie"></i>
            <p>Click a driver to view details</p>
        </div>

        <div id="driver-preview" style="display:none;">
            <div class="driver-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="driver-details">
                <h3 id="preview-name">—</h3>
                <div class="detail-row">
                    <span class="detail-label">License</span>
                    <span id="preview-license" class="detail-value">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Contact</span>
                    <span id="preview-contact" class="detail-value">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Login Email</span>
                    <span id="preview-email" class="detail-value">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span id="preview-status" class="detail-badge">—</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon"><i class="fas fa-user-tie"></i></div>
                <div>
                    <h6 class="rmodal-title">Add New Driver</h6>
                    <p class="rmodal-sub">Register a new driver</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../../controllers/Drivers/AddDriver.php">
                <div class="rmodal-body">
                    <?= csrf_field() ?>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="full_name" class="rinput"
                            placeholder="e.g. Juan Dela Cruz"
                            maxlength="255"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-id-badge"></i> License Number</label>
                        <input type="text" name="license_number" class="rinput"
                            placeholder="e.g. N12-3456789"
                            maxlength="30"
                            style="text-transform:uppercase"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="tel" name="contact_number" class="rinput"
                            placeholder="e.g. 0917-123-4567"
                            maxlength="20"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-envelope"></i> Login Email</label>
                        <input type="email" name="email" class="rinput"
                            placeholder="driver@example.com"
                            maxlength="255"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-key"></i> Temporary Password</label>
                        <input type="text" name="password" class="rinput"
                            placeholder="At least 8 characters"
                            minlength="8"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" class="ss" data-placeholder="Select status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="rmodal-footer">
                    <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="rbtn rbtn-primary"><i class="fas fa-plus me-1"></i> Add Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon edit"><i class="fas fa-pen"></i></div>
                <div>
                    <h6 class="rmodal-title">Edit Driver</h6>
                    <p class="rmodal-sub">Update driver details</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDriverForm">
                <div class="rmodal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="driver_id" id="edit-id">

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="full_name" id="edit-fullname" class="rinput"
                            placeholder="e.g. Juan Dela Cruz"
                            maxlength="255"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-id-badge"></i> License Number</label>
                        <input type="text" name="license_number" id="edit-license" class="rinput"
                            placeholder="e.g. N12-3456789"
                            maxlength="30"
                            style="text-transform:uppercase"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="tel" name="contact_number" id="edit-contact" class="rinput"
                            placeholder="e.g. 0917-123-4567"
                            maxlength="20"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-envelope"></i> Login Email</label>
                        <input type="email" name="email" id="edit-email" class="rinput"
                            maxlength="255"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-key"></i> New Password</label>
                        <input type="text" name="password" id="edit-password" class="rinput"
                            placeholder="Leave blank to keep current password"
                            minlength="8">
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" id="edit-status" class="ss" data-placeholder="Select status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="rmodal-footer">
                    <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="rbtn rbtn-primary"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>
