<?php use App\Support\Csrf; ?>
<?php $title = 'Resgatar prêmio'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <h2>1. Buscar cliente</h2>
    <form action="/index.php" method="GET" class="form-row">
        <input type="hidden" name="url" value="merchant/redeem">
        <div class="form-group">
            <label for="lookup_phone">Telefone do cliente</label>
            <input type="tel" name="phone" id="lookup_phone" placeholder="(11) 99999-9999" value="<?= e(format_phone($phone)) ?>" required>
        </div>
        <div class="form-group" style="align-self: end;">
            <button type="submit" class="btn-secondary" style="padding: 0.75rem 1rem;">Buscar</button>
        </div>
    </form>
</section>

<?php if ($phone !== '' && !$card && !isset($_GET['error'])): ?>
    <p class="alert alert-error">Nenhum cliente com esse telefone nesta loja.</p>
<?php elseif ($card): ?>
    <section class="card">
        <h2>2. Escolher prêmio</h2>
        <p style="margin-bottom: 1rem;">
            <strong><?= e($card['customer_name']) ?></strong> tem
            <strong><?= (int)$card['current_points'] ?> pontos</strong>.
        </p>

        <?php if (!$rewards): ?>
            <p class="muted">Nenhum prêmio ativo. <a href="<?= e(url('merchant/rewards')) ?>">Cadastre um prêmio</a>.</p>
        <?php else: ?>
            <form action="<?= e(url('merchant/redeem')) ?>" method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="phone" value="<?= e($phone) ?>">

                <div class="form-group">
                    <label for="reward_id">Prêmio</label>
                    <select name="reward_id" id="reward_id" required>
                        <?php foreach ($rewards as $reward): ?>
                            <?php $affordable = (int)$card['current_points'] >= (int)$reward['points_cost']; ?>
                            <option value="<?= (int)$reward['id'] ?>" <?= $affordable ? '' : 'disabled' ?>>
                                <?= e($reward['name']) ?> — <?= (int)$reward['points_cost'] ?> pts<?= $affordable ? '' : ' (saldo insuficiente)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary btn-inline">Confirmar resgate</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
