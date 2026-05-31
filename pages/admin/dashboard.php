<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';

require_role('admin');

$stats = [];
$db    = db();

$stats['members']   = (int)$db->query('SELECT COUNT(*) FROM users WHERE role=\'member\' AND active=1')->fetchColumn();
$stats['trainers']  = (int)$db->query('SELECT COUNT(*) FROM users WHERE role=\'trainer\' AND active=1')->fetchColumn();
$stats['classes']   = (int)$db->query('SELECT COUNT(*) FROM fitness_classes WHERE active=1')->fetchColumn();
$stats['equipment'] = (int)$db->query('SELECT COUNT(*) FROM equipment WHERE status=\'disponivel\'')->fetchColumn();
$stats['bookings']  = (int)$db->query('SELECT COUNT(*) FROM pt_bookings WHERE status IN (\'pendente\',\'confirmado\')')->fetchColumn();
$stats['reviews']   = (int)$db->query('SELECT COUNT(*) FROM reviews')->fetchColumn();

$recent_users = $db->query('SELECT * FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();

$popular_classes = $db->query('SELECT fc.name, fc.type, COUNT(ce.id) as enrollments FROM fitness_classes fc LEFT JOIN class_enrollments ce ON ce.class_id = fc.id WHERE fc.active=1 GROUP BY fc.id ORDER BY enrollments DESC LIMIT 5')->fetchAll();

$recent_bookings = $db->query('SELECT pb.*, um.name as member_name, ut.name as trainer_name FROM pt_bookings pb JOIN users um ON um.id=pb.member_id JOIN users ut ON ut.id=pb.trainer_id ORDER BY pb.created_at DESC LIMIT 5')->fetchAll();

$page_title = 'Painel de Administração';
$extra_css  = ['admin.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Painel de Administração</h1>
            <p class="text-muted">Visão geral do sistema GymFit</p>
        </div>

        <div class="admin-kpis" style="margin-bottom:2rem">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['members'] ?></div>
                <div class="stat-label">Membros ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['trainers'] ?></div>
                <div class="stat-label">Treinadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['classes'] ?></div>
                <div class="stat-label">Aulas ativas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['equipment'] ?></div>
                <div class="stat-label">Equipamentos disponíveis</div>
            </div>
        </div>

        <div class="admin-two-col" style="margin-bottom:2rem">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['bookings'] ?></div>
                <div class="stat-label">Marcações PT ativas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['reviews'] ?></div>
                <div class="stat-label">Avaliações publicadas</div>
            </div>
        </div>

        <div class="admin-actions-grid" style="margin-bottom:2rem">
            <a href="/?page=admin/users" class="card" style="display:block;padding:1.5rem;text-align:center;color:var(--color-text)">
                <div class="admin-card-icon admin-card-icon-users" aria-hidden="true"></div>
                <strong>Gerir Utilizadores</strong>
            </a>
            <a href="/?page=admin/classes" class="card" style="display:block;padding:1.5rem;text-align:center;color:var(--color-text)">
                <div class="admin-card-icon admin-card-icon-classes" aria-hidden="true"></div>
                <strong>Gerir Aulas</strong>
            </a>
            <a href="/?page=admin/equipment" class="card" style="display:block;padding:1.5rem;text-align:center;color:var(--color-text)">
                <div class="admin-card-icon admin-card-icon-equipment" aria-hidden="true"></div>
                <strong>Gerir Equipamento</strong>
            </a>
        </div>

        <div class="admin-two-col">
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom:1rem">Utilizadores recentes</h3>
                    <div style="display:flex;flex-direction:column;gap:0.5rem">
                        <?php foreach ($recent_users as $u): ?>
                        <div style="display:flex;align-items:center;gap:0.75rem">
                            <img src="<?= e(avatar_url($u['profile_photo'] ?? null, $u['name'])) ?>" class="avatar" style="width:32px;height:32px">
                            <div class="flex-1">
                                <div style="font-size:0.9rem"><?= e($u['name']) ?></div>
                                <div style="font-size:0.78rem;color:var(--color-text-muted)">@<?= e($u['username']) ?></div>
                            </div>
                            <span class="badge badge-<?= $u['role'] === 'admin' ? 'warning' : ($u['role'] === 'trainer' ? 'primary' : 'muted') ?>"><?= e($u['role']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="/?page=admin/users" class="btn btn-outline btn-sm btn-full" style="margin-top:1rem">Ver todos</a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom:1rem">Aulas mais populares</h3>
                    <div style="display:flex;flex-direction:column;gap:0.5rem">
                        <?php foreach ($popular_classes as $cls): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <div>
                                <div style="font-size:0.9rem;font-weight:500"><?= e($cls['name']) ?></div>
                                <div style="font-size:0.78rem;color:var(--color-text-muted)"><?= e($cls['type']) ?></div>
                            </div>
                            <span class="badge badge-primary"><?= $cls['enrollments'] ?> inscritos</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="/?page=admin/analytics" class="btn btn-outline btn-sm btn-full" style="margin-top:1rem">Ver análises</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
