<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/utils.php';

session_start_safe();
security_headers();
header('Content-Type: application/json; charset=UTF-8');

$action    = $_GET['action'] ?? 'list';
$class_id  = (int)($_GET['class_id'] ?? 0);

if ($action === 'list' && $class_id > 0) {
    $stmt = db()->prepare('SELECT r.*, u.name as member_name FROM reviews r JOIN users u ON u.id=r.member_id WHERE r.class_id=? ORDER BY r.created_at DESC');
    $stmt->execute([$class_id]);
    $reviews = $stmt->fetchAll();

    $avg = 0;
    if (!empty($reviews)) {
        $avg = array_sum(array_column($reviews, 'rating')) / count($reviews);
    }

    json_response(['success' => true, 'reviews' => $reviews, 'avg_rating' => round($avg, 1), 'count' => count($reviews)]);
}

json_response(['success' => false, 'message' => 'Ação desconhecida.'], 400);
