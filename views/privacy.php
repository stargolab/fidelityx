<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacidade | FidelityX</title>

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/home.css">
</head>
<body class="home-page">
    <header class="home-header">
        <a href="<?= e(url('home')) ?>" class="home-logo"><img src="/assets/fidelityx-logo.svg" alt="FidelityX"></a>
        <a href="<?= e(url('customer/balance')) ?>" class="home-login">Consultar pontos</a>
    </header>

    <main class="privacy">
        <?php // TODO juridico: texto escrito pelo time de produto, precisa de revisao antes de ir a producao ?>
        <div class="alert alert-warning" role="note">
            Rascunho pendente de revisão jurídica. Não usar em produção sem aprovação.
        </div>

        <h1>Política de privacidade</h1>
        <p class="muted">Versão <?= e($version) ?></p>

        <h2>Quem cuida dos seus dados</h2>
        <p>Cada loja que usa o FidelityX é responsável pelos dados do programa de pontos dela. O FidelityX é a plataforma que guarda e processa esses dados em nome da loja.</p>

        <h2>Quais dados</h2>
        <ul>
            <li><strong>Telefone</strong>: identifica você no programa de pontos.</li>
            <li><strong>Nome</strong>: o nome que você informou à loja.</li>
            <li><strong>Pontos e histórico</strong>: compras pontuadas, prêmios resgatados, datas e horários.</li>
            <li><strong>Consentimento</strong>: a data em que você autorizou o cadastro e a versão deste texto.</li>
        </ul>

        <h2>Para que</h2>
        <p>Somente para o programa de pontos da loja: lançar pontos, mostrar seu saldo e permitir o resgate de prêmios.</p>

        <h2>Quem vê</h2>
        <ul>
            <li>Cada loja vê apenas o nome que você deu a ela e os seus pontos nela. Uma loja não vê o que você informou em outra, nem se você é cliente de outra loja.</li>
            <li>Na consulta de saldo, quem digita o seu telefone vê só o primeiro nome e o saldo na loja do código consultado.</li>
            <li>Seus dados não são vendidos nem usados para propaganda.</li>
        </ul>

        <h2>Por quanto tempo</h2>
        <p>Enquanto você participar do programa da loja. Você pode pedir a exclusão a qualquer momento.</p>

        <h2>Como pedir a exclusão</h2>
        <p>Peça no balcão da loja. Ela apaga o seu nome e o seu telefone e zera o saldo. As movimentações continuam nos relatórios da loja, sem nada que identifique você. Se você não tiver cadastro em nenhuma outra loja, o seu telefone também é apagado do FidelityX.</p>

        <h2>Contato</h2>
        <p>Dúvidas sobre os seus dados: fale com a loja ou com o FidelityX pelo e-mail <strong>[a definir]</strong>.</p>
    </main>

    <footer class="home-footer">
        <p><a href="<?= e(url('home')) ?>">Início</a> · <a href="<?= e(url('customer/balance')) ?>">Consultar pontos</a></p>
    </footer>
</body>
</html>
