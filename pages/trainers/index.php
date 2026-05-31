<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';

session_start_safe();

$search = sanitize_string($_GET['search'] ?? '');
$spec   = sanitize_string($_GET['specialization'] ?? '');

$sql  = 'SELECT u.*, tp.bio, tp.specializations, tp.certifications, tp.experience_years,
         (SELECT COUNT(*) FROM fitness_classes fc WHERE fc.trainer_id = u.id AND fc.active = 1) as class_count,
         (SELECT AVG(r.rating) FROM reviews r JOIN fitness_classes fc2 ON fc2.id = r.class_id WHERE fc2.trainer_id = u.id) as avg_rating,
         (SELECT COUNT(*) FROM pt_availability pa WHERE pa.trainer_id = u.id AND pa.date >= date(\'now\') AND pa.booked = 0) as slots_available
         FROM users u
         LEFT JOIN trainer_profiles tp ON tp.user_id = u.id
         WHERE u.role = \'trainer\' AND u.active = 1';
$args = [];

if ($search !== '') {
    $sql  .= ' AND (u.name LIKE ? OR tp.specializations LIKE ?)';
    $args[] = "%$search%";
    $args[] = "%$search%";
}
if ($spec !== '') {
    $sql  .= ' AND tp.specializations LIKE ?';
    $args[] = "%$spec%";
}

$sql .= ' ORDER BY u.name';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$trainers = $stmt->fetchAll();

$page_title = 'Treinadores';
$extra_css  = ['trainers.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Os nossos treinadores</h1>
            <p class="text-muted">Conheça a equipa de profissionais certificados do GymFit</p>
        </div>

        <form method="get" action="/" class="filter-bar">
            <input type="hidden" name="page" value="trainers">
            <div class="filter-group" style="flex:1">
                <label class="filter-label">Pesquisar</label>
                <input type="text" name="search" class="form-control" placeholder="Nome ou especialização..."
                    value="<?= e($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Pesquisar</button>
            <?php if ($search): ?>
                <a href="/?page=trainers" class="btn btn-outline">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if (empty($trainers)): ?>
            <div class="empty-state">
                <div class="empty-state-icon" aria-hidden="true"></div>
                <h3>Nenhum treinador encontrado</h3>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($trainers as $t): ?>
                <div class="card trainer-card">
                    <div class="card-body trainer-card-body" style="text-align:center;padding:2rem 1.5rem">
                        <div class="trainer-card-info">
                            <img src="<?= e(avatar_url($t['profile_photo'] ?? null, $t['name'])) ?>"
                                 alt="<?= e($t['name']) ?>" class="avatar-lg" style="margin:0 auto 1rem">
                            <h3 style="margin-bottom:0.25rem"><?= e($t['name']) ?></h3>
                            <?php if ($t['experience_years']): ?>
                                <p class="text-muted" style="font-size:0.85rem;margin-bottom:0.75rem"><?= $t['experience_years'] ?> anos de experiência</p>
                            <?php endif; ?>
                            <?php if ($t['avg_rating']): ?>
                                <div class="rating-display" style="justify-content:center;margin-bottom:0.75rem">
                                    <?= star_rating((float)$t['avg_rating']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($t['specializations']): ?>
                                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;justify-content:center;margin-bottom:1rem">
                                    <?php foreach (array_slice(explode(',', $t['specializations']), 0, 3) as $s): ?>
                                        <span class="badge badge-muted"><?= e(trim($s)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($t['bio']): ?>
                                <p style="font-size:0.85rem;color:var(--color-text-muted);margin-bottom:1rem"><?= e(mb_substr($t['bio'], 0, 100)) ?>...</p>
                            <?php endif; ?>
                        </div>
                        <div class="trainer-card-footer">
                            <?php if ($t['slots_available'] > 0): ?>
                                <div style="font-size:0.8rem;color:var(--color-text-muted);margin-bottom:0.6rem"><?= (int)$t['slots_available'] ?> horário(s) PT disponível(is)</div>
                            <?php endif; ?>
                            <div style="display:flex;gap:0.5rem;flex-direction:column">
                                <a href="/?page=trainers/view&id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Ver perfil</a>
                                <?php if (is_logged_in() && current_user()['role'] === 'member'): ?>
                                    <a href="/?page=bookings/calendar&trainer_id=<?= $t['id'] ?>" class="btn btn-primary btn-sm btn-wrap"><?= $t['slots_available'] > 0 ? 'Marcar treino pessoal' : 'Ver disponibilidade' ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
