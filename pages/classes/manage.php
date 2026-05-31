<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/validation.php';

require_role('trainer');
$current_user = current_user();
$trainer_id   = (int)$current_user['id'];

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'add_schedule') {
        $class_id   = (int)($_POST['class_id'] ?? 0);
        $day        = (int)($_POST['day_of_week'] ?? 0);
        $time       = sanitize_string($_POST['start_time'] ?? '');

        $stmt = db()->prepare('SELECT id FROM fitness_classes WHERE id = ? AND trainer_id = ?');
        $stmt->execute([$class_id, $trainer_id]);
        if ($stmt->fetch() && validate_time($time) && $day >= 0 && $day <= 6) {
            db()->prepare('INSERT INTO class_schedule (class_id, day_of_week, start_time, recurring) VALUES (?, ?, ?, 1)')
               ->execute([$class_id, $day, $time]);
            $message = 'Horário adicionado com sucesso.';
        } else {
            $error = 'Dados inválidos.';
        }
    } elseif ($action === 'remove_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $stmt = db()->prepare('SELECT cs.id FROM class_schedule cs JOIN fitness_classes fc ON fc.id = cs.class_id WHERE cs.id = ? AND fc.trainer_id = ?');
        $stmt->execute([$schedule_id, $trainer_id]);
        if ($stmt->fetch()) {
            db()->prepare('DELETE FROM class_schedule WHERE id = ?')->execute([$schedule_id]);
            $message = 'Horário removido.';
        }
    } elseif ($action === 'add_availability') {
        $date  = sanitize_string($_POST['date']       ?? '');
        $start = sanitize_string($_POST['start_time'] ?? '');
        $end   = sanitize_string($_POST['end_time']   ?? '');

        if (validate_date($date) && validate_time($start) && validate_time($end) && $start < $end && $date >= date('Y-m-d')) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM pt_availability WHERE trainer_id=? AND date=? AND ((start_time<=? AND end_time>?) OR (start_time<? AND end_time>=?))');
            $stmt->execute([$trainer_id, $date, $start, $start, $end, $end]);
            if ((int)$stmt->fetchColumn() === 0) {
                db()->prepare('INSERT INTO pt_availability (trainer_id, date, start_time, end_time) VALUES (?, ?, ?, ?)')
                   ->execute([$trainer_id, $date, $start, $end]);
                $message = 'Disponibilidade adicionada.';
            } else {
                $error = 'Já existe uma disponibilidade nesse horário.';
            }
        } else {
            $error = 'Dados inválidos. Verifique a data e horários.';
        }
    } elseif ($action === 'remove_availability') {
        $avail_id = (int)($_POST['availability_id'] ?? 0);
        $stmt = db()->prepare('SELECT booked FROM pt_availability WHERE id = ? AND trainer_id = ?');
        $stmt->execute([$avail_id, $trainer_id]);
        $avail = $stmt->fetch();
        if ($avail && !$avail['booked']) {
            db()->prepare('DELETE FROM pt_availability WHERE id = ?')->execute([$avail_id]);
            $message = 'Disponibilidade removida.';
        } else {
            $error = 'Não é possível remover este horário (já tem marcação).';
        }
    } elseif ($action === 'confirm_booking') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $stmt = db()->prepare('SELECT id FROM pt_bookings WHERE id=? AND trainer_id=? AND status=\'pendente\'');
        $stmt->execute([$booking_id, $trainer_id]);
        if ($stmt->fetch()) {
            db()->prepare('UPDATE pt_bookings SET status=\'confirmado\' WHERE id=?')->execute([$booking_id]);
            $message = 'Marcação confirmada.';
        } else {
            $error = 'Marcação não encontrada.';
        }
    }
}

$stmt = db()->prepare('SELECT fc.*, (SELECT COUNT(*) FROM class_schedule WHERE class_id = fc.id) as schedule_count FROM fitness_classes fc WHERE fc.trainer_id = ? AND fc.active = 1 ORDER BY fc.name');
$stmt->execute([$trainer_id]);
$classes = $stmt->fetchAll();

$class_schedules = [];
foreach ($classes as $cls) {
    $stmt = db()->prepare('SELECT cs.*, (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.schedule_id = cs.id) as enrolled FROM class_schedule cs WHERE cs.class_id = ? ORDER BY cs.day_of_week, cs.start_time');
    $stmt->execute([$cls['id']]);
    $class_schedules[$cls['id']] = $stmt->fetchAll();
}

$schedule_members = [];
$schedule_ids = [];
foreach ($class_schedules as $schedules) {
    foreach ($schedules as $sch) {
        $schedule_ids[] = (int)$sch['id'];
    }
}
if (!empty($schedule_ids)) {
    $placeholders = implode(',', array_fill(0, count($schedule_ids), '?'));
    $stmt = db()->prepare("SELECT ce.schedule_id, u.id, u.name, u.profile_photo FROM class_enrollments ce JOIN users u ON u.id = ce.member_id WHERE ce.schedule_id IN ($placeholders) ORDER BY u.name");
    $stmt->execute($schedule_ids);
    foreach ($stmt->fetchAll() as $row) {
        $schedule_members[(int)$row['schedule_id']][] = $row;
    }
}

$stmt = db()->prepare('SELECT pa.*, (SELECT u.name FROM users u WHERE u.id = pb.member_id LIMIT 1) as member_name FROM pt_availability pa LEFT JOIN pt_bookings pb ON pb.availability_id = pa.id WHERE pa.trainer_id = ? AND pa.date >= date(\'now\') ORDER BY pa.date, pa.start_time');
$stmt->execute([$trainer_id]);
$availability = $stmt->fetchAll();

$stmt = db()->prepare('SELECT pb.*, u.name as member_name, u.profile_photo FROM pt_bookings pb JOIN users u ON u.id = pb.member_id WHERE pb.trainer_id = ? AND pb.status IN (\'pendente\',\'confirmado\') AND pb.date >= date(\'now\') ORDER BY pb.date, pb.start_time');
$stmt->execute([$trainer_id]);
$pending_bookings = $stmt->fetchAll();

$page_title = 'Gerir Aulas';
$extra_css  = ['classes.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Gerir as minhas aulas</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div data-tabs-parent="">
            <div class="tabs">
                <button class="tab-btn active" data-tab="classes">As minhas aulas</button>
                <button class="tab-btn" data-tab="availability">Disponibilidade PT</button>
                <button class="tab-btn" data-tab="bookings">Marcações pendentes</button>
            </div>
            <div class="tab-content active" data-tab-content="classes">
                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon" aria-hidden="true"></div>
                        <h3>Sem aulas atribuídas</h3>
                        <p>Contacte um administrador para lhe atribuir aulas.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $cls): ?>
                    <div class="card" style="margin-bottom:1.5rem">
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem">
                                <div>
                                    <h3><?= e($cls['name']) ?></h3>
                                    <div style="display:flex;gap:0.5rem;margin-top:0.3rem">
                                        <span class="badge badge-primary"><?= e($cls['type']) ?></span>
                                        <span class="badge badge-muted">Duração: <?= $cls['duration_minutes'] ?> min</span>
                                        <span class="badge badge-muted">Cap.: <?= $cls['capacity'] ?></span>
                                    </div>
                                </div>
                            </div>

                            <h4 style="margin-bottom:0.75rem;font-size:0.95rem">Horários</h4>
                            <?php if (empty($class_schedules[$cls['id']])): ?>
                                <p class="text-muted" style="font-size:0.9rem">Sem horários definidos.</p>
                            <?php else: ?>
                                <div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1rem">
                                    <?php foreach ($class_schedules[$cls['id']] as $sch): ?>
                                    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0.75rem;background:var(--color-bg-3);border-radius:var(--radius)">
                                        <span><?= day_name((int)$sch['day_of_week']) ?> — <?= format_time($sch['start_time']) ?></span>
                                        <div style="display:flex;align-items:center;gap:1rem">
                                            <span class="text-muted" style="font-size:0.85rem">Inscritos: <?= $sch['enrolled'] ?></span>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $sch['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remover horário?')">X</button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php $members = $schedule_members[$sch['id']] ?? []; ?>
                                    <div style="padding:0.6rem 0.75rem;border:1px dashed var(--color-border);border-radius:var(--radius);font-size:0.85rem;color:var(--color-text-muted)">
                                        <?php if (empty($members)): ?>
                                            Sem inscritos neste horário.
                                        <?php else: ?>
                                            <strong style="color:var(--color-text);font-size:0.85rem">Inscritos:</strong>
                                            <div style="display:flex;flex-direction:column;gap:0.25rem;margin-top:0.35rem">
                                                <?php foreach ($members as $m): ?>
                                                    <div style="display:flex;align-items:center;gap:0.5rem">
                                                        <img src="<?= e(avatar_url($m['profile_photo'] ?? null, $m['name'])) ?>"
                                                             alt="<?= e($m['name']) ?>" style="width:22px;height:22px;border-radius:50%;object-fit:cover">
                                                        <span><?= e($m['name']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <details style="margin-top:0.75rem">
                                <summary class="btn btn-outline btn-sm" style="cursor:pointer;display:inline-flex">Adicionar horário</summary>
                                <form method="post" style="margin-top:0.75rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_schedule">
                                    <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                                    <div class="filter-group">
                                        <label class="filter-label">Dia</label>
                                        <select name="day_of_week" class="form-control">
                                            <?php for ($d = 0; $d <= 6; $d++): ?>
                                                <option value="<?= $d ?>"><?= day_name($d) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label class="filter-label">Hora</label>
                                        <input type="time" name="start_time" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Adicionar</button>
                                </form>
                            </details>

                            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--color-border)">
                                <a href="/?page=classes/view&id=<?= $cls['id'] ?>" class="btn btn-outline btn-sm">Ver como membro</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tab-content" data-tab-content="availability">
                <div class="card" style="margin-bottom:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:1rem">Adicionar disponibilidade</h3>
                        <form method="post" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_availability">
                            <div class="filter-group">
                                <label class="filter-label">Data</label>
                                <input type="date" name="date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Hora início</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Hora fim</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Adicionar</button>
                        </form>
                    </div>
                </div>

                <?php if (empty($availability)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon" aria-hidden="true"></div>
                        <h3>Sem disponibilidade definida</h3>
                        <p>Adicione os seus horários disponíveis para treinos pessoais.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="display:flex;flex-direction:column;gap:0.5rem">
                                <?php foreach ($availability as $avail): ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:var(--color-bg-3);border-radius:var(--radius)">
                                    <div>
                                        <strong><?= e(date('d/m/Y', strtotime($avail['date']))) ?></strong>
                                        <span class="text-muted" style="margin-left:0.75rem"><?= format_time($avail['start_time']) ?> — <?= format_time($avail['end_time']) ?></span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:1rem">
                                        <?php if ($avail['booked']): ?>
                                            <span class="badge badge-warning">Ocupado<?= $avail['member_name'] ? ' — ' . e($avail['member_name']) : '' ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Disponível</span>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove_availability">
                                                <input type="hidden" name="availability_id" value="<?= $avail['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remover disponibilidade?')">X</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" data-tab-content="bookings">
                <?php if (empty($pending_bookings)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon" aria-hidden="true"></div>
                        <h3>Sem marcações pendentes</h3>
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:0.75rem">
                        <?php foreach ($pending_bookings as $bk): ?>
                        <div class="card">
                            <div class="card-body" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                                <img src="<?= e(avatar_url($bk['profile_photo'] ?? null, $bk['member_name'])) ?>"
                                     alt="<?= e($bk['member_name']) ?>" class="avatar">
                                <div class="flex-1">
                                    <strong><?= e($bk['member_name']) ?></strong>
                                    <div style="font-size:0.88rem;color:var(--color-text-muted)">
                                        <?= e(date('d/m/Y', strtotime($bk['date']))) ?> — <?= format_time($bk['start_time']) ?> às <?= format_time($bk['end_time']) ?>
                                    </div>
                                    <?php if ($bk['notes']): ?>
                                        <div style="font-size:0.85rem;color:var(--color-text-muted);margin-top:0.25rem"><?= e($bk['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;gap:0.5rem">
                                    <?php if ($bk['status'] === 'pendente'): ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="confirm_booking">
                                            <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                                            <button class="btn btn-success btn-sm">Confirmar</button>
                                        </form>
                                    <?php endif; ?>
                                    <span class="badge badge-<?= $bk['status'] === 'confirmado' ? 'success' : 'warning' ?>"><?= pt_status_label($bk['status']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
