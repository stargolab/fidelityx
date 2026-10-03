<?php use App\Support\Csrf; ?>
<?php $title = 'Editar prêmio'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <form action="<?= e(url('merchant/reward-edit')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="name">Nome</label>
                <input type="text" name="name" id="name" maxlength="120" value="<?= e($reward['name']) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="points_cost">Custo em pontos</label>
                <input type="number" name="points_cost" id="points_cost" min="1" max="1000000" inputmode="numeric"
                       value="<?= (int)$reward['points_cost'] ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Descrição <span class="muted">(opcional)</span></label>
            <input type="text" name="description" id="description" maxlength="255" value="<?= e((string)$reward['description']) ?>">
        </div>

        <p class="muted form-hint">Mudar o custo vale só para os próximos resgates; o histórico não muda.</p>

        <button type="submit" class="btn-primary btn-inline">Salvar prêmio</button>
    </form>

    <p class="form-back"><a href="<?= e(url('merchant/rewards')) ?>">Voltar para os prêmios</a></p>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
