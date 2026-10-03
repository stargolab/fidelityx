<?php $title = 'Relatórios'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<div class="stats">
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['customers'], 0, ',', '.') ?></div>
        <div class="stat-label">Clientes</div>
    </div>
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['points_issued'], 0, ',', '.') ?></div>
        <div class="stat-label">Pontos emitidos</div>
    </div>
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['redemptions'], 0, ',', '.') ?></div>
        <div class="stat-label">Resgates</div>
    </div>
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['points_balance'], 0, ',', '.') ?></div>
        <div class="stat-label">Pontos em circulação</div>
    </div>
</div>

<section class="card">
    <h2>Histórico de movimentações</h2>

    <?php if (!$entries): ?>
        <p class="muted">Nenhuma movimentação ainda. Os pontos lançados e os resgates aparecem aqui.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Data</th><th>Cliente</th><th>Tipo</th><th class="num">Pontos</th><th>Descrição</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e(format_datetime($entry['created_at'])) ?></td>
                            <td><a href="<?= e(url('merchant/statement', ['phone' => $entry['phone']])) ?>"><?= e($entry['customer_name']) ?></a></td>
                            <td>
                                <?php if ($entry['type'] === 'earn'): ?>
                                    <span class="badge badge-earn">Ganhou</span>
                                <?php else: ?>
                                    <span class="badge badge-redeem">Resgatou</span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $entry['type'] === 'earn' ? '+' : '−' ?><?= (int)$entry['quantity'] ?></td>
                            <td><?= e($entry['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $route = 'merchant/reports';
        $query = [];
        require __DIR__ . '/../partials/pagination.php';
        ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
