<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (is_logged_in()) {
    redirect('/?page=home');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = sanitize_string($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor preencha todos os campos.';
    } elseif (!login($username, $password)) {
        $error = 'Credenciais inválidas. Verifique o username e a password.';
    } else {
        $redirect = $_GET['redirect'] ?? '/?page=home';
        if (!is_string($redirect) || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/?page=home';
        }
        redirect($redirect);
    }
}

$page_title = 'Entrar';
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

        <h1 class="auth-title">Bem-vindo de volta</h1>
        <p class="auth-subtitle">Entre na sua conta para continuar</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/?page=login<?= isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '' ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username ou Email</label>
                <input type="text" id="username" name="username" class="form-control"
                    value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="username" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                    required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full">Entrar</button>
        </form>

        <div class="auth-divider"><span>credenciais de demonstração</span></div>
        <div style="font-size:0.82rem;color:var(--color-text-muted);text-align:center;line-height:1.8">
            admin / p4s5w0rd &nbsp;·&nbsp; member / 1234 &nbsp;·&nbsp; joao.silva / 1234
        </div>

        <div class="auth-footer">
            Não tem conta? <a href="/?page=register">Registar</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
