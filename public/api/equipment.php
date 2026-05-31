<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/utils.php';

session_start_safe();
security_headers();
header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $category = sanitize_string($_GET['category'] ?? '');
    $sql      = 'SELECT * FROM equipment WHERE 1=1';
    $args     = [];

    if ($category !== '') {
        $sql  .= ' AND category=?';
        $args[] = $category;
    }

    $sql .= ' ORDER BY category, name';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    json_response(['success' => true, 'equipment' => $stmt->fetchAll()]);
}

json_response(['success' => false, 'message' => 'Ação desconhecida.'], 400);
