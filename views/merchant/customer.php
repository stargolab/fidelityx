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

<?php if ($launch): ?>
    <?php // confirmacao do lancamento: novo saldo, quanto falta e Desfazer por alguns segundos (task 5) ?>
    <section class="card launch-confirm" role="status">
        <p class="launch-confirm-title">
            <strong>+<?= number_format((int)$launch['quantity'], 0, ',', '.') ?> <?= (int)$launch['quantity'] === 1 ? 'ponto lançado' : 'pontos lançados' ?></strong>
            para <?= e(strtok((string)$card['customer_name'], ' ')) ?>.
            Saldo agora: <strong><?= number_format((int)$card['current_points'], 0, ',', '.') ?> pontos</strong>.
        </p>
        <?php if ($progress && !$progress['all_available']): ?>
            <p class="muted">Faltam <?= number_format($progress['missing'], 0, ',', '.') ?> para <?= e($progress['reward']['name']) ?>.</p>
        <?php elseif ($progress): ?>
            <p class="muted">Já dá para resgatar qualquer prêmio.</p>
        <?php endif; ?>
        <form action="<?= e(url('merchant/customer')) ?>" method="POST" class="launch-undo" data-undo-seconds="<?= (int)$undoSeconds ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="reverse">
            <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">
            <input type="hidden" name="log_id" value="<?= (int)$launch['id'] ?>">
            <button type="submit" class="btn-secondary">Desfazer <span class="launch-undo-timer"></span></button>
        </form>
    </section>
<?php endif; ?>

<?php if ($card['consent_at'] === null): ?>
    <?php // cadastro anterior ao registro de consentimento (migration 003): pergunta ao cliente e grava ?>
    <section class="card consent-pending">
        <h2>Consentimento não registrado</h2>
        <p class="muted">Este cliente foi cadastrado antes de o sistema guardar o consentimento. Pergunte se ele autoriza e registre.</p>
        <form action="<?= e(url('merchant/customer')) ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="consent">
            <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">
            <div class="form-group">
                <label class="checkbox">
                    <input type="checkbox" name="consent" value="1" required>
                    <span>O cliente autorizou esta loja a guardar o nome e o telefone dele para o programa de pontos
                        (<a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener">política de privacidade</a>).</span>
                </label>
            </div>
            <button type="submit" class="btn-secondary">Registrar consentimento</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($progress): ?>
    <section class="card">
        <?php require __DIR__ . '/../partials/reward-progress.php'; ?>
    </section>
<?php endif; ?>

<section class="card">
    <h2>Lançar pontos</h2>
    <form action="<?= e(url('merchant/customer')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="score">
        <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">

        <?php if ($pointsRule): ?>
            <?php // com regra: o lojista digita o valor e ve os pontos antes de confirmar (o servidor recalcula) ?>
            <div class="form-group">
                <label for="amount">Valor da compra (R$)</label>
                <input type="text" name="amount" id="amount" inputmode="decimal" autocomplete="off"
                       placeholder="0,00" maxlength="15" data-rule-cents="<?= (int)$pointsRule ?>" autofocus>
                <p class="muted amount-preview" id="amount-preview" aria-live="polite">
                    A cada <?= e(\App\Support\Money::format($pointsRule)) ?> em compras, 1 ponto (arredonda pra baixo).
                </p>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="points">Pontos<?php if ($pointsRule): ?> <span class="muted">(ou digite direto)</span><?php endif; ?></label>
            <input type="number" name="points" id="points" min="1" max="10000" inputmode="numeric"
                   placeholder="0"<?= $pointsRule ? '' : ' required autofocus' ?>>
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

    <?php if (!$pointsRule): ?>
        <p class="form-back"><a href="<?= e(url('merchant/points-rule')) ?>">Calcular os pontos pelo valor da compra</a></p>
    <?php endif; ?>
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

<?php // exclusao a pedido do cliente (LGPD): escondida num <details> pra nao ser clicada por engano no balcao ?>
<details class="card danger-zone">
    <summary>Excluir dados do cliente</summary>
    <p class="muted">Use quando o cliente pedir. Apaga o nome e o telefone dele nesta loja e zera o saldo.
        As movimentações continuam nos relatórios, sem identificação. Não dá para desfazer.</p>
    <form action="<?= e(url('merchant/customer')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="anonymize">
        <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">
        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="confirm" value="1" required>
                <span>O cliente pediu a exclusão dos dados dele.</span>
            </label>
        </div>
        <button type="submit" class="btn-secondary btn-danger">Excluir dados</button>
    </form>
</details>

<script>
    // Desfazer da confirmacao some depois de alguns segundos (depois disso, o estorno e pelo extrato)
    document.querySelectorAll('.launch-undo').forEach(function (form) {
        var left = parseInt(form.dataset.undoSeconds, 10);
        var timer = form.querySelector('.launch-undo-timer');
        var tick = function () {
            if (left <= 0) {
                form.remove();
                return;
            }
            timer.textContent = '(' + left + 's)';
            left--;
            setTimeout(tick, 1000);
        };
        tick();
    });

    // valor da compra -> previa dos pontos pela regra (a mesma conta do servidor: centavos, arredonda pra baixo).
    // digitar os pontos na mao apaga o valor, pra nao lancar uma coisa achando que e outra.
    (function () {
        var amount = document.getElementById('amount');
        if (!amount) return;
        var points = document.getElementById('points');
        var preview = document.getElementById('amount-preview');
        var rule = parseInt(amount.dataset.ruleCents, 10);
        var original = preview.textContent;

        var toCents = function (text) {
            var v = text.replace(/[^\d,.]/g, '');
            var intPart, dec = '';
            if (v === '' || (v.match(/,/g) || []).length > 1) return null;
            if (v.indexOf(',') >= 0) {
                v = v.replace(/\./g, '');
                intPart = v.split(',')[0];
                dec = v.split(',')[1];
            } else {
                var parts = v.split('.');
                if (parts.length > 1 && parts[parts.length - 1].length <= 2) dec = parts.pop();
                intPart = parts.join('');
            }
            if ((intPart === '' && dec === '') || dec.length > 2 || intPart.length > 9) return null;
            return parseInt(intPart || '0', 10) * 100 + parseInt((dec + '00').slice(0, 2), 10);
        };

        amount.addEventListener('input', function () {
            var cents = toCents(amount.value);
            if (cents === null) {
                points.value = '';
                preview.textContent = amount.value.trim() === '' ? original : 'Valor inválido.';
                return;
            }
            var total = Math.floor(cents / rule);
            points.value = total > 0 ? total : '';
            preview.textContent = total > 0
                ? '= ' + total + (total === 1 ? ' ponto' : ' pontos')
                : 'Valor abaixo de 1 ponto.';
        });

        points.addEventListener('input', function () {
            amount.value = '';
            preview.textContent = original;
        });
    })();

    // atalhos +1/+5/+10 somam no campo de pontos (o lancamento continua pelo botao)
    document.querySelectorAll('[data-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById('points');
            input.value = (parseInt(input.value, 10) || 0) + parseInt(button.dataset.add, 10);
            // avisa que os pontos foram digitados (com regra, isso limpa o valor da compra)
            input.dispatchEvent(new Event('input'));
            input.focus();
        });
    });
</script>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
