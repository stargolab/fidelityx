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
            <?php if ($store): ?>
                <p>Seus pontos na <strong class="balance-store"><?= e($store['store_name']) ?></strong></p>
            <?php else: ?>
                <p>Consulte seus pontos de fidelidade</p>
            <?php endif; ?>
        </header>

        <?php if (!$store): ?>
            <?php // sem loja: pede o codigo (vem impresso no cartaz, embaixo do QR). nao lista lojas de proposito ?>
            <?php if ($error === 'loja_invalida'): ?>
                <div class="alert alert-error" id="flash-error" role="alert">Código de loja não encontrado. Confira o código no cartaz da loja.</div>
            <?php endif; ?>

            <p class="muted balance-help">Aponte a câmera do celular para o QR code no balcão da loja, ou digite o código que aparece embaixo dele.</p>

            <form action="/index.php" method="GET">
                <input type="hidden" name="url" value="customer/balance">
                <div class="form-group">
                    <label for="loja">Código da loja</label>
                    <input type="text" name="loja" id="loja"<?= field_error_attr('loja', $error) ?> maxlength="12" autocomplete="off" autocapitalize="characters"
                           placeholder="Ex: 7K2M9QXA" required autofocus>
                </div>
                <button type="submit" class="btn-primary">Continuar</button>
            </form>
        <?php else: ?>
            <?php if ($error === 'telefone_invalido'): ?>
                <div class="alert alert-error" id="flash-error" role="alert">Informe um telefone válido com DDD.</div>
            <?php endif; ?>

            <form action="<?= e(url('customer/balance')) ?>" method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="loja" value="<?= e($store['public_code']) ?>">

                <div class="form-group">
                    <label for="phone">Seu telefone</label>
                    <input type="tel" name="phone" id="phone"<?= field_error_attr('phone', $error) ?> placeholder="(11) 99999-9999" maxlength="15" data-mask="phone"
                           value="<?= e(format_phone($phone)) ?>" required>
                </div>

                <button type="submit" class="btn-primary">Consultar</button>
            </form>

            <?php if ($card === false): ?>
                <p class="muted balance-result">Nenhum cartão fidelidade nesta loja para este telefone.</p>
            <?php elseif ($card): ?>
                <div class="balance-result">
                    <p>Olá, <strong><?= e($firstName) ?></strong>!</p>

                    <div class="card balance-card">
                        <div class="balance-card-head">
                            <strong><?= e($store['store_name']) ?></strong>
                            <strong><?= number_format((int)$card['current_points'], 0, ',', '.') ?> pts</strong>
                        </div>

                        <?php require __DIR__ . '/../partials/reward-progress.php'; ?>

                        <?php if ($rewards): ?>
                            <ul class="muted balance-rewards">
                                <?php foreach ($rewards as $reward): ?>
                                    <li>
                                        <?= e($reward['name']) ?> — <?= (int)$reward['points_cost'] ?> pts
                                        <?= (int)$card['current_points'] >= (int)$reward['points_cost'] ? '✓ disponível' : '' ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <footer class="auth-footer">
            <p><a href="<?= e(url('privacy')) ?>">Privacidade</a> · É lojista? <a href="<?= e(url('merchant/login')) ?>">Entrar no painel</a></p>
        </footer>
    </div>

    <script src="/js/masks.js"></script>
    <script src="/js/forms.js"></script>
</body>
</html>
