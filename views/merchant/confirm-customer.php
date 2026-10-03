<?php use App\Support\Csrf; ?>
<?php $title = 'Confirmar cliente'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <p class="muted">Este telefone já tem cadastro em outra loja do FidelityX.</p>
    <p class="confirm-name"><?= e($firstName) ?></p>
    <p class="muted confirm-phone"><?= e(format_phone($phone)) ?></p>

    <form action="<?= e(url('merchant/dashboard')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="phone" value="<?= e($phone) ?>">
        <button type="submit" class="btn-primary" autofocus>É <?= e($firstName) ?>, continuar</button>
    </form>

    <p class="form-back"><a href="<?= e(url('merchant/dashboard')) ?>">Não é essa pessoa, digitar outro telefone</a></p>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
