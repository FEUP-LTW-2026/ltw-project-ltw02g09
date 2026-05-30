<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/csrf.php';
$current_user = current_user();
$page_title   = $page_title ?? 'GymFit';
$body_class   = $body_class ?? '';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — GymFit</title>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/layout.css">
    <link rel="stylesheet" href="/css/components.css">
    <?php if (isset($extra_css)): ?>
        <?php foreach ((array)$extra_css as $css): ?>
            <link rel="stylesheet" href="/css/pages/<?= e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= e($body_class) ?>">
