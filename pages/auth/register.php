<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/validation.php';

if (is_logged_in()) {
    redirect('/?page=home');
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old = [
        'name'     => sanitize_string($_POST['name']     ?? ''),
        'username' => sanitize_string($_POST['username'] ?? ''),
        'email'    => sanitize_string($_POST['email']    ?? ''),
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    $errors = validate_required($old + ['password' => $password], ['name', 'username', 'email', 'password']);

    if (!isset($errors['name']) && !validate_min_length($old['name'], 2)) {
        $errors['name'] = 'O nome deve ter pelo menos 2 caracteres.';
    }
    if (!isset($errors['username']) && !validate_username($old['username'])) {
        $errors['username'] = 'O username só pode conter letras, números, pontos, hífens e underscores (3-30 caracteres).';
    }
    if (!isset($errors['email']) && !validate_email($old['email'])) {
        $errors['email'] = 'O email não é válido.';
    }
    if (!isset($errors['password']) && !validate_password($password)) {
        $errors['password'] = 'A password deve ter pelo menos 6 caracteres.';
    }
    if (empty($errors['password']) && $password !== $password2) {
        $errors['password2'] = 'As passwords não coincidem.';
    }
    if (!isset($errors['username']) && username_exists($old['username'])) {
        $errors['username'] = 'Este username já está em uso.';
    }
    if (!isset($errors['email']) && email_exists($old['email'])) {
        $errors['email'] = 'Este email já está registado.';
    }

        if (empty($errors)) {
        $role = 'member';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
        $stmt = db()->prepare('INSERT INTO users (name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$old['name'], $old['username'], $old['email'], $hash, $role]);
        $userId = (int)db()->lastInsertId();

        if ($role === 'trainer') {
            db()->prepare('INSERT INTO trainer_profiles (user_id) VALUES (?)')->execute([$userId]);
        }

        login($old['username'], $password);
        redirect('/?page=home');
    }
}

$page_title = 'Criar conta';
$body_class = 'no-navbar';
$extra_css  = ['auth.css'];
include __DIR__ . '/../../templates/header.php';
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <a href="/?page=home" class="brand-link">
                <span class="brand-icon">GF</span>
                <span class="brand-name">GymFit</span>
            </a>
        </div>

        <h1 class="auth-title">Criar conta</h1>
        <p class="auth-subtitle">Junte-se à comunidade GymFit</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">Por favor corrija os erros abaixo.</div>
        <?php endif; ?>

        <form method="post" action="/?page=register">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Nome completo</label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                    value="<?= e($old['name'] ?? '') ?>" required autocomplete="name">
                <?php if (isset($errors['name'])): ?>
                    <div class="form-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                    value="<?= e($old['username'] ?? '') ?>" required autocomplete="username">
                <?php if (isset($errors['username'])): ?>
                    <div class="form-error"><?= e($errors['username']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                    value="<?= e($old['email'] ?? '') ?>" required autocomplete="email">
                <?php if (isset($errors['email'])): ?>
                    <div class="form-error"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                    required autocomplete="new-password" minlength="6">
                <?php if (isset($errors['password'])): ?>
                    <div class="form-error"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="password2">Confirmar password</label>
                <input type="password" id="password2" name="password2" class="form-control <?= isset($errors['password2']) ? 'is-invalid' : '' ?>"
                    required autocomplete="new-password">
                <?php if (isset($errors['password2'])): ?>
                    <div class="form-error"><?= e($errors['password2']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Criar conta</button>
        </form>

        <div class="auth-footer">
            Já tem conta? <a href="/?page=login">Entrar</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
