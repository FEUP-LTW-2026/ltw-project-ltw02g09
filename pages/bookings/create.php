<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('member');
$current_user = current_user();
$user_id      = (int)$current_user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/?page=bookings');
}

csrf_verify();

$trainer_id = (int)($_POST['trainer_id'] ?? 0);
$slot_id    = (int)($_POST['slot_id']    ?? 0);
$notes      = sanitize_string($_POST['notes'] ?? '');

if ($trainer_id <= 0 || $slot_id <= 0) {
    redirect('/?page=bookings?error=invalid');
}

$stmt = db()->prepare('SELECT * FROM pt_availability WHERE id = ? AND trainer_id = ? AND booked = 0 AND date >= date(\'now\')');
$stmt->execute([$slot_id, $trainer_id]);
$slot = $stmt->fetch();

if (!$slot) {
    redirect('/?page=bookings/calendar&trainer_id=' . $trainer_id . '&error=unavailable');
}

$stmt = db()->prepare('SELECT COUNT(*) FROM pt_bookings WHERE member_id=? AND trainer_id=? AND date=? AND status IN (\'pendente\',\'confirmado\')');
$stmt->execute([$user_id, $trainer_id, $slot['date']]);
if ((int)$stmt->fetchColumn() > 0) {
    redirect('/?page=bookings?error=duplicate');
}

db()->beginTransaction();
try {
    db()->prepare('UPDATE pt_availability SET booked=1 WHERE id=?')->execute([$slot_id]);
    db()->prepare('INSERT INTO pt_bookings (trainer_id, member_id, availability_id, date, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, \'pendente\', ?)')
       ->execute([$trainer_id, $user_id, $slot_id, $slot['date'], $slot['start_time'], $slot['end_time'], $notes]);
    db()->commit();
} catch (Exception $e) {
    db()->rollBack();
    redirect('/?page=bookings?error=server');
}

redirect('/?page=bookings&success=booked');
