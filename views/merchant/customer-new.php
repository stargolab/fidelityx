<?php use App\Support\Csrf; ?>
<?php $title = 'Cliente novo'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <p class="muted">Nenhuma loja tem cadastro com este telefone.</p>
    <p class="customer-new-phone"><?= e(format_phone($phone)) ?></p>

    <form action="<?= e(url('merchant/customer-new')) ?>" method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="phone" value="<?= e($phone) ?>">

        <div class="form-group">
            <label for="name">Nome do cliente</label>
            <input type="text" name="name" id="name" placeholder="Ex: Maria Silva" maxlength="255"
                   autocomplete="off" required autofocus>
        </div>

        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="consent" value="1" required>
                <span>O cliente autorizou guardar o nome e o telefone para o programa de pontos.</span>
            </label>
        </div>

        <button type="submit" class="btn-primary">Cadastrar e continuar</button>
    </form>

    <p class="form-back"><a href="<?= e(url('merchant/dashboard')) ?>">Voltar e digitar outro telefone</a></p>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
