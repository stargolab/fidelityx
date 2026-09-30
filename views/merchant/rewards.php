<?php use App\Support\Csrf; ?>
<?php $title = 'Prêmios'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <h2>Novo prêmio</h2>
    <form action="<?= e(url('merchant/rewards')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">

        <div class="form-row">
            <div class="form-group">
                <label for="name">Nome</label>
                <input type="text" name="name" id="name" placeholder="Ex: Café grátis" maxlength="120" required>
            </div>

            <div class="form-group">
                <label for="points_cost">Custo em pontos</label>
                <input type="number" name="points_cost" id="points_cost" min="1" max="1000000" placeholder="10" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Descrição <span class="muted">(opcional)</span></label>
            <input type="text" name="description" id="description" maxlength="255">
        </div>

        <button type="submit" class="btn-primary btn-inline">Cadastrar prêmio</button>
    </form>
</section>

<section class="card">
    <h2>Catálogo</h2>

    <?php if (!$rewards): ?>
        <p class="muted">Nenhum prêmio cadastrado ainda.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Prêmio</th><th class="num">Custo</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rewards as $reward): ?>
                        <tr>
                            <td>
                                <?= e($reward['name']) ?>
                                <?php if ($reward['description']): ?><br><span class="muted"><?= e($reward['description']) ?></span><?php endif; ?>
                            </td>
                            <td class="num"><?= (int)$reward['points_cost'] ?> pts</td>
                            <td><?= $reward['active'] ? 'Ativo' : '<span class="muted">Inativo</span>' ?></td>
                            <td class="num">
                                <form action="<?= e(url('merchant/rewards')) ?>" method="POST">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>">
                                    <button type="submit" class="btn-secondary"><?= $reward['active'] ? 'Desativar' : 'Ativar' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
