<?php $title = 'Olá, ' . strtok((string)($_SESSION['merchant_name'] ?? ''), ' '); ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<?php if (!empty($steps)): ?>
    <?php // guia de primeiros passos: aparece enquanto faltar algum passo e some quando todos estiverem feitos ?>
    <section class="card onboarding" aria-labelledby="onboarding-title">
        <h2 id="onboarding-title">Primeiros passos</h2>
        <ol class="steps">
            <?php foreach ($steps as $step): ?>
                <li class="step<?= $step['done'] ? ' step-done' : '' ?>">
                    <span class="step-mark" aria-hidden="true"><?= $step['done'] ? '✓' : '' ?></span>
                    <div class="step-text">
                        <strong><?= e($step['label']) ?></strong><?php if ($step['done']): ?> <span class="visually-hidden">(feito)</span><?php elseif (!empty($step['optional'])): ?> <span class="muted">(opcional)</span><?php endif; ?>
                        <?php if (!$step['done']): ?>
                            <div class="muted"><?= e($step['hint']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$step['done']): ?>
                        <a href="<?= e($step['url']) ?>" class="btn-secondary"><?= e($step['cta']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>

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
