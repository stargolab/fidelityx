<?php

namespace App\Support;

// conta de paginacao: dado o total de itens e a pagina pedida (?page=), diz a pagina valida e o offset.
// pagina fora do intervalo (0, negativa, letra, alem da ultima) cai na mais proxima valida.
final class Paginator {
    public readonly int $total;
    public readonly int $perPage;
    public readonly int $pages;
    public readonly int $page;

    public function __construct(int $total, mixed $requestedPage, int $perPage = 20) {
        $this->total   = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->pages   = max(1, (int)ceil($this->total / $this->perPage));

        $page = filter_var($requestedPage, FILTER_VALIDATE_INT);
        $this->page = min($this->pages, max(1, $page === false ? 1 : $page));
    }

    public function offset(): int {
        return ($this->page - 1) * $this->perPage;
    }
}
