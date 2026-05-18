<?php
require_once '../../autoload.php';

if (!csrf_check()) {
    $_SESSION['error'] = 'Invalid CSRF token.';
    header('Location: ../../views/admin/routes.php');
    exit;
}

$origin = trim($_POST['origin'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$status = (int) ($_POST['status'] ?? 1);
$fare = (double) ($_POST['fare'] ?? 0);


$stops = array_values(array_filter(
    array_map('trim', $_POST['stops'] ?? []),
    fn($s) => $s !== ''
));

if (!$origin || !$destination) {
    $_SESSION['error'] = 'Origin and destination are required.';
    header('Location: ../../views/admin/routes.php');
    exit;
}
if ($fare <= 0) {
    $_SESSION['error'] = 'Fare must be positive integer.';
    header('Location: ../../views/admin/routes.php');
    exit;
}

if ($origin === $destination) {
    $_SESSION['error'] = 'Origin and destination cannot be the same.';
    header('Location: ../../views/admin/routes.php');
    exit;
}

$route = new Routes($conn);
$route->origin = $origin;
$route->destination = $destination;
$route->status = $status;
$route->fare = $fare;
$route->stops = $stops;

if ($route->IsRouteExist()) {
    $_SESSION['error'] = 'That route already exists.';
    header('Location: ../../views/admin/routes.php');
    exit;
}

$result = $route->AddRoute();

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? 'Route added successfully.'
    : ($result['message'] ?? 'Unable to add route. Please check the details and try again.');

header('Location: ../../views/admin/routes.php');
exit;
