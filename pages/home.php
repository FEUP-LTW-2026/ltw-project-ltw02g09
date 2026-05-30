<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/db.php';

session_start_safe();

$stmt = db()->query('SELECT COUNT(*) FROM users WHERE role = \'member\' AND active = 1');
$member_count = (int)$stmt->fetchColumn();

$stmt = db()->query('SELECT COUNT(*) FROM users WHERE role = \'trainer\' AND active = 1');
$trainer_count = (int)$stmt->fetchColumn();

$stmt = db()->query('SELECT COUNT(*) FROM fitness_classes WHERE active = 1');
$class_count = (int)$stmt->fetchColumn();

$stmt = db()->query('SELECT COUNT(*) FROM equipment WHERE status = \'disponivel\'');
$equipment_count = (int)$stmt->fetchColumn();

$stmt = db()->query('SELECT fc.*, u.name as trainer_name FROM fitness_classes fc LEFT JOIN users u ON u.id = fc.trainer_id WHERE fc.active = 1 ORDER BY fc.created_at DESC LIMIT 6');
$featured_classes = $stmt->fetchAll();

$page_title = 'Início';
$extra_css  = ['home.css'];
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="hero">
        <div class="container hero-content">
            <h1 class="hero-title">Bem-vindo ao <span>GymFit</span></h1>
            <p class="hero-subtitle">A plataforma de gestão de ginásio mais completa. Reserve aulas, acompanhe o seu progresso e atinja os seus objetivos.</p>
            <div class="hero-actions">
                <?php if (!is_logged_in()): ?>
                    <a href="/?page=register" class="btn btn-primary btn-lg">Começar agora</a>
                    <a href="/?page=classes" class="btn btn-outline btn-lg">Ver aulas</a>
                <?php else: ?>
                    <a href="/?page=classes" class="btn btn-primary btn-lg">Ver aulas</a>
                    <a href="/?page=bookings" class="btn btn-outline btn-lg">Marcações PT</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="home-stats">
        <div class="container">
            <div class="grid grid-4">
                <div class="stat-card">
                    <div class="stat-value"><?= $member_count ?>+</div>
                    <div class="stat-label">Membros ativos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $trainer_count ?></div>
                    <div class="stat-label">Treinadores certificados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $class_count ?>+</div>
                    <div class="stat-label">Tipos de aulas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $equipment_count ?></div>
                    <div class="stat-label">Equipamentos disponíveis</div>
                </div>
            </div>
        </div>
    </div>

    <div class="home-features">
        <div class="container">
            <h2 class="section-title">O que oferecemos</h2>
            <div class="grid grid-2" style="margin-top:1.5rem">
                <div class="feature-card">
                    <div class="feature-icon" aria-hidden="true"></div>
                    <h3>Aulas diversificadas</h3>
                    <p>Yoga, HIIT, Pilates, Spinning, CrossFit e muito mais. Para todos os níveis.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" aria-hidden="true"></div>
                    <h3>Treinadores especializados</h3>
                    <p>Profissionais certificados prontos para ajudá-lo a alcançar os seus objetivos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" aria-hidden="true"></div>
                    <h3>Marcações PT</h3>
                    <p>Reserve sessões de treino pessoal com o calendário interativo.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" aria-hidden="true"></div>
                    <h3>Equipamento moderno</h3>
                    <p>Verifique a disponibilidade do equipamento em tempo real.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" aria-hidden="true"></div>
                    <h3>Avaliações e reviews</h3>
                    <p>Partilhe a sua experiência e ajude outros membros a escolherem as melhores aulas.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" aria-hidden="true"></div>
                    <h3>Planos de treino</h3>
                    <p>Defina objetivos, acompanhe progresso e ajuste rotinas com facilidade.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($featured_classes)): ?>
    <div class="home-classes">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Aulas em destaque</h2>
                <a href="/?page=classes" class="btn btn-outline btn-sm">Ver todas</a>
            </div>
            <div class="grid grid-2">
                <?php foreach ($featured_classes as $cls): ?>
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
                        </div>
                        <h3 class="card-title"><?= e($cls['name']) ?></h3>
                        <p class="card-text"><?= e(mb_substr($cls['description'] ?? '', 0, 100)) ?>...</p>
                        <div style="font-size:0.85rem;color:var(--color-text-muted)">
                            <?php if ($cls['trainer_name']): ?>
                                Treinador: <?= e($cls['trainer_name']) ?> &nbsp;·&nbsp;
                            <?php endif; ?>
                            Duração: <?= e($cls['duration_minutes']) ?> min
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="/?page=classes/view&id=<?= $cls['id'] ?>" class="btn btn-outline btn-sm btn-full">Ver detalhes</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
