<?php
require_once '../../autoload.php';
if (!isset($_SESSION['is_login'])) { header('Location: ../auth/login.php'); exit; }

ob_start();
$title       = 'My Bookings';
$active_page = 'bookings';
$page_css    = '../../assets/css/user-bookings.css';
$page_js     = '../../assets/js/user-bookings.js';

// Fetch data

$status = $_GET['status'] ?? 'all';
$bk     = new Bookings($conn);
$bk->id = decrypt($_SESSION['id']);
$bk->status = $status;
$stats  = $bk->GetUserStats();
$bookings = $bk->GetBookingsByUserFiltered();
?>

<!-- PAGE BODY -->
<div class="u-body my-bookings-page">
    <div class="u-page-head">
        <!-- <div>
            <h1>My Bookings</h1>
            <p>Track your reservations, seat numbers, and trip status.</p>
        </div> -->
        <a href="schedule.php" class="u-head-action">
            <i class="fa-solid fa-plus"></i>
            New booking
        </a>
    </div>

    <!-- Stats Strip -->
    <div class="u-stats">
        <div class="u-stat primary">
            <div class="u-stat-lbl">Total</div>
            <div class="u-stat-val"><?= $stats['total'] ?? 0 ?></div>
            <div class="u-stat-sub">All bookings</div>
        </div>
        <div class="u-stat">
            <div class="u-stat-lbl">Pending</div>
            <div class="u-stat-val"><?= $stats['pending'] ?? 0 ?></div>
            <div class="u-stat-sub">For admin review</div>
        </div>
        <div class="u-stat">
            <div class="u-stat-lbl">Completed</div>
            <div class="u-stat-val"><?= $stats['completed'] ?? 0 ?></div>
            <div class="u-stat-sub">Past trips</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="u-filtabs">
        <a href="?status=all" class="u-ftab <?= $status === 'all' ? 'active' : '' ?>">All</a>
        <a href="?status=pending" class="u-ftab <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?status=upcoming" class="u-ftab <?= $status === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
        <a href="?status=completed" class="u-ftab <?= $status === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="?status=cancelled" class="u-ftab <?= $status === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <!-- Booking List -->
    <div class="u-bk-list">
        <?php if ($bookings && count($bookings) > 0): ?>
            <?php foreach ($bookings as $booking): ?>
            <a href="booking-detail.php?id=<?= urlencode(encrypt((string) $booking['book_id_pk'])) ?>" class="u-bk-item">
                <div class="u-bk-main">
                    <div class="u-bk-topline">
                        <div class="u-bk-ref"><?= htmlspecialchars($booking['reference_code']) ?></div>
                        <span class="u-badge <?= htmlspecialchars($booking['status']) ?>">
                            <?= ucfirst($booking['status']) ?>
                        </span>
                    </div>
                    <div class="u-bk-route">
                        <?= htmlspecialchars($booking['origin'] ?? 'Origin') ?>
                        <i class="fa-solid fa-arrow-right route-arrow-icon"></i>
                        <?= htmlspecialchars($booking['destination'] ?? 'Destination') ?>
                    </div>
                    <div class="u-bk-meta">
                        <span><i class="fa-regular fa-calendar"></i><?= date('M j, Y', strtotime($booking['departure_date'])) ?></span>
                        <span><i class="fa-regular fa-clock"></i><?= date('g:i A', strtotime($booking['departure_time'])) ?></span>
                        <span><i class="fa-solid fa-chair"></i><?= (int) $booking['seats_count'] ?> seat<?= (int) $booking['seats_count'] === 1 ? '' : 's' ?></span>
                    </div>
                    <?php if (!empty($booking['seat_numbers'])): ?>
                        <div class="u-seat-chip-row" aria-label="Selected seats">
                            <?php foreach (explode(',', $booking['seat_numbers']) as $seatNumber): ?>
                                <span class="u-seat-chip"><?= htmlspecialchars(trim($seatNumber)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="u-bk-go">
                    <i class="fa-solid fa-chevron-right"></i>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="u-empty-state">
                <?= vanny_empty_state('welcome', 'No bookings found', 'Try changing your filter or start a new trip.', 'Book a Trip', 'schedule.php', 'u-vanny-empty') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/user_layout.php';
?>
