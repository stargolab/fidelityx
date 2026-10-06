<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 | FidelityX</title>
    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/errors.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="bg-glow"></div>
    <div class="error-code">403</div>

    <div class="container">
        <div class="logo-container">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo">
        </div>
        
        <h1>Acesso negado</h1>
        <p>Você não tem permissão para ver esta página. Se isso parecer errado, fale com o administrador.</p>
        
        <a href="<?= e($backUrl) ?>" class="btn-home"><?= e($backLabel) ?></a>
    </div>
</body>
</html>
