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
              onsubmit="return confirm('Remover a regra? No balcão, os pontos voltam a ser digitados direto.');">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn-secondary btn-danger">Remover regra</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
