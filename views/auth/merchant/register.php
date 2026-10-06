<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Lojista | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">

    <div class="auth-container" style="max-width: 600px;"> <header class="auth-header">
            <img src="/assets/fidelityx-logo.svg" alt="FidelityX Logo" class="auth-logo">
            <p>Registre sua empresa no ecossistema FidelityX</p>
        </header>

        <?php require __DIR__ . '/../../partials/flash.php'; ?>

        <form action="<?= e(url('merchant/register')) ?>" method="POST">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="owner_name">Nome do Responsável</label>
                <input type="text" name="owner_name" id="owner_name" placeholder="Quem responderá pela conta?" maxlength="255" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="shop_name">Nome da Loja (Fantasia)</label>
                    <input type="text" name="shop_name" id="shop_name" placeholder="Ex: Burguer do Bairro" maxlength="255" required>
                </div>

                <div class="form-group">
                    <label for="category">Segmento</label>
                    <select name="category" id="category" required>
                        <option value="" disabled selected>Selecione o segmento...</option>
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="document">CPF ou CNPJ</label>
                <input type="text" name="document" id="document" placeholder="000.000.000-00 ou 00.000.000/0000-00" inputmode="numeric" maxlength="18" data-mask="document" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Telefone</label>
                    <input type="tel" name="phone" id="phone" placeholder="(11) 99999-9999" maxlength="15" data-mask="phone" required>
                </div>

                <div class="form-group">
                    <label for="address">Endereço Físico da Loja</label>
                    <input type="text" name="address" id="address" placeholder="Av. Principal, 123" maxlength="255" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">Cidade</label>
                    <input type="text" name="city" id="city" placeholder="Ex: Cotia" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label for="state">Estado</label>
                    <select name="state" id="state" required>
                        <option value="" disabled selected>Selecione o estado...</option>
                        <?php foreach ($states as $uf): ?>
                            <option value="<?= e($uf) ?>"><?= e($uf) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-divider"><span>Acesso ao Sistema</span></div>

            <div class="form-group">
                <label for="email">E-mail Comercial</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" maxlength="255" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" name="password" id="password" placeholder="De 8 a 72 caracteres" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirmar Senha</label>
                    <input type="password" name="password_confirm" id="password_confirm" placeholder="Repita a senha" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 1rem;">Criar Minha Conta Profissional</button>
        </form>

        <footer class="auth-footer">
            <p>Já é parceiro? <a href="<?= e(url('merchant/login')) ?>">Entrar no painel</a></p>
        </footer>
    </div>

    <script src="/js/masks.js"></script>
</body>
</html>
