<?php $title = 'Olá, ' . strtok((string)($_SESSION['merchant_name'] ?? ''), ' '); ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card">
    <?php // busca por GET: o telefone so le dados; o que grava (confirmar, cadastrar, lancar) vai por POST com csrf ?>
    <form action="/index.php" method="GET" class="phone-search">
        <input type="hidden" name="url" value="merchant/dashboard">
        <div class="form-group">
            <label for="phone">Telefone do cliente</label>
            <input type="tel" name="phone" id="phone" inputmode="numeric" maxlength="15" data-mask="phone" autocomplete="off"
                   placeholder="(11) 99999-9999" required autofocus>
        </div>
        <button type="submit" class="btn-primary">Continuar</button>
    </form>
</section>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
