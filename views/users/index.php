<?php
require_once '../../autoload.php';
if (!isset($_SESSION['is_login'])) { header('Location: ../auth/login.php'); exit; }

ob_start();
$title       = 'Home';
$active_page = 'home';
$page_css    = '../../assets/css/user-home.css';
$page_js     = '../../assets/js/user-home.js';

// Fetch data
$um     = new Users($conn);
$um->id = decrypt($_SESSION['id']);
$user   = $um->GetUserById();

$bk             = new Bookings($conn);
$bk->id = decrypt($_SESSION['id']);
$upcomingTrip   = $bk->GetUpcomingTripByUser();   // nearest approved future booking
$stats          = $bk->GetUserStats();             // total, upcoming, completed
$routes         = (new Routes($conn))->GetActiveRoutes();  // for search dropdowns
$locations      = LOCATIONS; // Use LOCATIONS constant from autoload.php
?>

<div class="user-home-page">
<!-- COMPACT DASHBOARD HEADER -->
<section class="u-dashboard-header">
    <div class="u-header-content">
        <div class="u-header-text">
            <h1 class="u-header-title">Welcome back, <?= htmlspecialchars(ucfirst($user['firstname'] ?? '')) ?></h1>
            <p class="u-header-subtitle">Find trips, review bookings, and keep your rides organized.</p>
        </div>
        <div class="u-header-date">
            <span class="u-date-label">Today</span>
            <span class="u-date-value"><?= date('M j, Y') ?></span>
        </div>
    </div>
</section>

<!-- SEARCH TOOLBAR -->
<section class="u-search-toolbar">
    <div class="u-toolbar-content">
        <h2 class="u-toolbar-title">Find a Trip</h2>
        <form class="u-toolbar-form" action="schedule.php" method="GET">
            <div class="u-tf">
                <label for="from">From</label>
                <select id="from" name="from" class="ss" data-placeholder="Select origin" required>
                    <option value="">Select origin</option>
                    <?php foreach ($locations as $name => $coords): ?>
                    <option value="<?= $name ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="u-t-sep"><i class = 'fa-solid fa-arrow-right'></i></div>
            <div class="u-tf">
                <label for="to">To</label>
                <select id="to" name="to" class="ss" data-placeholder="Select destination" required>
                    <option value="">Select destination</option>
                    <?php foreach ($locations as $name => $coords): ?>
                    <option value="<?= $name ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="u-tf">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
            </div>
            <button type="submit" class="u-tbtn">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
        </form>
    </div>
</section>

<!-- PAGE BODY -->
<div class="u-body">
    <!-- Stats Overview -->
    <div class="u-sec">
        <div class="u-sec-head">
            <h2 class="u-sec-title">Your Stats</h2>
        </div>
        <div class="u-stats u-stats-dashboard">
            <div class="u-stat primary">
                <div class="u-stat-icon">
                    <i class="fa-solid fa-route"></i>
                </div>
                <div class="u-stat-content">
                    <div class="u-stat-lbl">Total Bookings</div>
                    <div class="u-stat-val"><?= $stats['total'] ?? 0 ?></div>
                    <div class="u-stat-sub">All booking references</div>
                </div>
            </div>
            <div class="u-stat">
                <div class="u-stat-icon">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="u-stat-content">
                    <div class="u-stat-lbl">Upcoming Trips</div>
                    <div class="u-stat-val"><?= $stats['upcoming'] ?? 0 ?></div>
                    <div class="u-stat-sub">Approved future trips</div>
                </div>
            </div>
            <div class="u-stat">
                <div class="u-stat-icon">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="u-stat-content">
                    <div class="u-stat-lbl">Waiting For Approval</div>
                    <div class="u-stat-val"><?= $stats['pending'] ?? 0 ?></div>
                    <div class="u-stat-sub">For admin review</div>
                </div>
            </div>
            <div class="u-stat">
                <div class="u-stat-icon">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <div class="u-stat-content">
                    <div class="u-stat-lbl">Completed Trips</div>
                    <div class="u-stat-val"><?= $stats['completed'] ?? 0 ?></div>
                    <div class="u-stat-sub">Approved past trips</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Next Trip -->
    <div class="u-sec">
        <div class="u-sec-head">
            <h2 class="u-sec-title">Next Trip</h2>
        </div>
        <?php if ($upcomingTrip): ?>
            <a href="booking-detail.php?id=<?= urlencode(encrypt((string) $upcomingTrip['book_id_pk'])) ?>" class="u-next-trip">
                <div class="u-next-date">
                    <span><?= date('M', strtotime($upcomingTrip['departure_date'])) ?></span>
                    <strong><?= date('j', strtotime($upcomingTrip['departure_date'])) ?></strong>
                </div>
                <div class="u-next-main">
                    <div class="u-next-route"><?= htmlspecialchars($upcomingTrip['route_display']) ?></div>
                    <div class="u-next-meta">
                        <?= date('g:i A', strtotime($upcomingTrip['departure_time'])) ?>
                        &middot; <?= (int) $upcomingTrip['seats_count'] ?> seat<?= (int) $upcomingTrip['seats_count'] === 1 ? '' : 's' ?>
                        <?php if (!empty($upcomingTrip['seat_numbers'])): ?>
                            &middot; <?= htmlspecialchars($upcomingTrip['seat_numbers']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <div class="u-next-trip empty">
                <div class="u-next-date muted"><i class="fa-regular fa-calendar"></i></div>
                <div class="u-next-main">
                    <div class="u-next-route">No upcoming trip yet</div>
                    <div class="u-next-meta">Search a route and book your next ride.</div>
                </div>
                <a href="schedule.php" class="u-next-cta">Book now</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="u-sec">
        <div class="u-sec-head">
            <h2 class="u-sec-title">Quick Actions</h2>
        </div>
        <div class="u-qa-grid u-qa-grid-refined">
            <a href="schedule.php" class="u-qa-card">
                <div class="u-qa-icon">
                    <i class="fa-solid fa-magnifying-glass-location"></i>
                </div>
                <span class="u-qa-label">Find Schedule</span>
            </a>
            <a href="my-bookings.php" class="u-qa-card">
                <div class="u-qa-icon">
                    <i class="fa-solid fa-ticket"></i>
                </div>
                <span class="u-qa-label">My Bookings</span>
            </a>
            <a href="my-payments.php" class="u-qa-card">
                <div class="u-qa-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <span class="u-qa-label">Payments</span>
            </a>
            <a href="profile.php" class="u-qa-card">
                <div class="u-qa-icon">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <span class="u-qa-label">Profile</span>
            </a>
        </div>
    </div>
</div>
</div>

<?php
$content = ob_get_clean();
include '../layout/user_layout.php';
?>
