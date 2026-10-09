<?php
// validade do saldo (task 31). espera $card com expires_at (LoyaltyCardModel::findByMerchantAndPhone).
// sem data nao mostra nada: cartao sem saldo ou loja em que os pontos nao vencem.
if (empty($card['expires_at'])) {
    return;
}
// data ja passada: o saldo venceu e sai na proxima rodada do bin/expire-points.php
$pointsExpired = strtotime($card['expires_at']) <= time();
?>
<p class="muted points-expiry">
    <?php if ($pointsExpired): ?>
        Pontos vencidos em <strong><?= e(format_date($card['expires_at'])) ?></strong> por falta de movimentação.
    <?php else: ?>
        Pontos válidos até <strong><?= e(format_date($card['expires_at'])) ?></strong>. Cada compra ou resgate renova o prazo.
    <?php endif; ?>
</p>
