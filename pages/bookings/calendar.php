<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('member');
$current_user = current_user();
$user_id      = (int)$current_user['id'];

$trainer_id = (int)($_GET['trainer_id'] ?? 0);
if ($trainer_id <= 0) redirect('/?page=bookings');

$stmt = db()->prepare('SELECT u.*, tp.bio, tp.specializations FROM users u LEFT JOIN trainer_profiles tp ON tp.user_id = u.id WHERE u.id = ? AND u.role = \'trainer\' AND u.active = 1');
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch();
if (!$trainer) redirect('/?page=404');

$page_title  = 'Marcar sessão com ' . $trainer['name'];
$extra_css   = ['bookings.css', 'calendar.css'];
$extra_js    = ['calendar.js', 'form-handler.js'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <a href="/?page=bookings" class="btn btn-outline btn-sm" style="margin-bottom:1.5rem">← Voltar às marcações</a>

        <div class="bookings-calendar-layout">
            <div>
                <div class="page-header">
                    <h1>Marcar sessão de treino pessoal</h1>
                    <p class="text-muted">Selecione um dia no calendário para ver os horários disponíveis</p>
                </div>

                <div id="bookingCalendar" style="margin-bottom:1.5rem"></div>

                <div class="card">
                    <div class="card-body">
                        <h3 id="daySlotsTitle" style="margin-bottom:1rem">Selecione um dia</h3>
                        <div id="daySlots">
                            <p class="text-muted">Clique num dia no calendário para ver os horários disponíveis.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" style="text-align:center;padding:1.5rem">
                    <img src="<?= e(avatar_url($trainer['profile_photo'] ?? null, $trainer['name'])) ?>"
                         alt="<?= e($trainer['name']) ?>" class="avatar-lg" style="margin:0 auto 1rem">
                    <h3><?= e($trainer['name']) ?></h3>
                    <p class="text-muted" style="font-size:0.9rem">Treinador Pessoal</p>
                    <?php if ($trainer['specializations']): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:0.35rem;justify-content:center;margin-top:0.75rem">
                            <?php foreach (array_slice(explode(',', $trainer['specializations']), 0, 4) as $s): ?>
                                <span class="badge badge-muted"><?= e(trim($s)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a href="/?page=trainers/view&id=<?= $trainer_id ?>" class="btn btn-outline btn-sm btn-full" style="margin-top:1rem">Ver perfil completo</a>
                </div>

                <div class="card" style="margin-top:1rem">
                    <div class="card-body">
                        <h4 style="margin-bottom:0.5rem">Legenda</h4>
                        <div style="display:flex;flex-direction:column;gap:0.4rem;font-size:0.85rem">
                            <div style="display:flex;align-items:center;gap:0.5rem">
                                <span style="width:12px;height:12px;border-radius:2px;background:rgba(76,175,80,0.7);display:inline-block"></span>
                                Horário disponível
                            </div>
                            <div style="display:flex;align-items:center;gap:0.5rem">
                                <span style="width:12px;height:12px;border-radius:2px;background:rgba(229,57,53,0.7);display:inline-block"></span>
                                Horário ocupado
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="bookingModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Confirmar marcação</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="post" action="/?page=bookings/create">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="trainer_id" value="<?= $trainer_id ?>">
                <input type="hidden" name="slot_id" id="bookingSlotId">

                <div style="background:var(--color-bg-3);border-radius:var(--radius);padding:1rem;margin-bottom:1.25rem">
                    <div style="font-size:0.85rem;color:var(--color-text-muted);margin-bottom:0.25rem">Com</div>
                    <strong><?= e($trainer['name']) ?></strong>
                    <div style="margin-top:0.5rem;font-size:0.9rem;color:var(--color-text-muted)">
                        Data: <span id="bookingDate"></span><br>
                        Hora: <span id="bookingTime"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bookingNotes">Notas (opcional)</label>
                    <textarea id="bookingNotes" name="notes" class="form-control" rows="3"
                        placeholder="Objetivos, lesões, preferências..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar marcação</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initBookingCalendar(<?= $trainer_id ?>);
});
</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
