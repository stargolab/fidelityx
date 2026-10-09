<?php use App\Support\Csrf; ?>
<?php $title = 'Confirme seu e-mail'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <p>Enviamos um link de confirmação para <strong><?= e($email) ?></strong>. Abra o e-mail e clique no link
        para liberar o painel (ele vale por 24 horas).</p>
    <p class="muted">Não chegou? Confira a caixa de spam ou peça outro link. O link anterior deixa de valer.</p>

    <form action="<?= e(url('merchant/confirm-email')) ?>" method="POST">
        <?= Csrf::field() ?>
        <button type="submit" class="btn-secondary">Reenviar link</button>
    </form>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
