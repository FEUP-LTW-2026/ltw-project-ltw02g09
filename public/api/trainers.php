<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/csrf.php';

session_start_safe();
security_headers();
header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $stmt = db()->query('SELECT u.id, u.name, u.profile_photo, tp.bio, tp.specializations, tp.experience_years FROM users u LEFT JOIN trainer_profiles tp ON tp.user_id=u.id WHERE u.role=\'trainer\' AND u.active=1 ORDER BY u.name');
    $trainers = $stmt->fetchAll();

    foreach ($trainers as &$t) {
        $stmt = db()->prepare('SELECT AVG(r.rating) as avg FROM reviews r JOIN fitness_classes fc ON fc.id=r.class_id WHERE fc.trainer_id=?');
        $stmt->execute([$t['id']]);
        $t['avg_rating'] = round((float)$stmt->fetchColumn(), 1);
    }

    json_response(['success' => true, 'trainers' => $trainers]);
}

if ($action === 'availability') {
    $trainer_id = (int)($_GET['trainer_id'] ?? 0);
    if ($trainer_id <= 0) {
        json_response(['success' => false, 'message' => 'Treinador inválido.'], 400);
    }

    $stmt = db()->prepare('SELECT * FROM pt_availability WHERE trainer_id=? AND date>=date(\'now\') ORDER BY date, start_time');
    $stmt->execute([$trainer_id]);
    json_response(['success' => true, 'availability' => $stmt->fetchAll()]);
}

json_response(['success' => false, 'message' => 'Ação desconhecida.'], 400);
