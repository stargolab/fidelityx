<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus pontos | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/auth.css">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="auth-page">

    <div class="auth-container">
        <header class="auth-header">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo" class="auth-logo">
            <p>Consulte seus pontos de fidelidade</p>
        </header>

        <?php if ($error === 'telefone_invalido'): ?>
            <div class="alert alert-error">Informe um telefone válido com DDD.</div>
        <?php endif; ?>

        <form action="<?= e(url('customer/balance')) ?>" method="POST">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="phone">Seu telefone</label>
                <input type="tel" name="phone" id="phone" placeholder="(11) 99999-9999" maxlength="15" data-mask="phone" value="<?= e(format_phone($phone)) ?>" required>
            </div>

            <button type="submit" class="btn-primary">Consultar</button>
        </form>

        <?php if (is_array($cards)): ?>
            <div style="margin-top: 2rem;">
                <?php if (!$cards): ?>
                    <p class="muted">Nenhum cartão fidelidade encontrado para este telefone.</p>
                <?php else: ?>
                    <p style="margin-bottom: 1rem;">Olá, <strong><?= e($firstName) ?></strong>! Seus pontos:</p>

                    <?php foreach ($cards as $card): ?>
                        <div class="card" style="padding: 1rem;">
                            <div style="display: flex; justify-content: space-between; gap: 1rem;">
                                <strong><?= e($card['store_name']) ?></strong>
                                <strong><?= (int)$card['current_points'] ?> pts</strong>
                            </div>

                            <?php $progress = $card['progress']; require __DIR__ . '/../partials/reward-progress.php'; ?>

                            <?php if ($card['rewards']): ?>
                                <ul class="muted" style="margin: 0.5rem 0 0 1.25rem; font-size: 0.85rem;">
                                    <?php foreach ($card['rewards'] as $reward): ?>
                                        <li>
                                            <?= e($reward['name']) ?> — <?= (int)$reward['points_cost'] ?> pts
                                            <?= (int)$card['current_points'] >= (int)$reward['points_cost'] ? '✓ disponível' : '' ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <footer class="auth-footer">
            <p>É lojista? <a href="<?= e(url('merchant/login')) ?>">Entrar no painel</a></p>
        </footer>
    </div>

    <script src="/js/masks.js"></script>
</body>
</html>
