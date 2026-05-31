<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';

require_role('admin');

$db = db();

$top_classes = $db->query('SELECT fc.name, fc.type, COUNT(ce.id) as enrollments FROM fitness_classes fc LEFT JOIN class_enrollments ce ON ce.class_id=fc.id WHERE fc.active=1 GROUP BY fc.id ORDER BY enrollments DESC LIMIT 10')->fetchAll();

$trainer_ratings = $db->query('SELECT u.name, AVG(r.rating) as avg_rating, COUNT(r.id) as review_count FROM users u JOIN fitness_classes fc ON fc.trainer_id=u.id JOIN reviews r ON r.class_id=fc.id WHERE u.active=1 GROUP BY u.id ORDER BY avg_rating DESC')->fetchAll();

$equipment_usage = $db->query('SELECT name, category, total_quantity, available_quantity, status, ROUND(100.0*(total_quantity-available_quantity)/total_quantity,1) as usage_pct FROM equipment ORDER BY usage_pct DESC')->fetchAll();

$bookings_by_trainer = $db->query('SELECT u.name, COUNT(pb.id) as total FROM pt_bookings pb JOIN users u ON u.id=pb.trainer_id WHERE pb.status IN (\'confirmado\',\'concluido\') GROUP BY u.id ORDER BY total DESC')->fetchAll();

$page_title = 'Análises';
$extra_css  = ['admin.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Análises do sistema</h1>
            <p class="text-muted">Estatísticas e métricas do GymFit</p>
        </div>

        <div class="admin-two-col" style="margin-bottom:2rem">
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom:1rem">Aulas mais populares</h3>
                    <?php if (empty($top_classes)): ?>
                        <p class="text-muted">Sem dados.</p>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:0.75rem">
                            <?php foreach ($top_classes as $i => $cls): ?>
                            <div style="display:flex;align-items:center;gap:0.75rem">
                                <span style="font-size:1.1rem;font-weight:700;color:var(--color-accent);min-width:28px">#<?= $i+1 ?></span>
                                <div class="flex-1">
                                    <div style="font-size:0.92rem;font-weight:500"><?= e($cls['name']) ?></div>
                                    <div style="font-size:0.8rem;color:var(--color-text-muted)"><?= e($cls['type']) ?></div>
                                </div>
                                <span class="badge badge-primary"><?= $cls['enrollments'] ?> inscrições</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom:1rem">Classificação dos treinadores</h3>
                    <?php if (empty($trainer_ratings)): ?>
                        <p class="text-muted">Sem avaliações.</p>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:0.75rem">
                            <?php foreach ($trainer_ratings as $tr): ?>
                            <div style="display:flex;align-items:center;gap:0.75rem">
                                <div class="flex-1">
                                    <div style="font-size:0.92rem;font-weight:500"><?= e($tr['name']) ?></div>
                                    <div><?= star_rating((float)$tr['avg_rating']) ?></div>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-size:0.9rem;color:var(--color-accent);font-weight:600"><?= number_format((float)$tr['avg_rating'], 1) ?></div>
                                    <div style="font-size:0.78rem;color:var(--color-text-muted)"><?= $tr['review_count'] ?> avaliações</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-two-col">
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom:1rem">Utilização do equipamento</h3>
                    <div style="display:flex;flex-direction:column;gap:0.75rem">
                        <?php foreach (array_slice($equipment_usage, 0, 10) as $eq): ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;font-size:0.88rem">
                                <span><?= e($eq['name']) ?></span>
                                <span class="text-muted"><?= $eq['usage_pct'] ?>% em uso</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?= $eq['usage_pct'] ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom:1rem">Sessões PT por treinador</h3>
                    <?php if (empty($bookings_by_trainer)): ?>
                        <p class="text-muted">Sem sessões confirmadas.</p>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:0.75rem">
                            <?php foreach ($bookings_by_trainer as $tr): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <span style="font-size:0.9rem"><?= e($tr['name']) ?></span>
                                <span class="badge badge-success"><?= $tr['total'] ?> sessões</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
