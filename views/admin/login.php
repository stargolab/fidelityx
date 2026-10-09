<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Administração | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">

    <div class="auth-container">
        <header class="auth-header">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo" class="auth-logo">
            <p>Administração</p>
        </header>

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <form action="<?= e(url('admin/login')) ?>" method="POST">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary">Entrar</button>
        </form>
    </div>

    <script src="/js/forms.js"></script>
</body>
</html>
