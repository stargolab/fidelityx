<?php $title = 'Relatórios'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>
<?php use App\Support\Period; ?>

<?php // periodo (task 59): GET, so le dados. as datas valem quando "Escolher datas" esta marcado ?>
<section class="card">
    <form action="/index.php" method="GET" class="period-form">
        <input type="hidden" name="url" value="merchant/reports">
        <div class="form-group">
            <label for="periodo">Período</label>
            <select name="periodo" id="periodo">
                <?php foreach (Period::OPTIONS as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $period->key === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="de">De</label>
            <input type="date" name="de" id="de" value="<?= e($period->start) ?>">
        </div>
        <div class="form-group">
            <label for="ate">Até</label>
            <input type="date" name="ate" id="ate" value="<?= e($period->end) ?>">
        </div>
        <button type="submit" class="btn-secondary">Filtrar</button>
    </form>
    <?php if ($period->invalid): ?>
        <p class="alert alert-error" role="alert">Datas inválidas: escolha o dia inicial e o final (o inicial antes do final). Mostrando desde o início.</p>
    <?php elseif ($period->isFiltered()): ?>
        <p class="muted">De <?= e(date('d/m/Y', strtotime($period->start))) ?> a <?= e(date('d/m/Y', strtotime($period->end))) ?>. O saldo em circulação é sempre o de agora.</p>
    <?php endif; ?>
</section>

<div class="stats">
    <div class="card stat">
        <div class="stat-value"><?= number_format((int)$stats['customers'], 0, ',', '.') ?></div>
        <div class="stat-label"><?= $period->isFiltered() ? 'Clientes novos' : 'Clientes' ?></div>
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
    <p class="form-back"><a href="<?= e(url('merchant/export', ['tipo' => 'historico'] + $period->query())) ?>" download>Baixar planilha (CSV) do período</a></p>

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
                            <td>
                                <?php if ($entry['phone'] === null): ?>
                                    <span class="muted">Cliente excluído</span>
                                <?php else: ?>
                                    <a href="<?= e(url('merchant/statement', ['phone' => $entry['phone']])) ?>"><?= e($entry['customer_name']) ?></a>
                                <?php endif; ?>
                            </td>
                            <?php [$typeLabel, $typeBadge, $typeSign] = log_type_view($entry['type'], $entry['reversed_type'] ?? null); ?>
                            <td><span class="badge <?= $typeBadge ?>"><?= e($typeLabel) ?></span></td>
                            <td class="num"><?= $typeSign ?><?= (int)$entry['quantity'] ?></td>
                            <td><?= e($entry['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $route = 'merchant/reports';
        $query = $period->query();
        require __DIR__ . '/../partials/pagination.php';
        ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
