<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';

require_login();
$current_user = current_user();
$user_id = (int)($current_user['id']);

$viewed_id = isset($_GET['id']) ? (int)$_GET['id'] : $user_id;

$stmt = db()->prepare('SELECT u.*, tp.bio, tp.specializations, tp.certifications, tp.experience_years FROM users u LEFT JOIN trainer_profiles tp ON tp.user_id = u.id WHERE u.id = ? AND u.active = 1');
$stmt->execute([$viewed_id]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('/?page=404');
}

$classes = [];
if ($profile['role'] === 'trainer') {
    $stmt = db()->prepare('SELECT fc.* FROM fitness_classes fc WHERE fc.trainer_id = ? AND fc.active = 1 ORDER BY fc.name');
    $stmt->execute([$viewed_id]);
    $classes = $stmt->fetchAll();
}

$page_title = e($profile['name']);
$extra_css  = ['profile.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="profile-layout">
            <div class="card" style="text-align:center;padding:2rem">
                <img src="<?= e(avatar_url($profile['profile_photo'] ?? null, $profile['name'])) ?>"
                     alt="Foto de <?= e($profile['name']) ?>" class="avatar-xl" style="margin:0 auto 1rem">
                <h2 style="margin-bottom:0.25rem"><?= e($profile['name']) ?></h2>
                <p class="text-muted" style="margin-bottom:1rem">@<?= e($profile['username']) ?></p>

                <?php if ($profile['role'] === 'trainer'): ?>
                    <span class="badge badge-primary" style="font-size:0.85rem">Treinador</span>
                <?php elseif ($profile['role'] === 'admin'): ?>
                    <span class="badge badge-warning" style="font-size:0.85rem">Administrador</span>
                <?php else: ?>
                    <span class="badge badge-muted" style="font-size:0.85rem">Membro</span>
                <?php endif; ?>

                <?php if ($viewed_id === $user_id): ?>
                    <div style="margin-top:1.5rem">
                        <a href="/?page=profile/edit" class="btn btn-outline btn-full">Editar perfil</a>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <?php if ($profile['role'] === 'trainer'): ?>
                    <?php if ($profile['bio']): ?>
                        <div class="card" style="margin-bottom:1rem">
                            <div class="card-body">
                                <h3 style="margin-bottom:0.75rem">Sobre</h3>
                                <p style="color:var(--color-text-muted)"><?= e($profile['bio']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($profile['specializations']): ?>
                        <div class="card" style="margin-bottom:1rem">
                            <div class="card-body">
                                <h3 style="margin-bottom:0.75rem">Especializações</h3>
                                <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                                    <?php foreach (explode(',', $profile['specializations']) as $spec): ?>
                                        <span class="badge badge-primary"><?= e(trim($spec)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($profile['certifications']): ?>
                        <div class="card" style="margin-bottom:1rem">
                            <div class="card-body">
                                <h3 style="margin-bottom:0.75rem">Certificações</h3>
                                <p style="color:var(--color-text-muted)"><?= e($profile['certifications']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($classes)): ?>
                        <div class="card">
                            <div class="card-body">
                                <h3 style="margin-bottom:1rem">Aulas lecionadas</h3>
                                <div style="display:flex;flex-direction:column;gap:0.5rem">
                                    <?php foreach ($classes as $cls): ?>
                                        <a href="/?page=classes/view&id=<?= $cls['id'] ?>" style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem;border-radius:var(--radius);background:var(--color-bg-3);color:var(--color-text)">
                                            <span class="badge badge-muted"><?= e($cls['type']) ?></span>
                                            <span><?= e($cls['name']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <h3 style="margin-bottom:0.5rem">Informações</h3>
                            <p class="text-muted">Membro do GymFit</p>
                            <p style="margin-top:0.75rem;color:var(--color-text-muted);font-size:0.9rem">
                                Membro desde <?= e(date('d/m/Y', strtotime($profile['created_at']))) ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
