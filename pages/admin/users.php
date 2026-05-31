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
        $name     = sanitize_string($_POST['name']     ?? '');
        $username = sanitize_string($_POST['username'] ?? '');
        $email    = sanitize_string($_POST['email']    ?? '');
        $role     = sanitize_string($_POST['role']     ?? 'member');
        $password = $_POST['password'] ?? '';

        $errors_local = [];
        if (!validate_min_length($name, 2)) $errors_local[] = 'Nome inválido.';
        if (!validate_username($username)) $errors_local[] = 'Username inválido.';
        if (!validate_email($email))       $errors_local[] = 'Email inválido.';
        if (!validate_password($password)) $errors_local[] = 'Password muito curta.';
        if (username_exists($username))    $errors_local[] = 'Username já em uso.';
        if (email_exists($email))          $errors_local[] = 'Email já registado.';
        if (!in_array($role, ['member','trainer','admin'], true)) $role = 'member';

        if (empty($errors_local)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
            db()->prepare('INSERT INTO users (name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
               ->execute([$name, $username, $email, $hash, $role]);
            $uid = (int)db()->lastInsertId();
            if ($role === 'trainer') {
                db()->prepare('INSERT INTO trainer_profiles (user_id) VALUES (?)')->execute([$uid]);
            }
            $message = 'Utilizador criado com sucesso.';
        } else {
            $error = implode(' ', $errors_local);
        }

    } elseif ($action === 'toggle_active') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $stmt = db()->prepare('SELECT active FROM users WHERE id=?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row && $uid !== (int)current_user()['id']) {
            $new = $row['active'] ? 0 : 1;
            db()->prepare('UPDATE users SET active=? WHERE id=?')->execute([$new, $uid]);
            $message = $new ? 'Conta ativada.' : 'Conta desativada.';
        }

    } elseif ($action === 'elevate_admin') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            db()->prepare('UPDATE users SET role=\'admin\' WHERE id=?')->execute([$uid]);
            $message = 'Utilizador elevado a administrador.';
        }

    } elseif ($action === 'change_role') {
        $uid     = (int)($_POST['user_id'] ?? 0);
        $newRole = sanitize_string($_POST['new_role'] ?? '');
        if ($uid > 0 && in_array($newRole, ['member','trainer','admin'], true)) {
            db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$newRole, $uid]);
            if ($newRole === 'trainer') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM trainer_profiles WHERE user_id=?');
                $stmt->execute([$uid]);
                if ((int)$stmt->fetchColumn() === 0) {
                    db()->prepare('INSERT INTO trainer_profiles (user_id) VALUES (?)')->execute([$uid]);
                }
            }
            $message = 'Função atualizada.';
        }
    }
}

$role_filter   = sanitize_string($_GET['role']   ?? '');
$search_filter = sanitize_string($_GET['search'] ?? '');

$sql  = 'SELECT * FROM users WHERE 1=1';
$args = [];
if ($role_filter !== '') { $sql .= ' AND role=?'; $args[] = $role_filter; }
if ($search_filter !== '') { $sql .= ' AND (name LIKE ? OR username LIKE ? OR email LIKE ?)'; $args[] = "%$search_filter%"; $args[] = "%$search_filter%"; $args[] = "%$search_filter%"; }
$sql .= ' ORDER BY created_at DESC';

$stmt  = db()->prepare($sql);
$stmt->execute($args);
$users = $stmt->fetchAll();

$page_title = 'Gerir Utilizadores';
$extra_css  = ['admin.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
            <h1>Gerir Utilizadores</h1>
            <button class="btn btn-primary" data-modal="createUserModal">+ Novo utilizador</button>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <form method="get" action="/" class="filter-bar">
            <input type="hidden" name="page" value="admin/users">
            <div class="filter-group" style="flex:1">
                <label class="filter-label">Pesquisar</label>
                <input type="text" name="search" class="form-control" value="<?= e($search_filter) ?>" placeholder="Nome, username ou email...">
            </div>
            <div class="filter-group">
                <label class="filter-label">Função</label>
                <select name="role" class="form-control" data-filter-auto>
                    <option value="">Todas</option>
                    <option value="member"  <?= $role_filter === 'member'  ? 'selected' : '' ?>>Membro</option>
                    <option value="trainer" <?= $role_filter === 'trainer' ? 'selected' : '' ?>>Treinador</option>
                    <option value="admin"   <?= $role_filter === 'admin'   ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </form>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Utilizador</th>
                            <th>Email</th>
                            <th>Função</th>
                            <th>Estado</th>
                            <th>Registado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr style="<?= !$u['active'] ? 'opacity:0.55' : '' ?>">
                            <td>
                                <div style="display:flex;align-items:center;gap:0.75rem">
                                    <img src="<?= e(avatar_url($u['profile_photo'] ?? null, $u['name'])) ?>" class="avatar" style="width:36px;height:36px">
                                    <div>
                                        <div style="font-weight:500"><?= e($u['name']) ?></div>
                                        <div style="font-size:0.8rem;color:var(--color-text-muted)">@<?= e($u['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:0.88rem"><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $u['role'] === 'admin' ? 'warning' : ($u['role'] === 'trainer' ? 'primary' : 'muted') ?>">
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['active']): ?>
                                    <span class="status-available">● Ativo</span>
                                <?php else: ?>
                                    <span class="status-inactive">● Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.85rem;color:var(--color-text-muted)"><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                            <td>
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                                    <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button class="btn btn-sm <?= $u['active'] ? 'btn-outline' : 'btn-success' ?>">
                                                <?= $u['active'] ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
                                        <form method="post" style="display:flex;gap:0.35rem;align-items:center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="new_role" class="form-control" style="min-width:140px">
                                                <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Membro</option>
                                                <option value="trainer" <?= $u['role'] === 'trainer' ? 'selected' : '' ?>>Treinador</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                            <button class="btn btn-sm btn-outline">Guardar</button>
                                        </form>
                                        <?php if ($u['role'] !== 'admin'): ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="elevate_admin">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn btn-sm btn-warning"
                                                    onclick="return confirm('Elevar <?= e($u['name']) ?> a administrador?')">Admin</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <a href="/?page=profile&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Ver</a>
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

<div class="modal-overlay hidden" id="createUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Novo Utilizador</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Função</label>
                    <select name="role" class="form-control">
                        <option value="member">Membro</option>
                        <option value="trainer">Treinador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
