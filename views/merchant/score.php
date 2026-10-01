<?php use App\Support\Csrf; ?>
<?php $title = 'Lançar pontos'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <form action="<?= e(url('merchant/score')) ?>" method="POST">
        <?= Csrf::field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Telefone do cliente</label>
                <input type="tel" name="phone" id="phone" placeholder="(11) 99999-9999"
                       value="<?= e(format_phone($_GET['phone'] ?? '')) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="name">Nome <span class="muted">(só para cliente novo)</span></label>
                <input type="text" name="name" id="name" placeholder="Ex: Maria Silva" maxlength="255">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="points">Pontos</label>
                <input type="number" name="points" id="points" min="1" max="10000" value="1" required>
            </div>

            <div class="form-group">
                <label for="description">Descrição <span class="muted">(opcional)</span></label>
                <input type="text" name="description" id="description" placeholder="Compra" maxlength="255">
            </div>
        </div>

        <button type="submit" class="btn-primary btn-inline">Lançar pontos</button>
    </form>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
