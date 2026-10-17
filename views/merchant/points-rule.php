<?php use App\Support\Csrf; ?>
<?php use App\Support\Money; ?>
<?php $title = 'Regra de pontos'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <p class="muted rule-help">Com a regra, no balcão você digita o valor da compra e o sistema calcula os pontos, sempre arredondando para baixo. Lançar pontos direto continua possível.</p>

    <?php if ($ruleCents): ?>
        <p class="rule-current">Regra atual: a cada <strong><?= e(Money::format($ruleCents)) ?></strong> em compras, <strong>1 ponto</strong>.</p>
    <?php endif; ?>

    <form action="<?= e(url('merchant/points-rule')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save">

        <div class="form-group">
            <label for="rule">A cada quantos reais em compras o cliente ganha 1 ponto?</label>
            <input type="text" name="rule" id="rule" inputmode="decimal" autocomplete="off" maxlength="15"
                   placeholder="1,00" value="<?= $ruleCents ? e(number_format($ruleCents / 100, 2, ',', '.')) : '' ?>" required autofocus>
            <p class="muted rule-example">Ex.: com R$ 1,00, uma compra de R$ 12,90 dá 12 pontos. Com R$ 5,00, dá 2 pontos.</p>
        </div>

        <button type="submit" class="btn-primary btn-inline">Salvar regra</button>
    </form>

    <?php if ($ruleCents): ?>
        <form action="<?= e(url('merchant/points-rule')) ?>" method="POST" class="rule-clear"
              data-confirm="Remover a regra? No balcão, os pontos voltam a ser digitados direto.">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn-secondary btn-danger">Remover regra</button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Validade dos pontos</h2>
    <p class="muted rule-help">O saldo do cliente vence quando ele fica esse tempo sem nenhuma movimentação na sua loja (ganhar, resgatar ou estornar pontos). Cada movimentação renova o prazo do saldo inteiro.</p>

    <p class="rule-current">
        <?php if ($expiryMonths): ?>
            Validade atual: <strong><?= (int)$expiryMonths ?> meses</strong> sem movimentação.
        <?php else: ?>
            Validade atual: <strong>os pontos não vencem</strong>.
        <?php endif; ?>
    </p>

    <form action="<?= e(url('merchant/points-rule')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="expiry">

        <div class="form-group">
            <label for="expiry_months">Os pontos vencem depois de quanto tempo sem movimentação?</label>
            <select name="expiry_months" id="expiry_months" required>
                <?php foreach ($expiryOptions as $months): ?>
                    <option value="<?= (int)$months ?>"<?= $expiryMonths === $months ? ' selected' : '' ?>><?= (int)$months ?> meses</option>
                <?php endforeach; ?>
                <option value="never"<?= $expiryMonths === null ? ' selected' : '' ?>>Não vencem</option>
            </select>
            <p class="muted rule-example">A mudança vale para todos os clientes da loja, inclusive para o saldo que eles já têm.</p>
        </div>

        <button type="submit" class="btn-primary btn-inline">Salvar validade</button>
    </form>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
