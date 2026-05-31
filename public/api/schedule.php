<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/utils.php';

session_start_safe();
security_headers();
header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? 'weekly';

if ($action === 'weekly') {
    $day = isset($_GET['day']) && $_GET['day'] !== '' ? (int)$_GET['day'] : -1;

    $sql  = 'SELECT cs.*, fc.name as class_name, fc.type, fc.capacity, fc.location, u.name as trainer_name, (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.schedule_id=cs.id) as enrolled_count FROM class_schedule cs JOIN fitness_classes fc ON fc.id=cs.class_id LEFT JOIN users u ON u.id=fc.trainer_id WHERE fc.active=1';
    $args = [];

    if ($day >= 0 && $day <= 6) {
        $sql  .= ' AND cs.day_of_week=?';
        $args[] = $day;
    }

    $sql .= ' ORDER BY cs.day_of_week, cs.start_time';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    json_response(['success' => true, 'schedule' => $stmt->fetchAll()]);
}

json_response(['success' => false, 'message' => 'Ação desconhecida.'], 400);
