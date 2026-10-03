<?php
// mostra a mensagem correspondente ao ?error= ou ?success= da url.
// so exibe textos deste mapa, nunca o valor cru da url.
$flashMessages = [
    'error' => [
        'campos_invalidos'      => 'Preencha todos os campos corretamente.',
        'campos_obrigatorios'   => 'Preencha todos os campos obrigatórios.',
        'documento_invalido'    => 'CPF ou CNPJ inválido.',
        'email_invalido'        => 'Informe um e-mail válido.',
        'senha_curta'           => 'A senha deve ter pelo menos 6 caracteres.',
        'senhas_diferentes'     => 'As senhas não conferem.',
        'ja_cadastrado'         => 'E-mail, CPF ou CNPJ já cadastrado.',
        'dados_muito_longos'    => 'Algum campo ultrapassou o tamanho permitido.',
        'formato_invalido'      => 'Algum campo está em formato inválido.',
        'banco_indisponivel'    => 'Serviço temporariamente indisponível. Tente novamente.',
        'erro_servidor'         => 'Ocorreu um erro inesperado. Tente novamente.',
        'credenciais_invalidas' => 'E-mail ou senha incorretos.',
        'conta_inativa'         => 'Sua conta está inativa. Fale com o suporte.',
        'sessao_expirada'       => 'Faça login para continuar.',
        'telefone_invalido'     => 'Informe um telefone válido com DDD.',
        'pontos_invalidos'      => 'Informe uma quantidade de pontos entre 1 e 10.000.',
        'nome_obrigatorio'      => 'Cliente novo: informe o nome para cadastrá-lo.',
        'consentimento_obrigatorio' => 'Confirme que o cliente autorizou o cadastro.',
        'cliente_nao_encontrado'=> 'Nenhum cliente com esse telefone nesta loja.',
        'premio_invalido'       => 'Prêmio inválido ou inativo.',
        'saldo_insuficiente'    => 'Saldo de pontos insuficiente para este prêmio.',
    ],
    'success' => [
        'cadastrado'        => 'Cadastro realizado! Faça login para continuar.',
        'logged'            => 'Bem-vindo de volta!',
        'logout'            => 'Você saiu da sua conta.',
        'cliente_cadastrado' => 'Cliente cadastrado. Já pode lançar os pontos.',
        'pontos_lancados'   => 'Pontos lançados com sucesso.',
        'premio_criado'     => 'Prêmio cadastrado.',
        'premio_atualizado' => 'Prêmio atualizado.',
        'resgate_realizado' => 'Resgate realizado com sucesso.',
    ],
];

foreach (['error', 'success'] as $flashType):
    $flashCode = $_GET[$flashType] ?? null;
    if (is_string($flashCode) && isset($flashMessages[$flashType][$flashCode])): ?>
        <div class="alert alert-<?= $flashType ?>"><?= e($flashMessages[$flashType][$flashCode]) ?></div>
    <?php endif;
endforeach;
