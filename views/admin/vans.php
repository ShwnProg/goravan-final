<?php
require_once "../../autoload.php";

if (empty($_SESSION['is_login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$title = 'Vans';
$page_js = '../../assets/js/vans-js.js';

ob_start();

$vanObj = new Vans($conn);
$vans = $vanObj->GetAllVans();
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="van-search" placeholder="Search vans…">
    </div>
    <div class="filter-group">
        <select class="filter-select" id="van-status-filter">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>
    <div class="admin-date-filters" data-filter-scope="vans">
        <label><span>From</span><input type="date" id="van-date-from"></label>
        <label><span>To</span><input type="date" id="van-date-to"></label>
        <button type="button" class="filter-btn ghost" id="van-date-clear">Clear</button>
    </div>
    <button class="btn-add" id="open-add-modal">
        <i class="fas fa-plus"></i> Add Van
    </button>
</div>

<input type="hidden" id="page-csrf-token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

<div class="vans-card">
    <div class="vans-card-header">
        <h2>
            <i class="fas fa-van-shuttle" style="margin-right:7px;color:var(--color-accent)"></i>
            All Vans
        </h2>
        <span id="van-count"></span>
    </div>
    <div class="vans-table-wrap">
        <table class="vans-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Van / Model</th>
                    <th>Plate Number</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="vans-tbody">
                <?php if (empty($vans)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <?= vanny_mascot('letsGo', 'small', 'admin-empty-vanny', 'Vanny ready for vans') ?>
                                <p>No vans yet. Add your first van.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $vanStatusGroups = [
                        'active' => ['label' => 'Active Vans', 'icon' => 'fas fa-circle-check', 'hint' => 'Available for schedules'],
                        'inactive' => ['label' => 'Inactive Vans', 'icon' => 'fas fa-ban', 'hint' => 'Not assignable'],
                    ];
                    $currentGroup = '';
                    foreach ($vans as $i => $v):
                        $group = $vanStatusGroups[$v['status']] ?? ['label' => 'Other Vans', 'icon' => 'fas fa-van-shuttle', 'hint' => 'Other statuses'];
                        if ($currentGroup !== $v['status']):
                            $currentGroup = $v['status'];
                    ?>
                        <tr class="admin-status-group-row" data-group-key="<?= htmlspecialchars($currentGroup, ENT_QUOTES) ?>">
                            <td colspan="7">
                                <div class="admin-status-group-label">
                                    <i class="<?= htmlspecialchars($group['icon'], ENT_QUOTES) ?>"></i>
                                    <span><?= htmlspecialchars($group['label']) ?></span>
                                    <small><?= htmlspecialchars($group['hint']) ?></small>
                                </div>
                            </td>
                        </tr>
                    <?php
                        endif;
                        $seatsJson = htmlspecialchars(
                            json_encode(array_map(fn($s) => [
                                'seat_number' => $s['seat_number'],
                                'seat_row' => $s['seat_row'],
                                'seat_col' => $s['seat_col'],
                            ], $v['seats'])),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                        <tr class="van-row status-<?= htmlspecialchars($v['status'], ENT_QUOTES) ?>" data-id="<?= $v['van_id_pk'] ?>"
                            data-plate="<?= htmlspecialchars($v['plate_number'], ENT_QUOTES) ?>"
                            data-model="<?= htmlspecialchars($v['model'], ENT_QUOTES) ?>"
                            data-capacity="<?= (int) $v['capacity'] ?>" data-passenger-capacity="<?= (int) $v['capacity'] ?>"
                            data-status="<?= htmlspecialchars($v['status'], ENT_QUOTES) ?>"
                            data-created="<?= htmlspecialchars($v['created_at'] ?? '', ENT_QUOTES) ?>"
                            data-seats="<?= $seatsJson ?>">

                            <td class="text-muted-sm"><?= $i + 1 ?></td>

                            <td>
                                <div class="model-display">
                                    <i class="fas fa-car-side" style="color:#9ca3af;font-size:11px"></i>
                                    <?= htmlspecialchars($v['model']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="plate-display">
                                    <i class="fas fa-id-card" style="color:var(--color-accent);font-size:11px"></i>
                                    <span class="plate-number"><?= htmlspecialchars($v['plate_number']) ?></span>
                                </div>
                            </td>

                            <td>
                                <span class="capacity-badge">
                                    <i class="fas fa-chair" style="font-size:10px"></i>
                                    <?= (int) $v['capacity'] ?> seats
                                </span>
                            </td>

                            <td>
                                <span class="badge <?= $v['status'] ?>">
                                    <?= ucfirst($v['status']) ?>
                                </span>
                            </td>

                            <td class="text-muted-sm">
                                <?= date('M d, Y', strtotime($v['created_at'])) ?>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn view" title="View Seats" data-id="<?= $v['van_id_pk'] ?>"
                                        data-seats="<?= $seatsJson ?>"
                                        data-plate="<?= htmlspecialchars($v['plate_number'], ENT_QUOTES) ?>"
                                        data-model="<?= htmlspecialchars($v['model'], ENT_QUOTES) ?>"
                                        data-capacity="<?= (int) $v['capacity'] ?>"
                                        data-status="<?= htmlspecialchars($v['status'], ENT_QUOTES) ?>">
                                        <i class="fas fa-chair"></i>
                                    </button>
                                    <button class="icon-btn edit" title="Edit" data-id="<?= $v['van_id_pk'] ?>"
                                        data-plate="<?= htmlspecialchars($v['plate_number'], ENT_QUOTES) ?>"
                                        data-model="<?= htmlspecialchars($v['model'], ENT_QUOTES) ?>"
                                        data-capacity="<?= (int) $v['capacity'] ?>"
                                        data-status="<?= htmlspecialchars($v['status'], ENT_QUOTES) ?>">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="icon-btn toggle" title="Toggle Status" data-id="<?= $v['van_id_pk'] ?>"
                                        data-status="<?= htmlspecialchars($v['status'], ENT_QUOTES) ?>">
                                        <i class="fas fa-<?= $v['status'] === 'active' ? 'toggle-on' : 'toggle-off' ?>"></i>
                                    </button>
                                    <button class="icon-btn delete" title="Delete" data-id="<?= $v['van_id_pk'] ?>"
                                        data-plate="<?= htmlspecialchars($v['plate_number'], ENT_QUOTES) ?>">
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

<!-- ══════════════════════════════════════════
     ADD MODAL
     ══════════════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon"><i class="fas fa-van-shuttle"></i></div>
                <div>
                    <h6 class="rmodal-title">Add New Van</h6>
                    <p class="rmodal-sub">Register a van and auto-generate its seats</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../../controllers/vans/AddVan.php">
                <div class="rmodal-body">
                    <?= csrf_field() ?>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-id-card"></i> Plate Number</label>
                        <input type="text" name="plate_number" class="rinput" placeholder="e.g. ABC-1234" maxlength="20"
                            style="text-transform:uppercase" required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-car-side"></i> Model</label>
                        <input type="text" name="model" class="rinput" placeholder="e.g. Toyota Hi-Ace" maxlength="255"
                            required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-chair"></i> Capacity</label>
                        <input type="number" name="capacity" class="rinput" placeholder="e.g. 14" min="1" max="14"
                            step="1" required>
                        <span class="rfield-hint">Seats will be auto-generated (A1, A2, B1, B2…)</span>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" class="rinput">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="rmodal-footer">
                    <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="rbtn rbtn-primary">
                        <i class="fas fa-plus me-1"></i> Add Van
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     EDIT MODAL
     ══════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon edit"><i class="fas fa-pen"></i></div>
                <div>
                    <h6 class="rmodal-title">Edit Van</h6>
                    <p class="rmodal-sub">Update van details</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../../controllers/vans/EditVan.php">
                <div class="rmodal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="van_id" id="edit-id">

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-id-card"></i> Plate Number</label>
                        <input type="text" name="plate_number" id="edit-plate" class="rinput"
                            placeholder="e.g. ABC-1234" maxlength="20" style="text-transform:uppercase" required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-car-side"></i> Model</label>
                        <input type="text" name="model" id="edit-model" class="rinput" placeholder="e.g. Toyota Hi-Ace"
                            maxlength="255" required>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-chair"></i> Capacity</label>
                        <input type="number" name="capacity" id="edit-capacity" class="rinput" placeholder="e.g. 14"
                            min="1" max="14" step="1" required>
                        <span class="rfield-hint">Capacity changes keep booked seats intact.</span>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" id="edit-status" class="rinput">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="rmodal-footer">
                    <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="rbtn rbtn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     VIEW SEAT LAYOUT MODAL
     ══════════════════════════════════════════ -->
<div class="modal fade" id="seatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon"><i class="fas fa-chair"></i></div>
                <div>
                    <h6 class="rmodal-title" id="seat-modal-title">Seat Layout</h6>
                    <p class="rmodal-sub" id="seat-modal-sub">Van seat arrangement</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="rmodal-body">
                <div class="van-seat-viewer">
                    <!-- Windshield bar (decorative top) -->
                    <div class="vsv-windshield">
                        <i class="fas fa-car-side"></i>
                        <span>FRONT</span>
                    </div>
                    <!-- 3×5 seat grid — rendered by JS.
                         [DRIVER][S1][S2]
                         [S3][S4][S5]
                         [S6][S7][S8]
                         [S9][S10][S11]
                         [S12][S13][S14]  -->
                    <div class="vsv-grid" id="vsv-grid"></div>
                    <!-- Legend -->
                    <div class="vsv-legend">
                        <span class="vsv-legend-item vsv-driver-dot">Driver</span>
                        <span class="vsv-legend-item vsv-available-dot">Available</span>
                        <span class="vsv-legend-item vsv-occupied-dot">Occupied</span>
                    </div>
                </div>
            </div>
            <div class="rmodal-footer">
                <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>
