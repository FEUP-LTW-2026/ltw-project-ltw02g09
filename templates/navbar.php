<?php
require_once __DIR__ . '/../includes/csrf.php';
$current_user = current_user();
$user_role    = $current_user['role'] ?? 'guest';
$current_page = $_GET['page'] ?? 'home';

function nav_active(string $page): string {
    global $current_page;
    return str_starts_with($current_page, $page) ? 'active' : '';
}
?>
<nav class="navbar">
    <div class="navbar-brand">
        <a href="/?page=home" class="brand-link">
            <span class="brand-icon">GF</span>
            <span class="brand-name">GymFit</span>
        </a>
    </div>

    <button class="navbar-toggle" id="navToggle" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

    <div class="navbar-menu" id="navMenu">
        <div class="nav-user">
            <?php if ($current_user): ?>
                <div class="user-menu" id="userMenu">
                    <button class="user-menu-btn" id="userMenuBtn">
                        <img src="<?= e(avatar_url($current_user['profile_photo'] ?? null, $current_user['name'])) ?>"
                             alt="Foto de perfil" class="user-avatar-small">
                        <span class="user-name"><?= e($current_user['name']) ?></span>
                        <span class="chevron">▼</span>
                    </button>
                    <ul class="user-dropdown" id="userDropdown">
                        <li><a href="/?page=profile">O meu perfil</a></li>
                        <li><a href="/?page=profile/edit">Editar perfil</a></li>
                        <?php if ($user_role === 'member'): ?>
                            <li><a href="/?page=bookings">As minhas marcações</a></li>
                        <?php endif; ?>
                        <li class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="/?page=logout">
                                <?= csrf_field() ?>
                                <button type="submit" class="logout-link">Terminar sessão</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="/?page=login" class="btn btn-outline btn-sm">Entrar</a>
                <a href="/?page=register" class="btn btn-primary btn-sm">Registar</a>
            <?php endif; ?>
        </div>
        <ul class="nav-links">
            <li><a href="/?page=home" class="<?= nav_active('home') ?>">Início</a></li>
            <li><a href="/?page=classes" class="<?= nav_active('classes') ?>">Aulas</a></li>
            <li><a href="/?page=trainers" class="<?= nav_active('trainers') ?>">Treinadores</a></li>
            <li><a href="/?page=equipment" class="<?= nav_active('equipment') ?>">Equipamento</a></li>
            <?php if ($user_role === 'member'): ?>
                <li><a href="/?page=bookings" class="<?= nav_active('bookings') ?>">Marcações PT</a></li>
            <?php endif; ?>
            <?php if ($user_role === 'trainer'): ?>
                <li><a href="/?page=classes/manage" class="<?= nav_active('classes/manage') ?>">Gerir Aulas</a></li>
            <?php endif; ?>
            <?php if ($user_role === 'admin'): ?>
                <li><a href="/?page=admin/dashboard" class="<?= nav_active('admin') ?>">Administração</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
