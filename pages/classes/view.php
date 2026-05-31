<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';

session_start_safe();

$class_id = (int)($_GET['id'] ?? 0);
if ($class_id <= 0) redirect('/?page=classes');

$stmt = db()->prepare('SELECT fc.*, u.name as trainer_name, u.id as trainer_user_id FROM fitness_classes fc LEFT JOIN users u ON u.id = fc.trainer_id WHERE fc.id = ? AND fc.active = 1');
$stmt->execute([$class_id]);
$cls = $stmt->fetch();
if (!$cls) redirect('/?page=404');

$stmt = db()->prepare('SELECT * FROM class_schedule WHERE class_id = ? ORDER BY day_of_week, start_time');
$stmt->execute([$class_id]);
$schedules = $stmt->fetchAll();

$reviews_stmt = db()->prepare('SELECT r.*, u.name as member_name, u.profile_photo FROM reviews r JOIN users u ON u.id = r.member_id WHERE r.class_id = ? ORDER BY r.created_at DESC');
$reviews_stmt->execute([$class_id]);
$reviews = $reviews_stmt->fetchAll();

$avg_rating = 0;
if (!empty($reviews)) {
    $avg_rating = array_sum(array_column($reviews, 'rating')) / count($reviews);
}

$enrolled_schedules = [];
$user_review = null;
$is_member   = false;
$can_review  = false;

if (is_logged_in()) {
    $cu = current_user();
    if ($cu['role'] === 'member') {
        $is_member = true;
        $uid = (int)$cu['id'];

        $stmt = db()->prepare('SELECT schedule_id FROM class_enrollments WHERE class_id = ? AND member_id = ?');
        $stmt->execute([$class_id, $uid]);
        $enrolled_schedules = array_column($stmt->fetchAll(), 'schedule_id');

        $stmt = db()->prepare('SELECT * FROM reviews WHERE class_id = ? AND member_id = ?');
        $stmt->execute([$class_id, $uid]);
        $user_review = $stmt->fetch() ?: null;

        $can_review = !empty($enrolled_schedules) || $user_review !== null;
    }
}

$enrollment_counts = [];
foreach ($schedules as $sch) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM class_enrollments WHERE schedule_id = ?');
    $stmt->execute([$sch['id']]);
    $enrollment_counts[$sch['id']] = (int)$stmt->fetchColumn();
}

$review_message = '';
$review_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $is_member) {
    csrf_verify();
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = sanitize_string($_POST['comment'] ?? '');

    if (!$can_review) {
        $review_error = 'Só pode avaliar aulas em que esteve inscrito.';
    } elseif ($rating < 1 || $rating > 5) {
        $review_error = 'Selecione uma avaliação entre 1 e 5 estrelas.';
    } else {
        if ($user_review) {
            db()->prepare('UPDATE reviews SET rating=?, comment=?, created_at=datetime(\'now\') WHERE class_id=? AND member_id=?')
               ->execute([$rating, $comment, $class_id, (int)current_user()['id']]);
        } else {
            db()->prepare('INSERT INTO reviews (class_id, member_id, rating, comment) VALUES (?, ?, ?, ?)')
               ->execute([$class_id, (int)current_user()['id'], $rating, $comment]);
        }
        redirect("/?page=classes/view&id=$class_id");
    }
}

$page_title = $cls['name'];
$extra_css  = ['classes.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <a href="/?page=classes" class="btn btn-outline btn-sm" style="margin-bottom:1.5rem">← Voltar às aulas</a>

        <div class="class-detail-grid" style="align-items:start">
            <div>
                <div class="card" style="margin-bottom:1.5rem">
                    <?php $img = class_image_url($cls); ?>
                    <?php if ($img): ?>
                        <img class="card-img" src="<?= e($img) ?>" alt="<?= e($cls['name']) ?>" style="height:240px">
                    <?php else: ?>
                        <div class="card-img-placeholder" style="height:240px;font-size:4rem">
                            <span class="class-type-badge" aria-hidden="true"></span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                            <div>
                                <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem">
                                    <span class="badge badge-primary"><?= e($cls['type']) ?></span>
                                    <span class="badge badge-muted">Local: <?= e($cls['location']) ?></span>
                                    <span class="badge badge-muted">Duração: <?= e($cls['duration_minutes']) ?> min</span>
                                </div>
                                <h1 style="font-size:1.75rem;margin-bottom:0.5rem"><?= e($cls['name']) ?></h1>
                            </div>
                            <?php if (!empty($reviews)): ?>
                                <div class="rating-display" style="flex-direction:column;align-items:flex-end">
                                    <?= star_rating($avg_rating) ?>
                                    <span style="font-size:0.85rem;color:var(--color-text-muted)"><?= number_format($avg_rating, 1) ?> (<?= count($reviews) ?> avaliações)</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($cls['trainer_name']): ?>
                            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem">
                                <span style="color:var(--color-text-muted);font-size:0.9rem">Treinador:</span>
                                <a href="/?page=trainers/view&id=<?= $cls['trainer_user_id'] ?>" class="btn btn-outline btn-sm"><?= e($cls['trainer_name']) ?></a>
                            </div>
                        <?php endif; ?>

                        <p style="color:var(--color-text-muted);line-height:1.7"><?= e($cls['description']) ?></p>

                        <div style="margin-top:1rem;font-size:0.9rem;color:var(--color-text-muted)">
                            Capacidade maxima: <?= e($cls['capacity']) ?> participantes
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:1rem">Horários</h3>
                        <?php if (empty($schedules)): ?>
                            <p class="text-muted">Sem horários definidos.</p>
                        <?php else: ?>
                            <div class="class-schedule-list">
                                <?php foreach ($schedules as $sch):
                                    $count    = $enrollment_counts[$sch['id']] ?? 0;
                                    $capacity = (int)$cls['capacity'];
                                    $full     = $count >= $capacity;
                                    $enrolled = in_array((int)$sch['id'], $enrolled_schedules);
                                    $pct      = $capacity > 0 ? min(100, round(($count / $capacity) * 100)) : 0;
                                ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.75rem;background:var(--color-bg-3);border-radius:var(--radius);flex-wrap:wrap">
                                    <div>
                                        <strong><?= day_name((int)$sch['day_of_week']) ?></strong>
                                        <span style="color:var(--color-text-muted);margin-left:0.5rem"><?= format_time($sch['start_time']) ?></span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:1rem">
                                        <div style="text-align:right;font-size:0.85rem">
                                            <span id="count-<?= $sch['id'] ?>" style="color:var(--color-text-muted)"><?= $count ?>/<?= $capacity ?></span>
                                            <div class="progress-bar" style="width:80px;margin-top:4px">
                                                <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                        <?php if ($is_member): ?>
                                            <?php if ($enrolled): ?>
                                                <button class="btn btn-outline btn-sm" data-enroll="<?= $sch['id'] ?>" data-action="cancel">Cancelar inscrição</button>
                                            <?php elseif (!$full): ?>
                                                <button class="btn btn-primary btn-sm" data-enroll="<?= $sch['id'] ?>" data-action="enroll">Inscrever-me</button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>Lotado</button>
                                            <?php endif; ?>
                                        <?php elseif (!is_logged_in()): ?>
                                            <a href="/?page=login" class="btn btn-outline btn-sm">Entrar para inscrever</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 style="margin-bottom:1rem">Avaliações (<?= count($reviews) ?>)</h3>

                        <?php if ($is_member): ?>
                            <?php if ($review_error): ?>
                                <div class="alert alert-danger"><?= e($review_error) ?></div>
                            <?php endif; ?>
                            <?php if ($can_review): ?>
                                <div style="background:var(--color-bg-3);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem">
                                    <h4 style="margin-bottom:1rem"><?= $user_review ? 'A sua avaliação' : 'Avaliar aula' ?></h4>
                                    <p class="text-muted" style="font-size:0.85rem;margin-bottom:0.75rem">Apenas membros inscritos podem avaliar.</p>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="submit_review" value="1">
                                        <div class="form-group">
                                            <label class="form-label">Avaliação</label>
                                            <div style="display:flex;gap:0.5rem">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <label style="cursor:pointer">
                                                        <input type="radio" name="rating" value="<?= $i ?>" style="display:none"
                                                            <?= ($user_review['rating'] ?? 0) == $i ? 'checked' : '' ?>>
                                                        <span class="star" style="font-size:1.5rem;cursor:pointer" data-star="<?= $i ?>">★</span>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="comment">Comentário (opcional)</label>
                                            <textarea id="comment" name="comment" class="form-control" rows="3" placeholder="Partilhe a sua experiência..."><?= e($user_review['comment'] ?? '') ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">Publicar avaliação</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Só membros inscritos podem avaliar esta aula.</div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (empty($reviews)): ?>
                            <p class="text-muted">Ainda sem avaliações. Seja o primeiro a avaliar.</p>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:1rem">
                                <?php foreach ($reviews as $rev): ?>
                                <div style="padding:1rem;background:var(--color-bg-3);border-radius:var(--radius)">
                                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem">
                                        <img src="<?= e(avatar_url($rev['profile_photo'] ?? null, $rev['member_name'])) ?>"
                                             alt="<?= e($rev['member_name']) ?>" class="avatar" style="width:36px;height:36px">
                                        <div>
                                            <strong style="font-size:0.9rem"><?= e($rev['member_name']) ?></strong>
                                            <div><?= star_rating((float)$rev['rating']) ?></div>
                                        </div>
                                        <span style="margin-left:auto;font-size:0.8rem;color:var(--color-text-muted)"><?= e(date('d/m/Y', strtotime($rev['created_at']))) ?></span>
                                    </div>
                                    <?php if ($rev['comment']): ?>
                                        <p style="font-size:0.9rem;color:var(--color-text-muted);margin:0"><?= e($rev['comment']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <?php if ($cls['trainer_user_id']): ?>
                    <?php
                    $stmt = db()->prepare('SELECT u.*, tp.bio, tp.specializations FROM users u LEFT JOIN trainer_profiles tp ON tp.user_id = u.id WHERE u.id = ?');
                    $stmt->execute([$cls['trainer_user_id']]);
                    $trainer = $stmt->fetch();
                    ?>
                    <?php if ($trainer): ?>
                    <div class="card" style="margin-bottom:1rem;text-align:center;padding:1.5rem">
                        <img src="<?= e(avatar_url($trainer['profile_photo'] ?? null, $trainer['name'])) ?>"
                             alt="<?= e($trainer['name']) ?>" class="avatar-lg" style="margin:0 auto 1rem">
                        <h3 style="margin-bottom:0.25rem"><?= e($trainer['name']) ?></h3>
                        <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">Treinador</p>
                        <?php if ($trainer['bio']): ?>
                            <p style="font-size:0.85rem;color:var(--color-text-muted);margin-bottom:1rem"><?= e(mb_substr($trainer['bio'], 0, 120)) ?>...</p>
                        <?php endif; ?>
                        <a href="/?page=trainers/view&id=<?= $trainer['id'] ?>" class="btn btn-outline btn-sm btn-full">Ver perfil</a>
                        <?php if (is_logged_in() && current_user()['role'] === 'member'): ?>
                            <a href="/?page=bookings/calendar&trainer_id=<?= $trainer['id'] ?>" class="btn btn-primary btn-sm btn-full btn-wrap" style="margin-top:0.5rem">Marcar treino pessoal</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <h4 style="margin-bottom:0.75rem">Informações rápidas</h4>
                        <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.9rem;color:var(--color-text-muted)">
                            <div>Local: <?= e($cls['location']) ?></div>
                            <div>Duração: <?= e($cls['duration_minutes']) ?> minutos</div>
                            <div>Capacidade: <?= e($cls['capacity']) ?></div>
                            <div>Tipo: <?= e($cls['type']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrfTokenHidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<?php $extra_js = ['form-handler.js']; include __DIR__ . '/../../templates/footer.php'; ?>
