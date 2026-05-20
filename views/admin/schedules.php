<?php
require_once "../../autoload.php";

$title  = 'Schedules';
/* FIX 1: Remove $page_css — schedules.css is already loaded globally
   by admin_layout head.php. Loading it again caused duplicate styles. */
$page_js = '../../assets/js/schedules-js.js';

ob_start();

$scheduleObj = new Schedules($conn);
$schedules   = $scheduleObj->GetAllSchedules();

$routeObj = new Routes($conn);
$routes   = $routeObj->GetAllRoutes();

$driverObj = new Drivers($conn);
$drivers   = $driverObj->GetAllDrivers();

$vanObj = new Vans($conn);
$vans   = $vanObj->GetAllVans();

$scheduleDates = array_values(array_unique(array_filter(array_map(
    fn($s) => !empty($s['created_at']) ? substr((string) $s['created_at'], 0, 10) : '',
    $schedules
))));
rsort($scheduleDates);
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="schedule-search" placeholder="Search schedules...">
    </div>
    <div class="admin-date-filters" data-filter-scope="schedules">
        <label>
            <span>Created</span>
            <select id="schedule-date-select">
                <option value="">All dates</option>
                <?php foreach ($scheduleDates as $recordDate): ?>
                    <option value="<?= htmlspecialchars($recordDate, ENT_QUOTES) ?>">
                        <?= date('M d, Y', strtotime($recordDate)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>From</span><input type="date" id="schedule-date-from"></label>
        <label><span>To</span><input type="date" id="schedule-date-to"></label>
        <button type="button" class="filter-btn ghost" id="schedule-date-clear">Clear</button>
    </div>
    <button class="btn-add" id="open-add-modal">
        <i class="fas fa-plus"></i> Add Schedule
    </button>
</div>
<div class="admin-status-tabs" id="schedule-status-tabs" aria-label="Schedule status filters">
    <button type="button" class="active" data-status="not_departed">Not Departed</button>
    <button type="button" data-status="departed">Departed Schedules</button>
    <button type="button" data-status="arrived">Arrived Schedules</button>
    <button type="button" data-status="completed">Completed Schedules</button>
    <button type="button" data-status="cancelled">Cancelled Schedules</button>
    <button type="button" data-status="">All Schedules</button>
</div>
<input type="hidden" id="schedule-status-filter" value="not_departed">

<!-- FIX 3: ONE csrf_field() here, outside all modals.
     JS reads the LAST input[name="csrf_token"] on page,
     which will always be this one.
     The modals below also keep their own csrf_field() for
     the native form POST fallback — that is correct. -->
<input type="hidden" name="csrf_token" id="page-csrf-token"
       value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

<div class="schedules-wrapper">

    <!-- TABLE CARD -->
    <div class="schedules-card">
        <div class="schedules-card-header">
            <h2>
                <i class="fas fa-calendar-check" style="margin-right:7px;color:var(--color-accent)"></i>
                <span id="schedule-view-title">Not Departed</span>
            </h2>
            <!-- FIX 4: Removed data-label attribute — JS now handles pluralisation itself -->
            <span id="schedule-count"></span>
        </div>
        <div class="schedules-table-wrap">
            <table class="schedules-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Route</th>
                        <th>Driver</th>
                        <th>Van</th>
                        <th>Departure</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="schedules-tbody">
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <?= vanny_mascot('waiting', 'small', 'admin-empty-vanny', 'Vanny waiting for schedules') ?>
                                    <p>No schedules yet. Add your first schedule.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        foreach ($schedules as $i => $s):
                            $rawStatus = $scheduleObj->NormalizeTripStatus((string) ($s['trip_status'] ?? 'not_departed'));
                            $departure    = date('M d', strtotime($s['departure_date']))
                                          . ' at '
                                          . date('g:i A', strtotime($s['departure_time']));
                            $status       = htmlspecialchars($rawStatus, ENT_QUOTES);
                            $stops        = array_values(array_filter($s['stops'] ?? []));
                            $viaText      = !empty($stops) ? 'via ' . implode(', ', $stops) : '';
                            $routeDisplay = htmlspecialchars($s['route_display'] ?? 'N/A', ENT_QUOTES);
                            $origin       = htmlspecialchars($s['origin'] ?? '', ENT_QUOTES);
                            $destination  = htmlspecialchars($s['destination'] ?? '', ENT_QUOTES);
                            $fullRoute    = htmlspecialchars(($s['route_display'] ?? 'N/A') . ($viaText ? ' · ' . $viaText : ''), ENT_QUOTES);
                            $routeVia     = htmlspecialchars($viaText, ENT_QUOTES);
                            $driverName   = htmlspecialchars($s['driver_name']   ?? 'N/A', ENT_QUOTES);
                            $vanPlate     = htmlspecialchars($s['van_plate']     ?? 'N/A', ENT_QUOTES);
                            $vanModel     = htmlspecialchars($s['van_model']     ?? '', ENT_QUOTES);
                            $vanCapacity  = (int) ($s['van_capacity'] ?? 0);
                            $totalSeats   = (int) ($s['total_seats'] ?? $vanCapacity);
                            $bookedSeats  = (int) ($s['booked_seats'] ?? 0);
                            $availableSeats = (int) ($s['available_seats'] ?? max(0, $totalSeats - $bookedSeats));
                            $createdAt    = !empty($s['created_at'])
                                            ? htmlspecialchars(date('M d, Y g:i A', strtotime($s['created_at'])), ENT_QUOTES)
                                            : '';
                            $createdDate  = !empty($s['created_at'])
                                            ? htmlspecialchars(substr((string) $s['created_at'], 0, 10), ENT_QUOTES)
                                            : '';
                            $updatedAt    = !empty($s['updated_at'])
                                            ? htmlspecialchars(date('M d, Y g:i A', strtotime($s['updated_at'])), ENT_QUOTES)
                                            : '';
                            $arrivedAt    = !empty($s['arrived_at'])
                                            ? htmlspecialchars(date('M d, Y g:i A', strtotime($s['arrived_at'])), ENT_QUOTES)
                                            : '';
                            $departedAt   = !empty($s['departed_at'])
                                            ? htmlspecialchars(date('M d, Y g:i A', strtotime($s['departed_at'])), ENT_QUOTES)
                                            : '';
                            $completedAt  = !empty($s['completed_at'])
                                            ? htmlspecialchars(date('M d, Y g:i A', strtotime($s['completed_at'])), ENT_QUOTES)
                                            : '';
                            $etaValue     = !empty($s['estimated_arrival_at'])
                                            ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($s['estimated_arrival_at'])), ENT_QUOTES)
                                            : '';
                            $etaDisplay   = !empty($s['estimated_arrival_at'])
                                            ? htmlspecialchars(date('M d', strtotime($s['estimated_arrival_at'])) . ' at ' . date('g:i A', strtotime($s['estimated_arrival_at'])), ENT_QUOTES)
                                            : '';
                            $pendingBookings = (int) ($s['pending_bookings_count'] ?? 0);
                        ?>
                            <tr class="schedule-row status-<?= $status ?>"
                                data-id="<?= (int) $s['schedule_id_pk'] ?>"
                                data-route="<?= (int) $s['route_id_fk'] ?>"
                                data-driver="<?= (int) $s['driver_id_fk'] ?>"
                                data-van="<?= (int) $s['van_id_fk'] ?>"
                                data-route-display="<?= $routeDisplay ?>"
                                data-origin="<?= $origin ?>"
                                data-destination="<?= $destination ?>"
                                data-full-route="<?= $fullRoute ?>"
                                data-route-via="<?= $routeVia ?>"
                                data-driver-name="<?= $driverName ?>"
                                data-van-plate="<?= $vanPlate ?>"
                                data-van-model="<?= $vanModel ?>"
                                data-van-capacity="<?= $vanCapacity ?>"
                                data-total-seats="<?= $totalSeats ?>"
                                data-available-seats="<?= $availableSeats ?>"
                                data-booked-seats="<?= $bookedSeats ?>"
                                data-date="<?= htmlspecialchars($s['departure_date'], ENT_QUOTES) ?>"
                                data-filter-date="<?= $createdDate ?>"
                                data-time="<?= htmlspecialchars($s['departure_time'], ENT_QUOTES) ?>"
                                data-eta="<?= $etaValue ?>"
                                data-eta-display="<?= $etaDisplay ?>"
                                data-status="<?= $status ?>"
                                data-pending-bookings="<?= $pendingBookings ?>"
                                data-departed-at="<?= $departedAt ?>"
                                data-arrived-at="<?= $arrivedAt ?>"
                                data-completed-at="<?= $completedAt ?>"
                                data-created-at="<?= $createdAt ?>"
                                data-updated-at="<?= $updatedAt ?>">

                                <td class="text-muted-sm"><?= $i + 1 ?></td>
                                <td>
                                    <div class="route-display">
                                        <i class="fas fa-route" style="color:var(--color-accent);font-size:11px"></i>
                                        <div class="route-stack">
                                            <span><?= $routeDisplay ?></span>
                                            <?php if ($viaText): ?>
                                                <small><?= htmlspecialchars($viaText) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="driver-display">
                                        <i class="fas fa-user-tie" style="color:#9ca3af;font-size:11px"></i>
                                        <?= $driverName ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="plate-display">
                                        <i class="fas fa-van-shuttle" style="color:var(--color-accent);font-size:11px"></i>
                                        <span class="plate-number"><?= $vanPlate ?></span>
                                    </div>
                                </td>
                                <td class="text-muted-sm"><?= $departure ?></td>
                                <td>
                                    <!-- FIX 5: ucfirst + str_replace for display,
                                         but data-status keeps the raw value for JS logic -->
                                    <span class="badge <?= $status ?>">
                                        <?= htmlspecialchars($scheduleObj->TripStatusLabel($rawStatus)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <?php if (in_array($rawStatus, ['not_departed', 'boarding'], true)): ?>
                                            <button class="icon-btn status cancel-schedule" title="Cancel schedule">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="icon-btn edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="icon-btn delete" title="Delete">
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

    <!-- PREVIEW CARD — unchanged, kept as-is -->
    <div class="schedule-card">
        <div class="schedule-card-header">
            <i class="fas fa-id-badge"></i>
            <p>Schedule Details</p>
            <span id="schedule-label">Select a schedule</span>
        </div>

        <div id="schedule-empty" class="schedule-empty">
            <?= vanny_mascot('pointing', 'small', 'admin-empty-vanny', 'Vanny points to schedule details') ?>
            <p>Select a schedule to view details.</p>
        </div>

        <div id="schedule-preview" style="display:none;">
            <div class="schedule-details">
                <h3 id="preview-route">—</h3>
                <div class="detail-section">
                    <h4>Route Information</h4>
                    <div class="detail-row"><span class="detail-label">Origin</span><span id="preview-origin" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Destination</span><span id="preview-destination" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Via Route</span><span id="preview-via" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Full Route</span><span id="preview-full-route" class="detail-value">—</span></div>
                </div>
                <div class="detail-section">
                    <h4>Schedule Time</h4>
                    <div class="detail-row"><span class="detail-label">Departure</span><span id="preview-departure" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">ETA</span><span id="preview-eta" class="detail-value">—</span></div>
                    <div class="detail-row" id="preview-departed-row" style="display:none;"><span class="detail-label">Actual Departure</span><span id="preview-departed-at" class="detail-value">—</span></div>
                    <div class="detail-row" id="preview-arrived-row" style="display:none;"><span class="detail-label">Actual Arrival</span><span id="preview-arrived-at" class="detail-value">—</span></div>
                    <div class="detail-row" id="preview-completed-row" style="display:none;"><span class="detail-label">Completed At</span><span id="preview-completed-at" class="detail-value">—</span></div>
                </div>
                <div class="detail-section">
                    <h4>Driver and Van</h4>
                    <div class="detail-row"><span class="detail-label">Driver</span><span id="preview-driver" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Van</span><span id="preview-van" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Capacity</span><span id="preview-capacity" class="detail-value">—</span></div>
                </div>
                <div class="detail-section">
                    <h4>Seat Information</h4>
                    <div class="detail-row"><span class="detail-label">Total Seats</span><span id="preview-total-seats" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Available</span><span id="preview-available-seats" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Booked</span><span id="preview-booked-seats" class="detail-value">—</span></div>
                </div>
                <div class="detail-section">
                    <h4>Status Information</h4>
                    <div class="detail-row"><span class="detail-label">Current Status</span><span id="preview-status" class="detail-badge badge" style="justify-content: center;">—</span></div>
                    <div class="detail-row"><span class="detail-label">Description</span><span id="preview-status-desc" class="detail-value">—</span></div>
                </div>
                <div class="detail-section">
                    <h4>System Information</h4>
                    <div class="detail-row"><span class="detail-label">Created</span><span id="preview-created-at" class="detail-value">—</span></div>
                    <div class="detail-row"><span class="detail-label">Updated</span><span id="preview-updated-at" class="detail-value">—</span></div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.schedules-wrapper -->

<!-- FIX 7: Removed the bare <?= csrf_field() ?> that was here.
     We replaced it with the explicit #page-csrf-token input above
     the wrapper, which JS targets reliably. -->

<!-- ADD MODAL — csrf_field() inside is for native POST fallback, correct -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <h6 class="rmodal-title">Add New Schedule</h6>
                    <p class="rmodal-sub">Create a new trip schedule</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../../controllers/Schedules/AddSchedule.php">
                <div class="rmodal-body">
                    <?= csrf_field() ?>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-route"></i> Route</label>
                        <select name="route_id" class="ss" data-placeholder="Select a Route" required>
                            <option value="">Select a Route</option>
                            <?php foreach ($routes as $r): if ($r['is_active']): ?>
                                <?php
                                    $routeStops = array_column($r['stops'] ?? [], 'stop_name');
                                    $routeLabel = $r['origin'] . ' → ' . $r['destination'];
                                    if (!empty($routeStops)) {
                                        $routeLabel .= ' · via ' . implode(', ', $routeStops);
                                    }
                                ?>
                                <option value="<?= $r['route_id_pk'] ?>">
                                    <?= htmlspecialchars($routeLabel) ?>
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-user-tie"></i> Driver</label>
                        <select name="driver_id" class="ss" data-placeholder="Select a Driver" required>
                            <option value="">Select a Driver</option>
                            <?php foreach ($drivers as $d): if ($d['status'] === 'active'): ?>
                                <option value="<?= $d['driver_id_pk'] ?>">
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= $d['license_number'] ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-van-shuttle"></i> Van</label>
                        <select name="van_id" class="ss" data-placeholder="Select a Van" required>
                            <option value="">Select a Van</option>
                            <?php foreach ($vans as $v): if ($v['status'] === 'active'): ?>
                                <option value="<?= $v['van_id_pk'] ?>">
                                    <?= htmlspecialchars($v['plate_number']) ?> - <?= $v['model'] ?> (<?= $v['capacity'] ?> seats)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="rfield-inline">
                        <div class="rfield">
                            <label class="rfield-label"><i class="fas fa-calendar-day"></i> Date</label>
                            <input type="date" name="departure_date" class="rinput" required>
                        </div>
                        <div class="rfield">
                            <label class="rfield-label"><i class="fas fa-clock"></i> Time</label>
                            <input type="time" name="departure_time" class="rinput" required>
                        </div>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-flag-checkered"></i> Estimated Time of Arrival</label>
                        <div class="rfield-inline">
                            <input type="date" name="eta_date" class="rinput" required>
                            <input type="time" name="eta_time" class="rinput" required>
                        </div>
                    </div>

                    <div class="rfield">
                        <input type="hidden" name="trip_status" value="not_departed">
                        <span class="note-badge">New schedules start as Not Departed. The assigned driver updates trip movement.</span>
                    </div>
                </div>
                <div class="rmodal-footer">
                    <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="rbtn rbtn-primary">
                        <i class="fas fa-plus me-1"></i> Add Schedule
                    </button>
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
                    <h6 class="rmodal-title">Edit Schedule</h6>
                    <p class="rmodal-sub">Update schedule details</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <!-- FIX 8: action removed (was javascript:void(0) which is fine but
                 confusing). JS intercepts submit via e.preventDefault(). -->
            <form id="editForm">
                <div class="rmodal-body">
                    <!-- FIX 9: No csrf_field() here — JS sends csrf_token via
                         fetchPost() from the #page-csrf-token input. Adding it
                         here as well caused duplicate tokens and "invalid CSRF"
                         errors when PHP read the wrong one. -->
                    <input type="hidden" name="schedule_id" id="edit-id">

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-route"></i> Route</label>
                        <select name="route_id" id="edit-route" class="ss" data-placeholder="Select a Route" required>
                            <option value="">Select a Route</option>
                            <?php foreach ($routes as $r): if ($r['is_active']): ?>
                                <?php
                                    $routeStops = array_column($r['stops'] ?? [], 'stop_name');
                                    $routeLabel = $r['origin'] . ' → ' . $r['destination'];
                                    if (!empty($routeStops)) {
                                        $routeLabel .= ' · via ' . implode(', ', $routeStops);
                                    }
                                ?>
                                <option value="<?= $r['route_id_pk'] ?>">
                                    <?= htmlspecialchars($routeLabel) ?>
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-user-tie"></i> Driver</label>
                        <select name="driver_id" id="edit-driver" class="ss" data-placeholder="Select a Driver" required>
                            <option value="">Select a Driver</option>
                            <?php foreach ($drivers as $d): if ($d['status'] === 'active'): ?>
                                <option value="<?= $d['driver_id_pk'] ?>">
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= $d['license_number'] ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-van-shuttle"></i> Van</label>
                        <select name="van_id" id="edit-van" class="ss" data-placeholder="Select a Van" required>
                            <option value="">Select a Van</option>
                            <?php foreach ($vans as $v): if ($v['status'] === 'active'): ?>
                                <option value="<?= $v['van_id_pk'] ?>">
                                    <?= htmlspecialchars($v['plate_number']) ?> - <?= $v['model'] ?> (<?= $v['capacity'] ?> seats)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="rfield-inline">
                        <div class="rfield">
                            <label class="rfield-label"><i class="fas fa-calendar-day"></i> Date</label>
                            <input type="date" name="departure_date" id="edit-date" class="rinput" required>
                        </div>
                        <div class="rfield">
                            <label class="rfield-label"><i class="fas fa-clock"></i> Time</label>
                            <input type="time" name="departure_time" id="edit-time" class="rinput" required>
                        </div>
                    </div>

                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-flag-checkered"></i> Estimated Time of Arrival</label>
                        <div class="rfield-inline">
                            <input type="date" name="eta_date" id="edit-eta-date" class="rinput" required>
                            <input type="time" name="eta_time" id="edit-eta-time" class="rinput" required>
                        </div>
                    </div>

                    <input type="hidden" name="trip_status" id="edit-status" value="">
                    <div class="rfield">
                        <label class="rfield-label"><i class="fas fa-info-circle"></i> Trip Status</label>
                        <div class="rinput" id="edit-status-label" aria-live="polite">Driver controlled</div>
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

<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>
