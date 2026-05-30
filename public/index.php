<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/csrf.php';

session_start_safe();
security_headers();

$page = $_GET['page'] ?? 'home';
$page = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $page);

$public_pages = ['home', 'login', 'register', 'classes', 'trainers', 'equipment', '403', '404'];

$routes = [
    'home'              => __DIR__ . '/../pages/home.php',
    'login'             => __DIR__ . '/../pages/auth/login.php',
    'register'          => __DIR__ . '/../pages/auth/register.php',
    'logout'            => __DIR__ . '/../pages/auth/logout.php',
    'profile'           => __DIR__ . '/../pages/profile/view.php',
    'profile/edit'      => __DIR__ . '/../pages/profile/edit.php',
    'classes'           => __DIR__ . '/../pages/classes/index.php',
    'classes/view'      => __DIR__ . '/../pages/classes/view.php',
    'classes/manage'    => __DIR__ . '/../pages/classes/manage.php',
    'trainers'          => __DIR__ . '/../pages/trainers/index.php',
    'trainers/view'     => __DIR__ . '/../pages/trainers/view.php',
    'equipment'         => __DIR__ . '/../pages/equipment/index.php',
    'equipment/manage'  => __DIR__ . '/../pages/equipment/manage.php',
    'bookings'          => __DIR__ . '/../pages/bookings/index.php',
    'bookings/calendar' => __DIR__ . '/../pages/bookings/calendar.php',
    'bookings/create'   => __DIR__ . '/../pages/bookings/create.php',
    'admin/dashboard'   => __DIR__ . '/../pages/admin/dashboard.php',
    'admin/users'       => __DIR__ . '/../pages/admin/users.php',
    'admin/classes'     => __DIR__ . '/../pages/admin/classes.php',
    'admin/equipment'   => __DIR__ . '/../pages/admin/equipment.php',
    'admin/analytics'   => __DIR__ . '/../pages/admin/analytics.php',
    '403'               => __DIR__ . '/../pages/errors/403.php',
    '404'               => __DIR__ . '/../pages/errors/404.php',
];

if (!isset($routes[$page])) {
    http_response_code(404);
    include $routes['404'];
    exit;
}

include $routes[$page];
