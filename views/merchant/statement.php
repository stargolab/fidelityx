<?php $title = 'Extrato'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card customer-summary">
    <div>
        <div class="customer-name"><?= e($card['customer_name']) ?></div>
        <div class="muted"><?= e(format_phone($card['phone'])) ?></div>
    </div>
    <div class="customer-balance">
        <span class="customer-balance-value"><?= number_format((int)$card['current_points'], 0, ',', '.') ?></span>
        <span class="muted">pontos</span>
    </div>
</section>

<section class="card">
    <h2>Movimentações</h2>

    <?php if (!$entries): ?>
        <p class="muted">Este cliente ainda não tem movimentações.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Data</th><th>Tipo</th><th class="num">Pontos</th><th>Descrição</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e(format_datetime($entry['created_at'])) ?></td>
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
        $route = 'merchant/statement';
        $query = ['phone' => $card['phone']];
        require __DIR__ . '/../partials/pagination.php';
        ?>
    <?php endif; ?>

    <p class="form-back"><a href="<?= e(url('merchant/customer', ['phone' => $card['phone']])) ?>">Voltar para a tela do cliente</a></p>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
