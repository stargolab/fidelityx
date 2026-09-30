<?php $title = 'Clientes'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <?php if (!$customers): ?>
        <p class="muted">Nenhum cliente ainda. Os clientes aparecem aqui quando recebem pontos pela primeira vez.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Cliente</th><th>Telefone</th><th class="num">Saldo</th><th class="num">Total acumulado</th><th>Última visita</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= e($customer['name']) ?></td>
                            <td><?= e(format_phone($customer['phone'])) ?></td>
                            <td class="num"><?= (int)$customer['current_points'] ?></td>
                            <td class="num"><?= (int)$customer['total_accumulated'] ?></td>
                            <td><?= e(format_datetime($customer['last_use_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
