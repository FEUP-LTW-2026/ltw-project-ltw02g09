<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('member');
$current_user = current_user();
$user_id      = (int)$current_user['id'];

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'cancel') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $stmt = db()->prepare('SELECT pb.*, pa.booked FROM pt_bookings pb JOIN pt_availability pa ON pa.id = pb.availability_id WHERE pb.id = ? AND pb.member_id = ? AND pb.status IN (\'pendente\',\'confirmado\')');
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch();
        if ($booking) {
            db()->prepare('UPDATE pt_bookings SET status=\'cancelado\' WHERE id=?')->execute([$booking_id]);
            db()->prepare('UPDATE pt_availability SET booked=0 WHERE id=?')->execute([$booking['availability_id']]);
            $message = 'Marcação cancelada com sucesso.';
        }
    }
}

$stmt = db()->prepare('SELECT pb.*, u.name as trainer_name, u.profile_photo as trainer_photo FROM pt_bookings pb JOIN users u ON u.id = pb.trainer_id WHERE pb.member_id = ? ORDER BY pb.date DESC, pb.start_time DESC');
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

$upcoming = array_filter($bookings, fn($b) => $b['date'] >= date('Y-m-d') && in_array($b['status'], ['pendente', 'confirmado']));
$past     = array_filter($bookings, fn($b) => $b['date'] < date('Y-m-d') || $b['status'] === 'concluido');
$cancelled = array_filter($bookings, fn($b) => $b['status'] === 'cancelado');

$stmt = db()->query('SELECT u.id, u.name, u.profile_photo, tp.specializations, (SELECT COUNT(*) FROM pt_availability WHERE trainer_id = u.id AND date >= date(\'now\') AND booked = 0) as slots_available FROM users u JOIN trainer_profiles tp ON tp.user_id = u.id WHERE u.role = \'trainer\' AND u.active = 1 ORDER BY u.name');
$trainers = $stmt->fetchAll();

$page_title = 'As minhas marcações PT';
$extra_css  = ['bookings.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Marcações de treino pessoal</h1>
            <p class="text-muted">Gere as suas sessões com treinadores pessoais</p>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <div class="bookings-layout">
            <div data-tabs-parent="">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="upcoming">Próximas (<?= count($upcoming) ?>)</button>
                    <button class="tab-btn" data-tab="past">Passadas</button>
                    <button class="tab-btn" data-tab="cancelled">Canceladas</button>
                </div>
                <div>
                    <div class="tab-content active" data-tab-content="upcoming">
                        <?php if (empty($upcoming)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon" aria-hidden="true"></div>
                                <h3>Sem marcações próximas</h3>
                                <p>Reserve uma sessão com um dos nossos treinadores.</p>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:0.75rem">
                                <?php foreach ($upcoming as $bk): ?>
                                <div class="card">
                                    <div class="card-body" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                                        <img src="<?= e(avatar_url($bk['trainer_photo'] ?? null, $bk['trainer_name'])) ?>"
                                             alt="<?= e($bk['trainer_name']) ?>" class="avatar">
                                        <div class="flex-1">
                                            <strong><?= e($bk['trainer_name']) ?></strong>
                                            <div style="font-size:0.88rem;color:var(--color-text-muted)">
                                                <?= e(date('d/m/Y', strtotime($bk['date']))) ?> — <?= format_time($bk['start_time']) ?> às <?= format_time($bk['end_time']) ?>
                                            </div>
                                            <?php if ($bk['notes']): ?>
                                                <div style="font-size:0.82rem;color:var(--color-text-muted);margin-top:0.25rem"><?= e($bk['notes']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display:flex;gap:0.5rem;align-items:center">
                                            <span class="badge badge-<?= $bk['status'] === 'confirmado' ? 'success' : 'warning' ?>"><?= pt_status_label($bk['status']) ?></span>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Cancelar esta marcação?')">Cancelar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content" data-tab-content="past">
                        <?php if (empty($past)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon" aria-hidden="true"></div>
                                <h3>Sem sessões passadas</h3>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:0.75rem">
                                <?php foreach ($past as $bk): ?>
                                <div class="card">
                                    <div class="card-body" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;opacity:0.75">
                                        <img src="<?= e(avatar_url($bk['trainer_photo'] ?? null, $bk['trainer_name'])) ?>"
                                             alt="<?= e($bk['trainer_name']) ?>" class="avatar">
                                        <div class="flex-1">
                                            <strong><?= e($bk['trainer_name']) ?></strong>
                                            <div style="font-size:0.88rem;color:var(--color-text-muted)">
                                                <?= e(date('d/m/Y', strtotime($bk['date']))) ?> — <?= format_time($bk['start_time']) ?> às <?= format_time($bk['end_time']) ?>
                                            </div>
                                        </div>
                                        <span class="badge badge-muted"><?= pt_status_label($bk['status']) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content" data-tab-content="cancelled">
                        <?php if (empty($cancelled)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon" aria-hidden="true"></div>
                                <h3>Sem marcações canceladas</h3>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:0.75rem">
                                <?php foreach ($cancelled as $bk): ?>
                                <div class="card">
                                    <div class="card-body" style="display:flex;gap:1rem;align-items:center;opacity:0.6">
                                        <img src="<?= e(avatar_url($bk['trainer_photo'] ?? null, $bk['trainer_name'])) ?>"
                                             alt="<?= e($bk['trainer_name']) ?>" class="avatar">
                                        <div class="flex-1">
                                            <strong><?= e($bk['trainer_name']) ?></strong>
                                            <div style="font-size:0.88rem;color:var(--color-text-muted)">
                                                <?= e(date('d/m/Y', strtotime($bk['date']))) ?> — <?= format_time($bk['start_time']) ?> às <?= format_time($bk['end_time']) ?>
                                            </div>
                                        </div>
                                        <span class="badge badge-danger">Cancelado</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-body">
                        <h3 style="margin-bottom:1rem">Reservar nova sessão</h3>
                        <div style="display:flex;flex-direction:column;gap:0.75rem">
                            <?php foreach ($trainers as $t): ?>
                            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:var(--color-bg-3);border-radius:var(--radius)">
                                <img src="<?= e(avatar_url($t['profile_photo'] ?? null, $t['name'])) ?>"
                                     alt="<?= e($t['name']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                                <div class="flex-1">
                                    <strong style="font-size:0.9rem"><?= e($t['name']) ?></strong>
                                    <div style="font-size:0.8rem;color:var(--color-text-muted)">
                                        <?= $t['slots_available'] ?> horário(s) disponível(is)
                                    </div>
                                </div>
                                <?php if ($t['slots_available'] > 0): ?>
                                    <a href="/?page=bookings/calendar&trainer_id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Marcar</a>
                                <?php else: ?>
                                    <span class="btn btn-secondary btn-sm" style="opacity:0.5">Indisponível</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
