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

$method = $_SERVER['REQUEST_METHOD'];
$action = '';
$input  = [];

if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    $action = $input['action'] ?? '';

    $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['success' => false, 'message' => 'Token inválido.'], 403);
    }
} else {
    $action = $_GET['action'] ?? '';
}

$user = current_user();

if ($action === 'enroll' || $action === 'cancel') {
    if ($user['role'] !== 'member') {
        json_response(['success' => false, 'message' => 'Apenas membros podem inscrever-se.'], 403);
    }

    $schedule_id = (int)($input['schedule_id'] ?? 0);
    if ($schedule_id <= 0) {
        json_response(['success' => false, 'message' => 'Horário inválido.'], 400);
    }

    $stmt = db()->prepare('SELECT cs.*, fc.capacity, fc.id as class_id FROM class_schedule cs JOIN fitness_classes fc ON fc.id=cs.class_id WHERE cs.id=? AND fc.active=1');
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        json_response(['success' => false, 'message' => 'Horário não encontrado.'], 404);
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM class_enrollments WHERE schedule_id=?');
    $stmt->execute([$schedule_id]);
    $enrolled_count = (int)$stmt->fetchColumn();

    if ($action === 'enroll') {
        if ($enrolled_count >= (int)$schedule['capacity']) {
            json_response(['success' => false, 'message' => 'Aula lotada.']);
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM class_enrollments WHERE schedule_id=? AND member_id=?');
        $stmt->execute([$schedule_id, $user['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            json_response(['success' => false, 'message' => 'Já está inscrito neste horário.']);
        }

        db()->prepare('INSERT INTO class_enrollments (class_id, schedule_id, member_id) VALUES (?, ?, ?)')
           ->execute([$schedule['class_id'], $schedule_id, $user['id']]);
        $enrolled_count++;
        json_response(['success' => true, 'message' => 'Inscrição realizada com sucesso!', 'enrolled_count' => $enrolled_count]);

    } else {
        db()->prepare('DELETE FROM class_enrollments WHERE schedule_id=? AND member_id=?')
           ->execute([$schedule_id, $user['id']]);
        $enrolled_count = max(0, $enrolled_count - 1);
        json_response(['success' => true, 'message' => 'Inscrição cancelada.', 'enrolled_count' => $enrolled_count]);
    }
}

if ($action === 'list') {
    $type    = sanitize_string($_GET['type']    ?? '');
    $trainer = (int)($_GET['trainer']           ?? 0);
    $sql     = 'SELECT fc.*, u.name as trainer_name FROM fitness_classes fc LEFT JOIN users u ON u.id=fc.trainer_id WHERE fc.active=1';
    $args    = [];

    if ($type !== '') { $sql .= ' AND fc.type=?'; $args[] = $type; }
    if ($trainer > 0) { $sql .= ' AND fc.trainer_id=?'; $args[] = $trainer; }
    $sql .= ' ORDER BY fc.name';

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    json_response(['success' => true, 'classes' => $stmt->fetchAll()]);
}

json_response(['success' => false, 'message' => 'Ação desconhecida.'], 400);
