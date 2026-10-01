<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Lojista | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">

    <div class="auth-container">
        <header class="auth-header">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo" class="auth-logo">
            <p>Painel do Lojista</p>
        </header>

        <?php require __DIR__ . '/../../partials/flash.php'; ?>

        <form action="<?= e(url('merchant/login')) ?>" method="POST">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="email">E-mail Comercial</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-primary">Entrar no Painel</button>
        </form>

        <footer class="auth-footer">
            <p>Ainda não é parceiro? <a href="<?= e(url('merchant/register')) ?>">Cadastre sua loja</a></p>
            <p>É cliente? <a href="<?= e(url('customer/balance')) ?>">Consulte seus pontos</a></p>
        </footer>
    </div>

</body>
</html>
