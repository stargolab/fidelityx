<?php
// barra e frase de progresso ate o proximo premio. espera $progress (App\Support\RewardProgress::next) ou null.
if (!$progress) {
    return;
}
?>
<div class="reward-progress">
    <?php if ($progress['all_available']): ?>
        <p class="reward-progress-text">Já dá para resgatar qualquer prêmio!</p>
    <?php else: ?>
        <p class="reward-progress-text">
            <?= $progress['missing'] === 1 ? 'Falta' : 'Faltam' ?>
            <strong><?= number_format($progress['missing'], 0, ',', '.') ?> <?= $progress['missing'] === 1 ? 'ponto' : 'pontos' ?></strong>
            para <strong><?= e($progress['reward']['name']) ?></strong>
        </p>
    <?php endif; ?>
    <div class="progress" role="progressbar" aria-label="Progresso até o próximo prêmio"
         aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$progress['percent'] ?>">
        <?php // largura por classe, em passos de 5% (atributo style seria bloqueado pela CSP, task 48) ?>
        <div class="progress-bar progress-w-<?= (int)(round(max(0, min(100, (int)$progress['percent'])) / 5) * 5) ?>"></div>
    </div>
</div>
