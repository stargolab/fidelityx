<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FidelityX | Programa de fidelidade para lojas locais</title>
    <meta name="description" content="Dê pontos aos seus clientes a cada compra e troque por prêmios. Sem cartão de papel e sem app: o cliente consulta o saldo só com o telefone.">

    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/components.css">
    <link rel="stylesheet" href="/css/home.css">
</head>
<body class="home-page">
    <header class="home-header">
        <a href="<?= e(url('home')) ?>" class="home-logo"><img src="/assets/fidelityx-logo.svg" alt="FidelityX"></a>
        <a href="<?= e(url('merchant/login')) ?>" class="home-login">Entrar</a>
    </header>

    <main>
        <section class="home-hero">
            <h1>Faça seus clientes voltarem sempre</h1>
            <p class="home-lead">Dê pontos a cada compra e troque por prêmios. Sem cartão de papel e sem app para o cliente instalar.</p>

            <div class="home-actions">
                <a href="<?= e(url('merchant/register')) ?>" class="home-btn home-btn-primary">Cadastrar minha loja</a>
                <a href="<?= e(url('customer/balance')) ?>" class="home-btn home-btn-secondary">Consultar meus pontos</a>
            </div>
        </section>

        <section class="home-section" aria-labelledby="como-funciona">
            <h2 id="como-funciona">Como funciona</h2>
            <ol class="home-steps">
                <li>
                    <span class="home-step-number" aria-hidden="true">1</span>
                    <h3>Você lança os pontos</h3>
                    <p>No balcão, digite o telefone do cliente e os pontos da compra. Leva poucos segundos, até no celular.</p>
                </li>
                <li>
                    <span class="home-step-number" aria-hidden="true">2</span>
                    <h3>O cliente acompanha</h3>
                    <p>Ele consulta o saldo só com o telefone, sem login, e vê quanto falta para o próximo prêmio. Um QR code no balcão leva direto à consulta.</p>
                </li>
                <li>
                    <span class="home-step-number" aria-hidden="true">3</span>
                    <h3>Ele resgata o prêmio</h3>
                    <p>Com pontos suficientes, você resgata na hora e o saldo é descontado. Tudo fica registrado no extrato do cliente.</p>
                </li>
            </ol>
        </section>

        <section class="home-section" aria-labelledby="para-quem">
            <h2 id="para-quem">Feito para lojas locais</h2>
            <ul class="home-benefits">
                <li><strong>Prêmios do seu jeito.</strong> Você escolhe o que oferecer e quantos pontos custa.</li>
                <li><strong>Seus clientes e relatórios.</strong> Veja quem volta, quantos pontos circulam e quantos prêmios saíram.</li>
                <li><strong>Seguro.</strong> O telefone do cliente só mostra o primeiro nome na consulta, e cada loja enxerga apenas os próprios dados.</li>
            </ul>
        </section>

        <section class="home-cta">
            <h2>Comece hoje</h2>
            <p>O cadastro da loja leva alguns minutos.</p>
            <a href="<?= e(url('merchant/register')) ?>" class="home-btn home-btn-primary">Cadastrar minha loja</a>
        </section>
    </main>

    <footer class="home-footer">
        <p>© FidelityX</p>
        <p>
            <a href="<?= e(url('merchant/login')) ?>">Entrar no painel</a>
            ·
            <a href="<?= e(url('customer/balance')) ?>">Consultar pontos</a>
            ·
            <a href="<?= e(url('privacy')) ?>">Privacidade</a>
        </p>
    </footer>
</body>
</html>
