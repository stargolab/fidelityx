<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova senha | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">

    <div class="auth-container">
        <header class="auth-header">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo" class="auth-logo">
            <p>Escolha a nova senha</p>
        </header>

        <?php require __DIR__ . '/../../partials/flash.php'; ?>

        <form action="<?= e(url('merchant/reset')) ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group">
                <label for="new_password">Nova senha</label>
                <input type="password" name="new_password" id="new_password" minlength="<?= (int)$minPassword ?>" maxlength="<?= (int)$maxPassword ?>" autocomplete="new-password" required>
            </div>

            <div class="form-group">
                <label for="new_password_confirm">Confirmar nova senha</label>
                <input type="password" name="new_password_confirm" id="new_password_confirm" minlength="<?= (int)$minPassword ?>" maxlength="<?= (int)$maxPassword ?>" autocomplete="new-password" required>
            </div>
            <p class="muted form-hint">De <?= (int)$minPassword ?> a <?= (int)$maxPassword ?> caracteres. Quem estiver conectado com a senha antiga precisa entrar de novo.</p>

            <button type="submit" class="btn-primary btn-spaced">Salvar nova senha</button>
        </form>
    </div>

    <script src="/js/forms.js"></script>
</body>
</html>
