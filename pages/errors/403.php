<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

$page_title = 'Acesso negado';
http_response_code(403);
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content" style="text-align:center;padding:5rem 1rem">
        <div class="error-hero-icon" aria-hidden="true"></div>
        <h1 style="font-size:3rem;margin-bottom:0.5rem">403</h1>
        <h2 style="margin-bottom:1rem">Acesso negado</h2>
        <p class="text-muted" style="max-width:420px;margin:0 auto 2rem">Não tem permissões para aceder a esta página.</p>
        <a href="/?page=home" class="btn btn-primary">Voltar ao início</a>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
