<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';

session_start_safe();

$trainer_id = (int)($_GET['id'] ?? 0);
if ($trainer_id <= 0) redirect('/?page=trainers');

$stmt = db()->prepare('SELECT u.*, tp.bio, tp.specializations, tp.certifications, tp.experience_years FROM users u LEFT JOIN trainer_profiles tp ON tp.user_id = u.id WHERE u.id = ? AND u.role = \'trainer\' AND u.active = 1');
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch();
if (!$trainer) redirect('/?page=404');

$stmt = db()->prepare('SELECT fc.*, (SELECT AVG(r.rating) FROM reviews r WHERE r.class_id = fc.id) as avg_rating FROM fitness_classes fc WHERE fc.trainer_id = ? AND fc.active = 1 ORDER BY fc.name');
$stmt->execute([$trainer_id]);
$classes = $stmt->fetchAll();

$stmt = db()->prepare('SELECT AVG(r.rating) FROM reviews r JOIN fitness_classes fc ON fc.id = r.class_id WHERE fc.trainer_id = ?');
$stmt->execute([$trainer_id]);
$avg_rating = (float)($stmt->fetchColumn() ?: 0);

$stmt = db()->prepare('SELECT COUNT(*) FROM pt_availability WHERE trainer_id = ? AND date >= date(\'now\') AND booked = 0');
$stmt->execute([$trainer_id]);
$available_slots = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT date, start_time, end_time FROM pt_availability WHERE trainer_id = ? AND date >= date(\'now\') AND booked = 0 ORDER BY date, start_time LIMIT 4');
$stmt->execute([$trainer_id]);
$available_times = $stmt->fetchAll();

$page_title = $trainer['name'];
$extra_css  = ['trainers.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <a href="/?page=trainers" class="btn btn-outline btn-sm" style="margin-bottom:1.5rem">← Voltar aos treinadores</a>

        <div class="trainer-profile-grid">
            <div>
                <div class="card" style="text-align:center;padding:2rem;margin-bottom:1rem">
                    <img src="<?= e(avatar_url($trainer['profile_photo'] ?? null, $trainer['name'])) ?>"
                         alt="<?= e($trainer['name']) ?>" class="avatar-xl" style="margin:0 auto 1rem">
                    <h2 style="margin-bottom:0.25rem"><?= e($trainer['name']) ?></h2>
                    <p class="text-muted">Treinador Certificado</p>

                    <?php if ($avg_rating > 0): ?>
                        <div class="rating-display" style="justify-content:center;margin:0.75rem 0">
                            <?= star_rating($avg_rating) ?>
                            <span style="font-size:0.85rem;color:var(--color-text-muted)"><?= number_format($avg_rating, 1) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($trainer['experience_years']): ?>
                        <div style="font-size:0.9rem;color:var(--color-text-muted);margin:0.5rem 0">
                            <?= $trainer['experience_years'] ?> anos de experiência
                        </div>
                    <?php endif; ?>

                    <?php if ($available_slots > 0 && is_logged_in() && current_user()['role'] === 'member'): ?>
                        <div style="margin-top:1.25rem">
                            <a href="/?page=bookings/calendar&trainer_id=<?= $trainer_id ?>" class="btn btn-primary btn-full btn-wrap">
                                Marcar treino pessoal
                            </a>
                            <div style="font-size:0.8rem;color:var(--color-text-muted);margin-top:0.4rem"><?= $available_slots ?> horário(s) disponível(is)</div>
                        </div>
                    <?php elseif (is_logged_in() && current_user()['role'] === 'member'): ?>
                        <div style="margin-top:1.25rem">
                            <button class="btn btn-secondary btn-full" disabled>Sem disponibilidade</button>
                        </div>
                    <?php elseif (!is_logged_in()): ?>
                        <div style="margin-top:1.25rem">
                            <a href="/?page=login" class="btn btn-outline btn-full">Entrar para marcar</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($trainer['specializations']): ?>
                <div class="card" style="margin-bottom:1rem">
                    <div class="card-body">
                        <h4 style="margin-bottom:0.75rem">Especializações</h4>
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem">
                            <?php foreach (explode(',', $trainer['specializations']) as $spec): ?>
                                <span class="badge badge-primary"><?= e(trim($spec)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($trainer['certifications']): ?>
                <div class="card">
                    <div class="card-body">
                        <h4 style="margin-bottom:0.75rem">Certificações</h4>
                        <p style="font-size:0.9rem;color:var(--color-text-muted);margin:0"><?= e($trainer['certifications']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <?php if ($trainer['bio']): ?>
                <div class="card" style="margin-bottom:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:0.75rem">Sobre <?= e($trainer['name']) ?></h3>
                        <p style="color:var(--color-text-muted);line-height:1.7"><?= e($trainer['bio']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($classes)): ?>
                <div class="card">
                    <div class="card-body">
                        <h3 style="margin-bottom:1rem">Aulas lecionadas</h3>
                        <div class="grid grid-2" style="gap:1rem">
                            <?php foreach ($classes as $cls): ?>
                            <a href="/?page=classes/view&id=<?= $cls['id'] ?>" style="display:block;padding:1rem;background:var(--color-bg-3);border-radius:var(--radius);border:1px solid var(--color-border);color:var(--color-text);transition:border-color 0.2s">
                                <div style="display:flex;gap:0.5rem;margin-bottom:0.4rem">
                                    <span class="badge badge-primary"><?= e($cls['type']) ?></span>
                                </div>
                                <strong><?= e($cls['name']) ?></strong>
                                <?php if ($cls['avg_rating']): ?>
                                    <div style="margin-top:0.3rem"><?= star_rating((float)$cls['avg_rating']) ?></div>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($available_times)): ?>
                <div class="card" style="margin-top:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:1rem">Próximos Horários Livres (PT)</h3>
                        <div class="grid grid-2" style="gap:1rem">
                            <?php foreach ($available_times as $time): ?>
                            <div style="display:block;padding:1rem;background:var(--color-bg-3);border-radius:var(--radius);border:1px solid var(--color-border);color:var(--color-text);">
                                <strong><?= e(date('d/m/Y', strtotime($time['date']))) ?></strong>
                                <div style="margin-top:0.3rem"><?= e(substr($time['start_time'], 0, 5)) ?> &ndash; <?= e(substr($time['end_time'], 0, 5)) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
