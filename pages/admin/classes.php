<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/validation.php';

require_role('admin');

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'create') {
        $name        = sanitize_string($_POST['name']        ?? '');
        $description = sanitize_string($_POST['description'] ?? '');
        $type        = sanitize_string($_POST['type']        ?? '');
        $trainer_id  = (int)($_POST['trainer_id']            ?? 0);
        $capacity    = (int)($_POST['capacity']              ?? 20);
        $duration    = (int)($_POST['duration_minutes']      ?? 60);
        $location    = sanitize_string($_POST['location']    ?? 'Sala Principal');

        if (validate_min_length($name, 2) && validate_min_length($type, 2) && $capacity > 0 && $duration > 0) {
            db()->prepare('INSERT INTO fitness_classes (name, description, type, trainer_id, capacity, duration_minutes, location) VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$name, $description, $type, $trainer_id ?: null, $capacity, $duration, $location]);
            $message = 'Aula criada com sucesso.';
        } else {
            $error = 'Preencha os campos obrigatórios.';
        }

    } elseif ($action === 'edit') {
        $id          = (int)($_POST['class_id']              ?? 0);
        $name        = sanitize_string($_POST['name']        ?? '');
        $description = sanitize_string($_POST['description'] ?? '');
        $type        = sanitize_string($_POST['type']        ?? '');
        $trainer_id  = (int)($_POST['trainer_id']            ?? 0);
        $capacity    = (int)($_POST['capacity']              ?? 20);
        $duration    = (int)($_POST['duration_minutes']      ?? 60);
        $location    = sanitize_string($_POST['location']    ?? '');

        if ($id > 0 && validate_min_length($name, 2) && validate_min_length($type, 2)) {
            db()->prepare('UPDATE fitness_classes SET name=?, description=?, type=?, trainer_id=?, capacity=?, duration_minutes=?, location=? WHERE id=?')
               ->execute([$name, $description, $type, $trainer_id ?: null, $capacity, $duration, $location, $id]);
            $message = 'Aula atualizada.';
        } else {
            $error = 'Dados inválidos.';
        }

    } elseif ($action === 'toggle') {
        $id  = (int)($_POST['class_id'] ?? 0);
        $stmt = db()->prepare('SELECT active FROM fitness_classes WHERE id=?');
        $stmt->execute([$id]);
        $cls = $stmt->fetch();
        if ($cls) {
            $new = $cls['active'] ? 0 : 1;
            db()->prepare('UPDATE fitness_classes SET active=? WHERE id=?')->execute([$new, $id]);
            $message = $new ? 'Aula ativada.' : 'Aula desativada.';
        }
    } elseif ($action === 'delete') {
        $id  = (int)($_POST['class_id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM fitness_classes WHERE id=?')->execute([$id]);
            $message = 'Aula removida.';
        }
    }
}

$stmt    = db()->query('SELECT fc.*, u.name as trainer_name FROM fitness_classes fc LEFT JOIN users u ON u.id=fc.trainer_id ORDER BY fc.active DESC, fc.name');
$classes = $stmt->fetchAll();

$trainers_stmt = db()->query('SELECT id, name FROM users WHERE role=\'trainer\' AND active=1 ORDER BY name');
$trainers      = $trainers_stmt->fetchAll();

$types_stmt = db()->query('SELECT DISTINCT type FROM fitness_classes ORDER BY type');
$types      = array_column($types_stmt->fetchAll(), 'type');

$page_title = 'Gerir Aulas';
$extra_css  = ['admin.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
            <h1>Gerir Aulas</h1>
            <button class="btn btn-primary" data-modal="createClassModal">+ Nova aula</button>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Aula</th>
                            <th>Tipo</th>
                            <th>Treinador</th>
                            <th>Capacidade</th>
                            <th>Duração</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $cls): ?>
                        <tr style="<?= !$cls['active'] ? 'opacity:0.55' : '' ?>">
                            <td>
                                <div style="font-weight:500"><?= e($cls['name']) ?></div>
                                <div style="font-size:0.8rem;color:var(--color-text-muted)">Local: <?= e($cls['location']) ?></div>
                            </td>
                            <td><span class="badge badge-primary"><?= e($cls['type']) ?></span></td>
                            <td><?= e($cls['trainer_name'] ?? '—') ?></td>
                            <td><?= $cls['capacity'] ?></td>
                            <td><?= $cls['duration_minutes'] ?> min</td>
                            <td>
                                <?php if ($cls['active']): ?>
                                    <span class="status-available">● Ativa</span>
                                <?php else: ?>
                                    <span class="status-inactive">● Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem">
                                    <button class="btn btn-outline btn-sm"
                                        onclick="openEditClass(<?= htmlspecialchars(json_encode($cls), ENT_QUOTES) ?>)">Editar</button>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                                        <button class="btn btn-sm <?= $cls['active'] ? 'btn-outline' : 'btn-success' ?>">
                                            <?= $cls['active'] ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                                        <button class="btn btn-sm btn-danger"
                                            onclick="return confirm('Remover definitivamente esta aula?')">Remover</button>
                                    </form>
                                    <a href="/?page=classes/view&id=<?= $cls['id'] ?>" class="btn btn-sm btn-outline" target="_blank">Ver</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="createClassModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nova Aula</h3>
            <button class="modal-close">X</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <?php include __DIR__ . '/../../templates/components/class_form.php'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar aula</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay hidden" id="editClassModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar Aula</h3>
            <button class="modal-close">X</button>
        </div>
        <form method="post" id="editClassForm">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="class_id" id="editClassId">
                <?php $edit_mode = true; include __DIR__ . '/../../templates/components/class_form.php'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditClass(cls) {
    document.getElementById('editClassId').value = cls.id;
    document.getElementById('editClassName').value       = cls.name;
    document.getElementById('editClassType').value       = cls.type;
    document.getElementById('editClassDescription').value = cls.description || '';
    document.getElementById('editClassTrainer').value    = cls.trainer_id || '';
    document.getElementById('editClassCapacity').value   = cls.capacity;
    document.getElementById('editClassDuration').value   = cls.duration_minutes;
    document.getElementById('editClassLocation').value   = cls.location;
    document.getElementById('editClassModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
