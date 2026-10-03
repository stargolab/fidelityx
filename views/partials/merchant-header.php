<?php
// cabecalho das paginas do painel. espera $title definido pela view.
$currentAction = explode('/', (string)($_GET['url'] ?? ''))[1] ?? 'dashboard';
$navItems = [
    'dashboard' => 'Início',
    'rewards'   => 'Prêmios',
    'customers' => 'Clientes',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <nav class="app-nav">
        <a href="<?= e(url('merchant/dashboard')) ?>"><img src="/assets/fidelityx-logo.svg" alt="FidelityX"></a>
        <div class="nav-links">
            <?php foreach ($navItems as $action => $label): ?>
                <a href="<?= e(url('merchant/' . $action)) ?>" class="<?= $currentAction === $action ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <span class="nav-store"><?= e($_SESSION['store_name'] ?? '') ?></span>
        <a href="<?= e(url('merchant/logout')) ?>">Sair</a>
    </nav>

    <main class="app-main">
        <h1><?= e($title) ?></h1>
        <?php require __DIR__ . '/flash.php'; ?>
