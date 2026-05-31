<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

session_start_safe();

$type    = sanitize_string($_GET['type']       ?? '');
$trainer = (int)($_GET['trainer']              ?? 0);
$day     = isset($_GET['day']) && $_GET['day'] !== '' ? (int)$_GET['day'] : -1;
$search  = sanitize_string($_GET['search']     ?? '');
$time    = sanitize_string($_GET['time']       ?? '');
$time    = validate_time($time) ? $time : '';

$sql  = 'SELECT fc.*, u.name as trainer_name, u.id as trainer_user_id,
         (SELECT AVG(r.rating) FROM reviews r WHERE r.class_id = fc.id) as avg_rating,
         (SELECT COUNT(r.id) FROM reviews r WHERE r.class_id = fc.id) as review_count
         FROM fitness_classes fc
         LEFT JOIN users u ON u.id = fc.trainer_id
         WHERE fc.active = 1';
$args = [];

if ($type !== '') {
    $sql  .= ' AND fc.type = ?';
    $args[] = $type;
}
if ($trainer > 0) {
    $sql  .= ' AND fc.trainer_id = ?';
    $args[] = $trainer;
}
if ($search !== '') {
    $sql  .= ' AND (fc.name LIKE ? OR fc.description LIKE ?)';
    $args[] = "%$search%";
    $args[] = "%$search%";
}

$sql .= ' ORDER BY fc.name';

$stmt    = db()->prepare($sql);
$stmt->execute($args);
$classes = $stmt->fetchAll();

if ($day >= 0 || $time !== '') {
    $class_ids = array_column($classes, 'id');
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $conditions = ["class_id IN ($placeholders)"];
        $schedArgs  = $class_ids;
        if ($day >= 0) {
            $conditions[] = "(day_of_week = ? OR (recurring = 0 AND strftime('%w', specific_date) = ?))";
            $schedArgs[]  = $day;
            $schedArgs[]  = (string)$day;
        }
        if ($time !== '') {
            $conditions[] = 'start_time >= ?';
            $schedArgs[]  = $time;
        }
        $schedStmt = db()->prepare('SELECT DISTINCT class_id FROM class_schedule WHERE ' . implode(' AND ', $conditions));
        $schedStmt->execute($schedArgs);
        $filtered_ids = array_column($schedStmt->fetchAll(), 'class_id');
        $classes = array_filter($classes, fn($c) => in_array($c['id'], $filtered_ids));
    } else {
        $classes = [];
    }
}

$types_stmt = db()->query('SELECT DISTINCT type FROM fitness_classes WHERE active = 1 ORDER BY type');
$types      = array_column($types_stmt->fetchAll(), 'type');

$trainers_stmt = db()->query('SELECT u.id, u.name FROM users u WHERE u.role = \'trainer\' AND u.active = 1 ORDER BY u.name');
$trainers = $trainers_stmt->fetchAll();

$schedule_map = [];
if (!empty($classes)) {
    $class_ids = array_column(array_values($classes), 'id');
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $schedules = db()->prepare("SELECT * FROM class_schedule WHERE class_id IN ($placeholders) ORDER BY day_of_week, start_time");
        $schedules->execute($class_ids);
        foreach ($schedules->fetchAll() as $s) {
            $schedule_map[$s['class_id']][] = $s;
        }
    }
}

$enrolled_schedules = [];
if (is_logged_in() && current_user()['role'] === 'member') {
    $uid = (int)current_user()['id'];
    $stmt = db()->prepare('SELECT schedule_id FROM class_enrollments WHERE member_id = ?');
    $stmt->execute([$uid]);
    $enrolled_schedules = array_column($stmt->fetchAll(), 'schedule_id');
}

$page_title = 'Aulas';
$extra_css  = ['classes.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Aulas de fitness</h1>
            <p class="text-muted">Encontre a aula perfeita para os seus objetivos</p>
        </div>

        <form method="get" action="/" class="filter-bar">
            <input type="hidden" name="page" value="classes">
            <div class="filter-group">
                <label class="filter-label">Pesquisar</label>
                <input type="text" name="search" class="form-control" placeholder="Nome da aula..."
                    value="<?= e($search) ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">Tipo</label>
                <select name="type" class="form-control" data-filter-auto>
                    <option value="">Todos</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Treinador</label>
                <select name="trainer" class="form-control" data-filter-auto>
                    <option value="0">Todos</option>
                    <?php foreach ($trainers as $tr): ?>
                        <option value="<?= $tr['id'] ?>" <?= $trainer === (int)$tr['id'] ? 'selected' : '' ?>><?= e($tr['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Dia</label>
                <select name="day" class="form-control" data-filter-auto>
                    <option value="">Todos</option>
                    <?php for ($d = 0; $d <= 6; $d++): ?>
                        <option value="<?= $d ?>" <?= $day === $d ? 'selected' : '' ?>><?= day_name($d) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Hora (a partir de)</label>
                <input type="time" name="time" class="form-control" value="<?= e($time) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Pesquisar</button>
            <?php if ($type || $trainer || $day >= 0 || $search || $time !== ''): ?>
                <a href="/?page=classes" class="btn btn-outline">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if (empty($classes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon" aria-hidden="true"></div>
                <h3>Nenhuma aula encontrada</h3>
                <p>Tente ajustar os filtros de pesquisa.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($classes as $cls):
                    $schedules = $schedule_map[$cls['id']] ?? [];
                ?>
                <div class="card">
                    <?php $img = class_image_url($cls); ?>
                    <?php if ($img): ?>
                        <img class="card-img" src="<?= e($img) ?>" alt="<?= e($cls['name']) ?>">
                    <?php else: ?>
                        <div class="card-img-placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="card-meta">
                            <span class="badge badge-primary"><?= e($cls['type']) ?></span>
                            <span class="badge badge-muted">Duração: <?= e($cls['duration_minutes']) ?> min</span>
                        </div>
                        <h3 class="card-title"><?= e($cls['name']) ?></h3>
                        <p class="card-text"><?= e(mb_substr($cls['description'] ?? '', 0, 90)) ?>...</p>

                        <?php if ($cls['trainer_name']): ?>
                            <div style="font-size:0.85rem;color:var(--color-text-muted);margin-bottom:0.5rem">
                                Treinador: <a href="/?page=trainers/view&id=<?= $cls['trainer_user_id'] ?>" style="color:var(--color-text-muted)"><?= e($cls['trainer_name']) ?></a>
                            </div>
                        <?php endif; ?>

                        <?php if ($cls['avg_rating']): ?>
                            <div class="rating-display" style="margin-bottom:0.5rem">
                                <?= star_rating((float)$cls['avg_rating']) ?>
                                <span style="font-size:0.82rem;color:var(--color-text-muted)">(<?= $cls['review_count'] ?>)</span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($schedules)): ?>
                            <div style="font-size:0.82rem;color:var(--color-text-muted)">
                                <?php foreach (array_slice($schedules, 0, 3) as $sch): ?>
                                    <span><?= day_abbr((int)$sch['day_of_week']) ?> <?= format_time($sch['start_time']) ?></span> &nbsp;
                                <?php endforeach; ?>
                                <?php if (count($schedules) > 3): ?>
                                    <span>+<?= count($schedules) - 3 ?> horários</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="/?page=classes/view&id=<?= $cls['id'] ?>" class="btn btn-outline btn-sm btn-full">Ver detalhes</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
