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
        <div class="progress-bar" style="width: <?= (int)$progress['percent'] ?>%"></div>
    </div>
</div>
