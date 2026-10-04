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
                    <tr><th>Data</th><th>Tipo</th><th class="num">Pontos</th><th>Descrição</th><th><span class="visually-hidden">Ações</span></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e(format_datetime($entry['created_at'])) ?></td>
                            <?php [$typeLabel, $typeBadge, $typeSign] = log_type_view($entry['type']); ?>
                            <td><span class="badge <?= $typeBadge ?>"><?= e($typeLabel) ?></span></td>
                            <td class="num"><?= $typeSign ?><?= (int)$entry['quantity'] ?></td>
                            <td><?= e($entry['description']) ?></td>
                            <td class="num">
                                <?php if ($entry['can_reverse'] ?? false): ?>
                                    <form action="<?= e(url('merchant/customer')) ?>" method="POST"
                                          onsubmit="return confirm('Estornar este lançamento? Os pontos saem do saldo do cliente.');">
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="action" value="reverse">
                                        <input type="hidden" name="back" value="statement">
                                        <input type="hidden" name="phone" value="<?= e($card['phone']) ?>">
                                        <input type="hidden" name="log_id" value="<?= (int)$entry['id'] ?>">
                                        <button type="submit" class="btn-secondary btn-danger">Estornar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
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
