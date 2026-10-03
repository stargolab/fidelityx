<?php
// cabecalho das paginas do painel. espera $title definido pela view.
$currentAction = explode('/', (string)($_GET['url'] ?? ''))[1] ?? 'dashboard';
$navItems = [
    'dashboard' => 'Início',
    'rewards'   => 'Prêmios',
    'customers' => 'Clientes',
    'poster'    => 'Cartaz',
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
    <?php // marca que o js esta ligado: so assim o menu do celular comeca fechado (sem js os links ficam visiveis) ?>
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
    <nav class="app-nav">
        <a href="<?= e(url('merchant/dashboard')) ?>" class="nav-logo"><img src="/assets/fidelityx-logo.svg" alt="FidelityX"></a>

        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="nav-menu">Menu</button>

        <div class="nav-menu" id="nav-menu">
            <div class="nav-links">
                <?php foreach ($navItems as $action => $label): ?>
                    <a href="<?= e(url('merchant/' . $action)) ?>" class="<?= $currentAction === $action ? 'active' : '' ?>"<?= $currentAction === $action ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <span class="nav-store"><?= e($_SESSION['store_name'] ?? '') ?></span>
            <a href="<?= e(url('merchant/logout')) ?>">Sair</a>
        </div>
    </nav>
    <script>
        // abre/fecha o menu no celular (no pc o botao fica escondido e o menu sempre aparece)
        document.querySelector('.nav-toggle').addEventListener('click', function () {
            var open = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!open));
            document.getElementById('nav-menu').classList.toggle('open', !open);
        });
    </script>

    <main class="app-main">
        <h1><?= e($title) ?></h1>
        <?php require __DIR__ . '/flash.php'; ?>
