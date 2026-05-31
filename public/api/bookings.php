<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/csrf.php';

session_start_safe();
security_headers();
header('Content-Type: application/json; charset=UTF-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Não autenticado.'], 401);
}

$action = $_GET['action'] ?? '';
$user   = current_user();

if ($action === 'slots') {
    $trainer_id = (int)($_GET['trainer_id'] ?? 0);
    $year       = (int)($_GET['year']       ?? date('Y'));
    $month      = (int)($_GET['month']      ?? date('n'));

    if ($trainer_id <= 0) {
        json_response(['success' => false, 'message' => 'Treinador inválido.'], 400);
    }
    
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to   = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

    $stmt = db()->prepare('SELECT * FROM pt_availability WHERE trainer_id=? AND date BETWEEN ? AND ? AND date >= date(\'now\') ORDER BY date, start_time');
    $stmt->execute([$trainer_id, $from, $to]);
    json_response(['success' => true, 'slots' => $stmt->fetchAll()]);
}

if ($action === 'my_bookings') {
    $stmt = db()->prepare('SELECT pb.*, u.name as trainer_name FROM pt_bookings pb JOIN users u ON u.id=pb.trainer_id WHERE pb.member_id=? ORDER BY pb.date DESC');
    $stmt->execute([$user['id']]);
    json_response(['success' => true, 'bookings' => $stmt->fetchAll()]);
}

if ($action === 'confirm' && $user['role'] === 'trainer') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['success' => false, 'message' => 'Token inválido.'], 403);
    }

    $booking_id = (int)($input['booking_id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM pt_bookings WHERE id=? AND trainer_id=? AND status=\'pendente\'');
    $stmt->execute([$booking_id, $user['id']]);
    if ($stmt->fetch()) {
        db()->prepare('UPDATE pt_bookings SET status=\'confirmado\' WHERE id=?')->execute([$booking_id]);
        json_response(['success' => true, 'message' => 'Marcação confirmada.']);
    }
    json_response(['success' => false, 'message' => 'Marcação não encontrada.'], 404);
}

json_response(['success' => false, 'message' => 'Ação desconhecida.'], 400);
