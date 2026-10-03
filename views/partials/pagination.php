<?php
// navegacao entre paginas. espera $paginator (App\Support\Paginator), $route e $query (filtros que a url mantem).
if ($paginator->pages <= 1) {
    return;
}
?>
<nav class="pagination" aria-label="Paginação">
    <?php if ($paginator->page > 1): ?>
        <a href="<?= e(url($route, $query + ['page' => $paginator->page - 1])) ?>" class="btn-secondary" rel="prev">Anterior</a>
    <?php else: ?>
        <span class="btn-secondary disabled" aria-disabled="true">Anterior</span>
    <?php endif; ?>

    <span class="muted">Página <?= $paginator->page ?> de <?= $paginator->pages ?></span>

    <?php if ($paginator->page < $paginator->pages): ?>
        <a href="<?= e(url($route, $query + ['page' => $paginator->page + 1])) ?>" class="btn-secondary" rel="next">Próxima</a>
    <?php else: ?>
        <span class="btn-secondary disabled" aria-disabled="true">Próxima</span>
    <?php endif; ?>
</nav>
