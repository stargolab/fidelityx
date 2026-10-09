<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">

    <div class="auth-container">
        <header class="auth-header">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo" class="auth-logo">
            <p>Criar uma nova senha</p>
        </header>

        <?php require __DIR__ . '/../../partials/flash.php'; ?>

        <p class="muted">Informe o e-mail de acesso da loja. Enviamos um link para criar uma nova senha, válido por 1 hora.</p>

        <form action="<?= e(url('merchant/forgot')) ?>" method="POST">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="email">E-mail Comercial</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" maxlength="255" required autocomplete="email">
            </div>

            <button type="submit" class="btn-primary">Enviar link</button>
        </form>

        <footer class="auth-footer">
            <p><a href="<?= e(url('merchant/login')) ?>">Voltar para o login</a></p>
        </footer>
    </div>

    <script src="/js/forms.js"></script>
</body>
</html>
