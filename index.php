<?php
require_once __DIR__ . '/autoload.php';

$landingSchedules = [];
$landingSeatPreview = [];

try {
    $scheduleObj = new Schedules($conn);
    $landingSchedules = array_slice($scheduleObj->GetAvailableSchedules(), 0, 3);

    if (!empty($landingSchedules[0]['schedule_id_pk'])) {
        $availability = $scheduleObj->GetSeatAvailability((int) $landingSchedules[0]['schedule_id_pk']);
        $landingSeatPreview = array_slice($availability['seats'] ?? [], 0, 10);
    }
} catch (Throwable $e) {
    $landingSchedules = [];
    $landingSeatPreview = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'GoraVan' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="../../images/logo_white.png" type="image/png">

</head>

<body>

    <!-- NAV BAR -->
    <nav class="navbar">
        <div class="logo">
            <img src="/images/logo.png" alt="goravan logo">
            <span class="navbar-name">Gora<span>Van</span></span>
        </div>
        <div class="navbar-nav">
            <a href="#home" class="nav-link">Home</a>
            <a href="#features" class="nav-link">Features</a>
            <a href="#how" class="nav-link">How It Works</a>
            <!-- <a href="#routes" class="nav-link">Routes</a> -->
            <div class="btn-group">
                <a href="views/auth/login.php" class="btn login-btn">Log In</a>
                <a href="views/auth/register.php" class="btn register-btn">Get Started</a>
            </div>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </nav>
    <div class="mobile-menu" id="mobileMenu">
        <a href="#home" class="nav-link">Home</a>
        <a href="#features" class="nav-link">Features</a>
        <a href="#how" class="nav-link">How It Works</a>
        <!-- <a href="#routes" class="nav-link">Routes</a> -->
        <div class="mobile-divider"></div>
        <div class="btn-group">
            <a href="views/auth/login.php" class="btn login-btn">Log In</a>
            <a href="views/auth/register.php" class="btn register-btn">Get Started</a>
        </div>
    </div>

    <!-- HOME -->
    <section class="hero" id="home">
        <div class="hero-main">
            <div class="hero-copy">
                <div class="hero-topline">
                    <div class="hero-badge">Southern Leyte Online Van Booking</div>

                    <img class="hero-vanny" src="/images/vanny-lets-go.png" alt="Vanny ready to go" loading="lazy"
                        decoding="async">
                </div>

                <h1>Book Your <span>Van Ride</span><br>Online, Anytime</h1>

                <p>
                    GoraVan makes commuting between Southern Leyte destinations easier. Reserve your seat, view
                    schedules, and confirm your booking - all without going to the terminal.
                </p>

                <div class="hero-actions">
                    <a href="views/auth/register.php" class="cta-btn">Book a Ride</a>
                    <a href="views/auth/login.php" class="cta-outline">Log In</a>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-card">
                <div class="hero-card-header">
                    <span class="card-label">Available Routes</span>
                </div>

                <div class="route-list">
                    <?php if (!empty($landingSchedules)): ?>
                        <?php foreach ($landingSchedules as $schedule): ?>
                            <div class="route-item">
                                <span class="route-name">
                                    <?= htmlspecialchars($schedule['origin'] ?? 'Origin') ?>
                                    <i class="fas fa-arrow-right"></i>
                                    <?= htmlspecialchars($schedule['destination'] ?? 'Destination') ?>
                                </span>
                                <span class="route-time">
                                    <?= date('M j, g:i A', strtotime(($schedule['departure_date'] ?? '') . ' ' . ($schedule['departure_time'] ?? ''))) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="route-item">
                            <span class="route-name">No boarding schedules yet</span>
                            <span class="route-time">Check back soon</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hero-card">
                <div class="hero-card-header">
                    <span class="card-label">
                        Seat Availability
                        <?php if (!empty($landingSchedules[0])): ?>
                            -
                            <?= htmlspecialchars($landingSchedules[0]['origin'] ?? 'Origin') ?>
                            <i class="fas fa-arrow-right"></i>
                            <?= htmlspecialchars($landingSchedules[0]['destination'] ?? 'Destination') ?>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="seat-grid">
                    <?php if (!empty($landingSeatPreview)): ?>
                        <?php foreach ($landingSeatPreview as $seat): ?>
                            <div class="seat <?= !empty($seat['is_booked']) ? 'taken' : 'available' ?>">
                                <?= htmlspecialchars($seat['seat_number'] ?? '') ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="seat available">1A</div>
                        <div class="seat available">1B</div>
                        <div class="seat taken">1C</div>
                        <div class="seat selected">2A</div>
                        <div class="seat available">2B</div>
                        <div class="seat taken">2C</div>
                        <div class="seat available">3A</div>
                        <div class="seat available">3B</div>
                        <div class="seat taken">3C</div>
                        <div class="seat available">4A</div>
                    <?php endif; ?>
                </div>

                <div class="seat-legend">
                    <span><span class="legend-box available-box"></span> Available</span>
                    <span><span class="legend-box taken-box"></span> Taken</span>
                </div>
            </div>
        </div>
    </section>
    <!-- FEATURES SECTION -->
    <section class="features" id="features">
        <div class="features-header">
            <div class="badge-dark">What We Offer</div>
            <h2>A smarter way <span>to travel</span></h2>
            <p>
                GoraVan replaces the hassle of lining up at the terminal with a simple, organized online booking
                experience.
            </p>
        </div>

        <div class="features-grid">

            <!-- Seat Selection -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-couch"></i>
                </div>
                <h3>Seat Selection</h3>
                <p>
                    Choose your exact seat from a visual van layout before you confirm your booking. No surprises on the
                    day of your trip.
                </p>
            </div>

            <!-- Route-Based Booking -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-route"></i>
                </div>
                <h3>Route-Based Booking</h3>
                <p>
                    Browse trips by route - origin, destination, and via points. Find the right schedule that fits your
                    travel plan.
                </p>
            </div>

            <!-- Online Payment -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <h3>Online Payment</h3>
                <p>
                    Pay through GCash, PayMaya, or card inside the booking flow and keep your receipt in My Payments.
                </p>
            </div>

            <!-- Trip Status Tracking -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-location-crosshairs"></i>
                </div>
                <h3>Trip Status Tracking</h3>
                <p>
                    Know where your van is in its journey - Scheduled, Boarding, Departed, or Arrived - updated in real
                    time by our operators.
                </p>
            </div>

            <!-- Booking Confirmation -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3>Booking Confirmation</h3>
                <p>
                    Receive a unique reference code upon approval. Present it at the terminal for fast, organized
                    boarding.
                </p>
            </div>

            <!-- Priority Passenger Support -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h3>Priority Passenger Support</h3>
                <p>
                    Senior citizens, PWD, students, and pregnant passengers may upload verification documents to avail
                    of
                    applicable discounts.
                </p>
            </div>

            <!-- Payment History -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <h3>Payment History</h3>
                <p>
                    Review paid bookings, payment references, and refund updates in one organized My Payments page.
                </p>
            </div>

            <!-- Passenger Notifications -->
            <div class="feature-card">
                <div class="icon-wrap">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <h3>Passenger Notifications</h3>
                <p>
                    Get timely updates for booking approvals, trip reminders, verification results, and payment changes.
                </p>
            </div>

        </div>
    </section>

    <!-- HOW IT WORKS SECTION -->
    <section class="how" id="how">
        <div class="how-header">
            <div class="badge-dark">How It Works</div>
            <h2>Book in <span>Five Simple Steps</span></h2>
            <p>
                The public booking flow follows the same guided process passengers see inside GoraVan.
            </p>
        </div>

        <div class="how-grid">

            <div class="how-card">
                <div class="how-step">1</div>
                <h3>Choose Route</h3>
                <p>Pick an available schedule and review the route, stops, van, fare, and departure time.</p>
            </div>

            <div class="how-card">
                <div class="how-step">2</div>
                <h3>Select Seats</h3>
                <p>Use the live seat map to choose available seats and see the fare update instantly.</p>
            </div>

            <div class="how-card">
                <div class="how-step">3</div>
                <h3>Add Passenger</h3>
                <p>Confirm passenger details and choose the right passenger type for each selected seat.</p>
            </div>

            <div class="how-card">
                <div class="how-step">4</div>
                <h3>Pay Online</h3>
                <p>Select GCash, PayMaya, or card, then review the full order summary before submitting.</p>
            </div>

            <div class="how-card">
                <div class="how-step">5</div>
                <h3>Confirm Booking</h3>
                <p>Get your booking reference and track the trip status from My Bookings.</p>
            </div>

        </div>
    </section>

    <!-- ROUTES SECTION -->
    <!-- <section class="routes" id="routes">
        <div class="routes-header">
            <div class="badge-light">Service Area</div>
            <h2>Covering <span>Southern Leyte</span></h2>
            <p>
                We operate scheduled van trips across major destinations in Southern Leyte province.
            </p>
        </div>

        <div class="routes-grid">

            <div class="route-card">Sogod -> Maasin</div>
            <div class="route-card">Maasin -> Sogod</div>
            <div class="route-card">Sogod -> Liloan</div>
            <div class="route-card">Maasin -> Bato</div>
            <div class="route-card">Bato -> Sogod</div>
            <div class="route-card">Sogod -> Malitbog</div>
            <div class="route-card">Maasin -> Pintuyan</div>
            <div class="route-card">Sogod -> San Juan</div>

        </div>
    </section> -->

    <!-- CTA SECTION -->
    <!-- <section class="cta-section">
        <div class="cta-card">
            <h2>Ready to book your next trip?</h2>
            <p>
                Join hundreds of Southern Leyte commuters who use GoraVan to travel smarter.
            </p>

            <div class="cta-buttons">
                <a href="views/auth/register.php" class="cta-btn">Create an Account</a>
                <a href="views/auth/login.php" class="cta-outline">Log In</a>
            </div>
        </div>
    </section> -->
    <!-- FOOTER -->
    <footer class="footer">
        <!-- <div class="footer-container"> -->

        <!-- Brand -->
        <!-- <div class="footer-brand">
                <h2>GoraVan</h2>
                <p>
                    A web-based van booking system for Southern Leyte commuters.
                    Organized, reliable, and easy to use.
                </p>
            </div> -->

        <!-- <div class="footer-box">
                <h3>System</h3>
                <p>Home</p>
                <p>Login</p>
                <p>Register</p>
                <p>Dashboard</p>
            </div> -->

        <!-- Routes -->
        <!-- <div class="footer-box">
                <h3>Routes</h3>
                <p>Sogod - Maasin</p>
                <p>Maasin - Sogod</p>
                <p>Sogod - Liloan</p>
                <p>All Routes</p>
            </div> -->

        <!-- </div> -->

        <div class="footer-bottom">
            <p>2026 GoraVan. All rights reserved. Southern Leyte, Philippines.</p>
        </div>
    </footer>
</body>

<script>
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const body = document.body;

    function toggleMenu() {
        hamburger.classList.toggle('open');
        mobileMenu.classList.toggle('open');
        body.classList.toggle('menu-open');
    }

    hamburger.addEventListener('click', toggleMenu);
    document.querySelectorAll('.mobile-menu a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('open');
            mobileMenu.classList.remove('open');
            body.classList.remove('menu-open');
        });
    });
</script>