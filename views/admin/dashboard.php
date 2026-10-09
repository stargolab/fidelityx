<?php use App\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Lojas | Administração FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <nav class="app-nav admin-nav">
        <span class="nav-logo"><img src="/assets/fidelityx-logo.svg" alt="FidelityX"></span>
        <span class="nav-store">Administração · <?= e($_SESSION['admin_name'] ?? '') ?></span>
        <form action="<?= e(url('admin/logout')) ?>" method="POST" class="nav-logout">
            <?= Csrf::field() ?>
            <button type="submit">Sair</button>
        </form>
    </nav>

    <main class="app-main">
        <h1>Lojas</h1>
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <section class="card">
            <?php if (!$merchants): ?>
                <p class="muted">Nenhuma loja cadastrada.</p>
            <?php else: ?>
                <p class="muted list-count"><?= $paginator->total ?> <?= $paginator->total === 1 ? 'loja' : 'lojas' ?></p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Loja</th><th>Responsável</th><th>CPF/CNPJ</th><th>E-mail</th><th class="num">Clientes</th><th>Cadastro</th><th>Situação</th><th><span class="visually-hidden">Ações</span></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($merchants as $merchant): ?>
                                <?php $active = $merchant['status'] === 'active'; ?>
                                <tr>
                                    <td><?= e($merchant['store_name']) ?></td>
                                    <td><?= e($merchant['owner_name']) ?></td>
                                    <td><?= e(format_document($merchant['cnpj'] ?: $merchant['cpf'])) ?></td>
                                    <td><?= e($merchant['email']) ?></td>
                                    <td class="num"><?= (int)$merchant['customers'] ?></td>
                                    <td><?= e(format_datetime($merchant['created_at'])) ?></td>
                                    <td><span class="badge <?= $active ? 'badge-earn' : 'badge-reversal' ?>"><?= $active ? 'Ativa' : 'Desativada' ?></span></td>
                                    <td class="num">
                                        <form action="<?= e(url('admin/dashboard')) ?>" method="POST"
                                              data-confirm="<?= e($active ? 'Desativar ' . $merchant['store_name'] . '? O lojista perde o acesso na hora.' : 'Ativar ' . $merchant['store_name'] . '?') ?>">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="merchant_id" value="<?= (int)$merchant['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $active ? 'inactive' : 'active' ?>">
                                            <input type="hidden" name="page" value="<?= (int)$paginator->page ?>">
                                            <button type="submit" class="btn-secondary<?= $active ? ' btn-danger' : '' ?>"><?= $active ? 'Desativar' : 'Ativar' ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $route = 'admin/dashboard';
                $query = [];
                require __DIR__ . '/../partials/pagination.php';
                ?>
            <?php endif; ?>
        </section>
    </main>
    <script src="/js/forms.js"></script>
</body>
</html>
