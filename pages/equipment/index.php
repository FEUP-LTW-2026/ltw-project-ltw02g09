<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/db.php';

session_start_safe();

$category = sanitize_string($_GET['category'] ?? '');
$status   = sanitize_string($_GET['status']   ?? '');
$search   = sanitize_string($_GET['search']   ?? '');

$sql  = 'SELECT * FROM equipment WHERE 1=1';
$args = [];

if ($category !== '') {
    $sql  .= ' AND category = ?';
    $args[] = $category;
}
if ($status !== '') {
    $sql  .= ' AND status = ?';
    $args[] = $status;
}
if ($search !== '') {
    $sql  .= ' AND (name LIKE ? OR description LIKE ?)';
    $args[] = "%$search%";
    $args[] = "%$search%";
}

$sql .= ' ORDER BY category, name';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$equipment = $stmt->fetchAll();

$cats_stmt = db()->query('SELECT DISTINCT category FROM equipment ORDER BY category');
$categories = array_column($cats_stmt->fetchAll(), 'category');

$page_title = 'Equipamento';
$extra_css  = ['equipment.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem">
            <div>
                <h1>Equipamento</h1>
                <p class="text-muted">Disponibilidade em tempo real do equipamento da zona de treino</p>
            </div>
            <div style="display:flex;gap:0.75rem;align-items:center">
                <button id="refreshEquipment" class="btn btn-outline btn-sm">
                    <span class="loading-spinner hidden"></span>
                    Atualizar
                </button>
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.85rem;color:var(--color-text-muted);cursor:pointer">
                    <input type="checkbox" id="autoRefreshToggle" style="accent-color:var(--color-accent)">
                    Auto-atualizar (30s)
                </label>
            </div>
        </div>

        <form method="get" action="/" class="filter-bar">
            <input type="hidden" name="page" value="equipment">
            <div class="filter-group" style="flex:1">
                <label class="filter-label">Pesquisar</label>
                <input type="text" name="search" class="form-control" placeholder="Nome do equipamento..."
                    value="<?= e($search) ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">Categoria</label>
                <select name="category" class="form-control" data-filter-auto>
                    <option value="">Todas</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Estado</label>
                <select name="status" class="form-control" data-filter-auto>
                    <option value="">Todos</option>
                    <option value="disponivel" <?= $status === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                    <option value="em_uso" <?= $status === 'em_uso' ? 'selected' : '' ?>>Em uso</option>
                    <option value="manutencao" <?= $status === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Pesquisar</button>
            <?php if ($category || $status || $search): ?>
                <a href="/?page=equipment" class="btn btn-outline">Limpar</a>
            <?php endif; ?>
        </form>

        <?php
        $total_available = 0;
        $total_items     = 0;
        foreach ($equipment as $eq) {
            $total_available += (int)$eq['available_quantity'];
            $total_items += (int)$eq['total_quantity'];
        }
        ?>
        <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
            <div class="stat-card" style="flex:1;min-width:150px;padding:1rem">
                <div class="stat-value" style="font-size:1.5rem"><?= count($equipment) ?></div>
                <div class="stat-label">Tipos de equipamento</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:150px;padding:1rem">
                <div class="stat-value" style="font-size:1.5rem"><?= $total_available ?></div>
                <div class="stat-label">Unidades disponíveis</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:150px;padding:1rem">
                <div class="stat-value" style="font-size:1.5rem"><?= $total_items - $total_available ?></div>
                <div class="stat-label">Em uso / manutenção</div>
            </div>
        </div>

        <?php if (empty($equipment)): ?>
            <div class="empty-state">
                <div class="empty-state-icon" aria-hidden="true"></div>
                <h3>Nenhum equipamento encontrado</h3>
            </div>
        <?php else: ?>
            <div class="grid grid-4" id="equipmentGrid">
                <?php foreach ($equipment as $eq):
                    $pct = $eq['total_quantity'] > 0 ? round(($eq['available_quantity'] / $eq['total_quantity']) * 100) : 0;
                ?>
                <div class="card">
                    <div class="card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem">
                            <h4 class="card-title" style="margin:0"><?= e($eq['name']) ?></h4>
                            <span class="badge badge-muted"><?= e($eq['category']) ?></span>
                        </div>
                        <?php if ($eq['description']): ?>
                            <p style="font-size:0.82rem;color:var(--color-text-muted);margin-bottom:0.75rem"><?= e($eq['description']) ?></p>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;font-size:0.88rem">
                            <span class="<?= equipment_status_class($eq['status']) ?>" style="font-weight:600"><?= equipment_status_label($eq['status']) ?></span>
                            <span class="text-muted"><?= $eq['available_quantity'] ?>/<?= $eq['total_quantity'] ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $extra_js = ['form-handler.js']; include __DIR__ . '/../../templates/footer.php'; ?>
