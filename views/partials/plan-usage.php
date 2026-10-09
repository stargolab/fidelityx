<?php
// cartao "Plano": qual e o plano da loja e quanto dos limites ja foi usado (task 32).
// espera $planUsage (MerchantController::planUsage).
$planFull = array_filter($planUsage['items'], fn($item) => $item['full']);
?>
<section class="card plan-usage">
    <h2>Plano <?= e($planUsage['label']) ?></h2>

    <?php foreach ($planUsage['items'] as $item): ?>
        <div class="plan-usage-item">
            <?php if ($item['limit'] === null): ?>
                <p><strong><?= number_format($item['used'], 0, ',', '.') ?></strong> <?= e($item['label']) ?> <span class="muted">(sem limite)</span></p>
            <?php else: ?>
                <p>
                    <strong><?= number_format($item['used'], 0, ',', '.') ?></strong>
                    de <?= number_format($item['limit'], 0, ',', '.') ?> <?= e($item['label']) ?>
                </p>
                <div class="progress" role="progressbar" aria-label="Uso do limite de <?= e($item['label']) ?>"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$item['percent'] ?>">
                    <?php // largura por classe, em passos de 5% (atributo style seria bloqueado pela CSP, task 48) ?>
                    <div class="progress-bar progress-w-<?= (int)(round($item['percent'] / 5) * 5) ?>"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($planFull): ?>
        <p class="alert alert-warning plan-usage-note" role="note">
            Você chegou ao limite de <?= e(implode(' e de ', array_column($planFull, 'label'))) ?> do plano <?= e($planUsage['label']) ?>.
            O que já está cadastrado continua funcionando. Para ter mais, fale com o suporte e peça o plano Pro.
        </p>
    <?php elseif ($planUsage['plan'] === \App\Support\Plan::FREE): ?>
        <p class="muted plan-usage-note">Precisa de mais? Fale com o suporte e peça o plano Pro, que não tem limite.</p>
    <?php endif; ?>
</section>
