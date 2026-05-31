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

    if ($action === 'add') {
        $name        = sanitize_string($_POST['name'] ?? '');
        $description = sanitize_string($_POST['description'] ?? '');
        $category    = sanitize_string($_POST['category'] ?? '');
        $total       = (int)($_POST['total_quantity'] ?? 1);
        $available   = (int)($_POST['available_quantity'] ?? 1);
        $status      = sanitize_string($_POST['status'] ?? 'disponivel');

        if (validate_min_length($name, 2) && validate_min_length($category, 2) && $total >= 1 && $available >= 0 && $available <= $total) {
            db()->prepare('INSERT INTO equipment (name, description, category, total_quantity, available_quantity, status) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$name, $description, $category, $total, $available, $status]);
            $message = 'Equipamento adicionado com sucesso.';
        } else {
            $error = 'Preencha todos os campos obrigatórios corretamente.';
        }
    } elseif ($action === 'edit') {
        $id          = (int)($_POST['equipment_id'] ?? 0);
        $name        = sanitize_string($_POST['name'] ?? '');
        $description = sanitize_string($_POST['description'] ?? '');
        $category    = sanitize_string($_POST['category'] ?? '');
        $total       = (int)($_POST['total_quantity'] ?? 1);
        $available   = (int)($_POST['available_quantity'] ?? 0);
        $status      = sanitize_string($_POST['status'] ?? 'disponivel');

        if ($id > 0 && validate_min_length($name, 2) && $total >= 1 && $available >= 0 && $available <= $total) {
            db()->prepare('UPDATE equipment SET name=?, description=?, category=?, total_quantity=?, available_quantity=?, status=? WHERE id=?')
               ->execute([$name, $description, $category, $total, $available, $status, $id]);
            $message = 'Equipamento atualizado.';
        } else {
            $error = 'Dados inválidos.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['equipment_id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM equipment WHERE id = ?')->execute([$id]);
            $message = 'Equipamento removido.';
        }
    } elseif ($action === 'update_status') {
        $id     = (int)($_POST['equipment_id'] ?? 0);
        $status = sanitize_string($_POST['status'] ?? '');
        $avail  = (int)($_POST['available_quantity'] ?? 0);
        $allowed = ['disponivel', 'em_uso', 'manutencao', 'inativo'];
        if ($id > 0 && in_array($status, $allowed, true)) {
            db()->prepare('UPDATE equipment SET status=?, available_quantity=? WHERE id=?')->execute([$status, $avail, $id]);
            $message = 'Estado atualizado.';
        }
    }
}

$stmt = db()->query('SELECT * FROM equipment ORDER BY category, name');
$equipment = $stmt->fetchAll();

$page_title = 'Gerir Equipamento';
$extra_css  = ['equipment.css', 'admin.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
            <h1>Gerir Equipamento</h1>
            <button class="btn btn-primary" data-modal="addEquipmentModal">+ Adicionar equipamento</button>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Total</th>
                            <th>Disponível</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipment as $eq): ?>
                        <tr>
                            <td>
                                <strong><?= e($eq['name']) ?></strong>
                                <?php if ($eq['description']): ?>
                                    <div style="font-size:0.8rem;color:var(--color-text-muted)"><?= e(mb_substr($eq['description'], 0, 60)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($eq['category']) ?></td>
                            <td><?= $eq['total_quantity'] ?></td>
                            <td><?= $eq['available_quantity'] ?></td>
                            <td><span class="<?= equipment_status_class($eq['status']) ?>"><?= equipment_status_label($eq['status']) ?></span></td>
                            <td>
                                <div style="display:flex;gap:0.5rem">
                                    <button class="btn btn-outline btn-sm"
                                        onclick="openEditEquipment(<?= htmlspecialchars(json_encode($eq), ENT_QUOTES) ?>)">Editar</button>
                                    <form method="post" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Remover \'<?= e($eq['name']) ?>\'?')">Remover</button>
                                    </form>
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

<div class="modal-overlay hidden" id="addEquipmentModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Adicionar Equipamento</h3>
               <button class="modal-close">X</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria *</label>
                    <input type="text" name="category" class="form-control" required placeholder="Ex: Cardio, Força, Funcional">
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label">Quantidade total *</label>
                        <input type="number" name="total_quantity" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Disponível</label>
                        <input type="number" name="available_quantity" class="form-control" value="1" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-control">
                            <option value="disponivel">Disponível</option>
                            <option value="em_uso">Em Uso</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay hidden" id="editEquipmentModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar Equipamento</h3>
               <button class="modal-close">X</button>
        </div>
        <form method="post" id="editEquipmentForm">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="equipment_id" id="editEquipmentId">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" id="editEquipmentName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria *</label>
                    <input type="text" name="category" id="editEquipmentCategory" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" id="editEquipmentDescription" class="form-control" rows="2"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label">Total</label>
                        <input type="number" name="total_quantity" id="editEquipmentTotal" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Disponível</label>
                        <input type="number" name="available_quantity" id="editEquipmentAvailable" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="status" id="editEquipmentStatus" class="form-control">
                            <option value="disponivel">Disponível</option>
                            <option value="em_uso">Em Uso</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditEquipment(eq) {
    document.getElementById('editEquipmentId').value          = eq.id;
    document.getElementById('editEquipmentName').value        = eq.name;
    document.getElementById('editEquipmentCategory').value    = eq.category;
    document.getElementById('editEquipmentDescription').value = eq.description || '';
    document.getElementById('editEquipmentTotal').value       = eq.total_quantity;
    document.getElementById('editEquipmentAvailable').value   = eq.available_quantity;
    document.getElementById('editEquipmentStatus').value      = eq.status;
    document.getElementById('editEquipmentModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
