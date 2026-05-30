<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

$page_title = 'Página não encontrada';
http_response_code(404);
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content" style="text-align:center;padding:5rem 1rem">
        <div class="error-hero-icon" aria-hidden="true"></div>
        <h1 style="font-size:3rem;margin-bottom:0.5rem">404</h1>
        <h2 style="margin-bottom:1rem">Página não encontrada</h2>
        <p class="text-muted" style="max-width:420px;margin:0 auto 2rem">A página que procura não existe ou foi removida.</p>
        <a href="/?page=home" class="btn btn-primary">Voltar ao início</a>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
