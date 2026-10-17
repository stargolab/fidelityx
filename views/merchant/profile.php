<?php use App\Support\Csrf; ?>
<?php $title = 'Perfil'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<?php require __DIR__ . '/../partials/plan-usage.php'; ?>

<section class="card">
    <h2>Dados da loja</h2>

    <form action="<?= e(url('merchant/profile')) ?>" method="POST">
        <?= Csrf::field() ?>

        <div class="form-group">
            <label for="owner_name">Nome do responsável</label>
            <input type="text" name="owner_name" id="owner_name" maxlength="255" value="<?= e($profile['owner_name']) ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="shop_name">Nome da loja (fantasia)</label>
                <input type="text" name="shop_name" id="shop_name" maxlength="255" value="<?= e($profile['store_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="category">Segmento</label>
                <select name="category" id="category" required>
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $profile['category'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Telefone</label>
                <input type="tel" name="phone" id="phone" maxlength="15" data-mask="phone" value="<?= e(format_phone($profile['phone'])) ?>" required>
            </div>

            <div class="form-group">
                <label for="address">Endereço da loja</label>
                <input type="text" name="address" id="address" maxlength="255" value="<?= e($profile['address']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="city">Cidade</label>
                <input type="text" name="city" id="city" maxlength="100" value="<?= e($profile['city']) ?>" required>
            </div>

            <div class="form-group">
                <label for="state">Estado</label>
                <select name="state" id="state" required>
                    <?php foreach ($states as $uf): ?>
                        <option value="<?= e($uf) ?>"<?= $profile['state'] === $uf ? ' selected' : '' ?>><?= e($uf) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php // e-mail (login) e documento (identidade da conta) aparecem, mas nao sao editaveis nem enviados ?>
        <div class="form-row">
            <div class="form-group">
                <label for="email">E-mail de acesso</label>
                <input type="email" id="email" value="<?= e($profile['email']) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="document"><?= $profile['cnpj'] ? 'CNPJ' : 'CPF' ?></label>
                <input type="text" id="document" value="<?= e(format_document($profile['cnpj'] ?: $profile['cpf'])) ?>" disabled>
            </div>
        </div>
        <p class="muted form-hint">O e-mail de acesso e o documento não podem ser alterados por aqui.</p>

        <button type="submit" class="btn-primary btn-inline">Salvar dados</button>
    </form>
</section>

<section class="card">
    <h2>Trocar senha</h2>

    <form action="<?= e(url('merchant/profile')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="password">

        <div class="form-group">
            <label for="current_password">Senha atual</label>
            <input type="password" name="current_password" id="current_password" autocomplete="current-password" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="new_password">Nova senha</label>
                <input type="password" name="new_password" id="new_password" minlength="<?= (int)$minPassword ?>" maxlength="<?= (int)$maxPassword ?>" autocomplete="new-password" required>
            </div>

            <div class="form-group">
                <label for="new_password_confirm">Confirmar nova senha</label>
                <input type="password" name="new_password_confirm" id="new_password_confirm" minlength="<?= (int)$minPassword ?>" maxlength="<?= (int)$maxPassword ?>" autocomplete="new-password" required>
            </div>
        </div>
        <p class="muted form-hint">De <?= (int)$minPassword ?> a <?= (int)$maxPassword ?> caracteres, diferente da atual. Ao trocar, os outros aparelhos conectados nesta conta precisam entrar de novo.</p>

        <button type="submit" class="btn-primary btn-inline">Trocar senha</button>
    </form>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
