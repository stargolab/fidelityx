<?php use App\Support\Csrf; ?>
<?php $title = 'Cliente'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<?php // nome e saldo antes de tudo: um digito errado no telefone aparece aqui antes de qualquer lancamento ?>
<section class="card customer-summary">
    <div>
        <div class="customer-name"><?= e($card['customer_name']) ?></div>
        <div class="muted"><?= e(format_phone($card['phone'])) ?></div>
        <a href="<?= e(url('merchant/statement', ['phone' => $card['phone']])) ?>" class="customer-statement-link">Ver extrato</a>
    </div>
    <div class="customer-balance">
        <span class="customer-balance-value"><?= number_format((int)$card['current_points'], 0, ',', '.') ?></span>
        <span class="muted">pontos</span>
    </div>
</section>

<section class="card">
    <h2>Lançar pontos</h2>
    <form action="<?= e(url('merchant/customer')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="score">
        <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">

        <div class="form-group">
            <label for="points">Pontos</label>
            <input type="number" name="points" id="points" min="1" max="10000" inputmode="numeric"
                   placeholder="0" required autofocus>
            <div class="quick-points">
                <button type="button" class="btn-secondary" data-add="1">+1</button>
                <button type="button" class="btn-secondary" data-add="5">+5</button>
                <button type="button" class="btn-secondary" data-add="10">+10</button>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Descrição <span class="muted">(opcional)</span></label>
            <input type="text" name="description" id="description" placeholder="Compra" maxlength="255">
        </div>

        <button type="submit" class="btn-primary">Lançar pontos</button>
    </form>
</section>

<section class="card">
    <h2>Resgatar prêmio</h2>

    <?php if (!$rewards): ?>
        <p class="muted">Nenhum prêmio disponível para o saldo atual.</p>
    <?php else: ?>
        <ul class="reward-list">
            <?php foreach ($rewards as $reward): ?>
                <li>
                    <div>
                        <strong><?= e($reward['name']) ?></strong>
                        <div class="muted"><?= (int)$reward['points_cost'] ?> pontos</div>
                    </div>
                    <form action="<?= e(url('merchant/customer')) ?>" method="POST">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="redeem">
                        <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">
                        <input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>">
                        <button type="submit" class="btn-secondary">Resgatar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script>
    // atalhos +1/+5/+10 somam no campo de pontos (o lancamento continua pelo botao)
    document.querySelectorAll('[data-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById('points');
            input.value = (parseInt(input.value, 10) || 0) + parseInt(button.dataset.add, 10);
            input.focus();
        });
    });
</script>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
