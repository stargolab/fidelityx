<?php $title = 'Olá, ' . strtok((string)($_SESSION['merchant_name'] ?? ''), ' '); ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="stats">
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['customers'], 0, ',', '.') ?></div>
        <div class="stat-label">Clientes fidelizados</div>
    </div>
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['points_issued'], 0, ',', '.') ?></div>
        <div class="stat-label">Pontos concedidos</div>
    </div>
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['redemptions'], 0, ',', '.') ?></div>
        <div class="stat-label">Prêmios resgatados</div>
    </div>
</section>

<section class="card">
    <h2>Últimas movimentações</h2>

    <?php if (!$recent): ?>
        <p class="muted">Nenhuma movimentação ainda. Elas aparecem aqui quando um cliente recebe pontos.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Data</th><th>Cliente</th><th>Tipo</th><th>Descrição</th><th class="num">Pontos</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e(format_datetime($row['created_at'])) ?></td>
                            <td><?= e($row['customer_name']) ?><br><span class="muted"><?= e(format_phone($row['phone'])) ?></span></td>
                            <td>
                                <?php if ($row['type'] === 'earn'): ?>
                                    <span class="badge badge-earn">Ganho</span>
                                <?php else: ?>
                                    <span class="badge badge-redeem">Resgate</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['description']) ?></td>
                            <td class="num"><?= $row['type'] === 'earn' ? '+' : '−' ?><?= (int)$row['quantity'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
