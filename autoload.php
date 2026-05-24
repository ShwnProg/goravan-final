<?php
/**
 * Shared bootstrap for sessions, database, helpers, and role guards.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

define('BASE_URL', '/GROAVAN');
define('LOCATIONS', [
    // Southern Leyte city / municipalities
    'Maasin City' => [10.1335, 124.8460],
    'Bontoc' => [10.3559, 124.9693],
    'Sogod' => [10.3856, 124.9806],
    'Malitbog' => [10.1581, 125.0012],
    'Padre Burgos' => [10.0296, 125.0170],
    'Limasawa' => [9.9303, 125.0746],
    'Liloan' => [10.1581, 125.1253],
    'Macrohon' => [10.0766, 124.9401],
    'San Juan' => [10.2641, 125.1735],
    'Silago' => [10.5284, 125.1627],
    'Hinunangan' => [10.3946, 125.1985],
    'Hinundayan' => [10.3511, 125.2510],
    'St. Bernard' => [10.2801, 125.1383],
    'San Ricardo' => [9.9130, 125.2763],
    'Tomas Oppus' => [10.2548, 124.9856],
    'San Francisco' => [10.0575, 125.1576],
    'Libagon' => [10.2968, 125.0505],
    'Anahawan' => [10.2740, 125.2578],
    'Pintuyan' => [9.9446, 125.2492],

    // Not Southern Leyte, but keep this only if your route uses it as a stop/via point
    'Bato' => [10.3279, 124.7919],
]);
define('discounts', [
    'student' => 10,
    'senior' => 15,
    'pwd' => 20
]);
// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/helpers/csrf_helper.php";
require_once __DIR__ . '/helpers/encryption.php';
require_once __DIR__ . '/helpers/vanny_helper.php';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . "/classes/",
    ];
    foreach ($paths as $path) {
        $file = $path . $class . ".php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    die("Class $class not found.");
});

$database = new Database();

try {
    $conn = $database->GetConnection();
} catch (DatabaseConnectionException $e) {
    http_response_code(503);
    define('GORAVAN_DATABASE_STATUS_MODE', true);
    $databaseStatusError = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();
    require __DIR__ . '/views/system/database-status.php';
    exit;
}

function require_role(string $role): void
{
    if (!empty($_SESSION['is_login']) && !empty($_SESSION['id']) && ($_SESSION['role'] ?? '') === $role) {
        return;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, '/controllers/')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => ucfirst($role) . ' access only.'
        ]);
        exit;
    }

    $_SESSION['error'] = ucfirst($role) . ' access only.';
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

if (str_contains($scriptPath, '/views/admin/')) {
    require_role('admin');
}

if (str_contains($scriptPath, '/views/users/')) {
    require_role('user');
}

if (str_contains($scriptPath, '/views/driver/')) {
    require_role('driver');
}

$adminControllerPaths = [
    '/controllers/Vans/',
    '/controllers/Drivers/',
    '/controllers/Schedules/',
    '/controllers/routes/',
    '/controllers/Bookings/',
    '/controllers/Dashboard/',
    '/controllers/PaymentsController.php',
    '/controllers/UsersController.php',
    '/controllers/SettingsController.php',
];

foreach ($adminControllerPaths as $path) {
    if (str_contains($scriptPath, $path)) {
        require_role('admin');
    }
}

if (str_contains($scriptPath, '/controllers/users/')) {
    $publicUserControllers = [
        '/controllers/users/RegisterController.php',
        '/controllers/users/LogoutController.php',
    ];

    $isPublicUserController = false;
    foreach ($publicUserControllers as $publicPath) {
        if (str_contains($scriptPath, $publicPath)) {
            $isPublicUserController = true;
            break;
        }
    }

    if (!$isPublicUserController) {
        require_role('user');
    }
}

if (str_contains($scriptPath, '/controllers/driver/')) {
    require_role('driver');
}
