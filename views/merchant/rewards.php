<?php use App\Support\Csrf; ?>
<?php $title = 'Prêmios'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card rule-summary">
    <?php if ($pointsRule): ?>
        <p>Regra de pontos: a cada <strong><?= e(\App\Support\Money::format($pointsRule)) ?></strong> em compras, 1 ponto.</p>
    <?php else: ?>
        <p class="muted">Sem regra de pontos: no balcão, os pontos são digitados direto.</p>
    <?php endif; ?>
    <a href="<?= e(url('merchant/points-rule')) ?>" class="btn-secondary"><?= $pointsRule ? 'Alterar regra' : 'Definir regra de pontos' ?></a>
</section>

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
                                <div class="row-actions">
                                    <a href="<?= e(url('merchant/reward-edit', ['id' => (int)$reward['id']])) ?>" class="btn-secondary">Editar</a>
                                    <form action="<?= e(url('merchant/rewards')) ?>" method="POST">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>">
                                        <button type="submit" class="btn-secondary"><?= $reward['active'] ? 'Desativar' : 'Ativar' ?></button>
                                    </form>
                                    <form action="<?= e(url('merchant/rewards')) ?>" method="POST"
                                          onsubmit="return confirm('Excluir este prêmio? Se ele já foi resgatado, será só desativado.');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>">
                                        <button type="submit" class="btn-secondary btn-danger">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
