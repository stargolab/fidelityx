<?php $title = 'Clientes'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <?php // busca por GET (so le dados): nome ou telefone, mantida na paginacao ?>
    <form action="/index.php" method="GET" class="search-form">
        <input type="hidden" name="url" value="merchant/customers">
        <div class="form-group">
            <label for="q">Buscar por nome ou telefone</label>
            <input type="search" name="q" id="q" value="<?= e($search) ?>" maxlength="100" autocomplete="off" placeholder="Ex: Maria ou 99999">
        </div>
        <button type="submit" class="btn-secondary">Buscar</button>
        <?php if ($search !== ''): ?>
            <a href="<?= e(url('merchant/customers')) ?>" class="btn-secondary">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (!$customers): ?>
        <?php if ($search !== ''): ?>
            <p class="muted">Nenhum cliente encontrado para essa busca.</p>
        <?php else: ?>
            <p class="muted">Nenhum cliente ainda. Os clientes aparecem aqui quando recebem pontos pela primeira vez.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted list-count"><?= $paginator->total ?> <?= $paginator->total === 1 ? 'cliente' : 'clientes' ?></p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Cliente</th><th>Telefone</th><th class="num">Saldo</th><th class="num">Total acumulado</th><th>Última visita</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><a href="<?= e(url('merchant/customer', ['phone' => $customer['phone']])) ?>"><?= e($customer['name']) ?></a></td>
                            <td><?= e(format_phone($customer['phone'])) ?></td>
                            <td class="num"><?= (int)$customer['current_points'] ?></td>
                            <td class="num"><?= (int)$customer['total_accumulated'] ?></td>
                            <td><?= e(format_datetime($customer['last_use_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $route = 'merchant/customers';
        $query = $search === '' ? [] : ['q' => $search];
        require __DIR__ . '/../partials/pagination.php';
        ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
