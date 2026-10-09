<?php $title = 'Cartaz de pontos'; ?>
<?php require __DIR__ . '/../partials/merchant-header.php'; ?>

<section class="card no-print">
    <p class="muted poster-help">Imprima e deixe no balcão: o cliente aponta a câmera do celular e vê os pontos dele, sem instalar nada.
        Na janela de impressão, escolha "Salvar como PDF" se quiser o arquivo.</p>
    <button type="button" class="btn-primary btn-inline" data-print>Imprimir ou salvar em PDF</button>
</section>

<?php // a folha: fundo claro e texto escuro tambem na tela, porque o qr code so le bem escuro sobre claro ?>
<article class="poster">
    <h2 class="poster-store"><?= e($_SESSION['store_name'] ?? '') ?></h2>
    <p class="poster-lead">Ganhe pontos e troque por prêmios</p>

    <div class="poster-qr" role="img" aria-label="QR code para consultar seus pontos">
        <?= $qrSvg /* svg gerado pelo servidor (QrSvg), sem conteudo do usuario */ ?>
    </div>

    <p class="poster-cta">Aponte a câmera do celular e consulte seus pontos</p>
    <p class="poster-code">Código da loja: <strong><?= e($publicCode) ?></strong></p>
    <p class="poster-url"><?= e($balanceUrl) ?></p>
</article>

<?php require __DIR__ . '/../partials/merchant-footer.php'; ?>
