<?php
use App\Support\Plan;

// mostra a mensagem correspondente ao ?error= ou ?success= da url.
// so exibe textos deste mapa, nunca o valor cru da url.
$flashMessages = [
    'error' => [
        // limites do plano Free (task 32): os numeros vem do Plan, pra mensagem nunca ficar diferente da regra
        'limite_clientes'       => 'Sua loja chegou ao limite de ' . Plan::limit(Plan::FREE, Plan::CUSTOMERS) . ' clientes do plano Free. Os clientes que você já tem continuam sendo atendidos normalmente. Para cadastrar mais, fale com o suporte e peça o plano Pro.',
        'limite_premios'        => 'O plano Free permite até ' . Plan::limit(Plan::FREE, Plan::ACTIVE_REWARDS) . ' prêmios ativos ao mesmo tempo. Desative um prêmio ou fale com o suporte e peça o plano Pro.',
        'campos_invalidos'      => 'Preencha todos os campos corretamente.',
        'campos_obrigatorios'   => 'Preencha todos os campos obrigatórios.',
        'documento_invalido'    => 'CPF ou CNPJ inválido.',
        'email_invalido'        => 'Informe um e-mail válido.',
        'senha_curta'           => 'A senha deve ter pelo menos 8 caracteres.',
        'senha_longa'           => 'A senha pode ter no máximo 72 caracteres (acentos e emojis contam como mais de um).',
        'senha_igual'           => 'A nova senha precisa ser diferente da atual.',
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
        'nome_invalido'         => 'Informe o nome do cliente (até 255 caracteres).',
        'telefone_novo_invalido'=> 'Informe o telefone novo com DDD.',
        'telefone_igual'        => 'O telefone novo é igual ao atual.',
        'telefone_ja_cliente'   => 'Esse telefone já é de outro cliente desta loja. Nada foi alterado.',
        'premio_invalido'       => 'Prêmio inválido ou inativo.',
        'saldo_insuficiente'    => 'Saldo de pontos insuficiente para este prêmio.',
        'confirmacao_obrigatoria' => 'Marque a confirmação de que o cliente pediu a exclusão.',
        'estorno_invalido'      => 'Lançamento não encontrado para este cliente.',
        'regra_invalida'        => 'Informe um valor de R$ 0,01 a R$ 1.000.000,00.',
        'validade_invalida'     => 'Escolha um dos prazos de validade da lista.',
        'valor_invalido'        => 'Informe o valor da compra, ex.: 12,90.',
        'valor_pontos_demais'   => 'Essa compra daria mais de 10.000 pontos, o limite por lançamento. Confira o valor ou divida em mais de um lançamento.',
        'valor_sem_pontos'      => 'O valor da compra não chega a 1 ponto pela regra da loja.',
        'estorno_repetido'      => 'Esta movimentação já foi estornada.',
        'estorno_expirado'      => 'Só dá para estornar lançamentos e resgates das últimas 24 horas.',
        'estorno_sem_saldo'     => 'Não dá para estornar: o cliente já usou parte desses pontos.',
        'senha_atual_incorreta' => 'A senha atual não confere.',
        'loja_invalida'         => 'Loja não encontrada.',
        'confirmacao_invalida'  => 'Este link de confirmação é inválido, já foi usado ou venceu. Entre no painel e peça outro.',
        'link_invalido'         => 'Este link de nova senha é inválido, já foi usado ou venceu. Peça outro.',
        'muitos_pedidos'        => 'Muitos pedidos seguidos. Aguarde um pouco e tente de novo.',
        'muitas_tentativas'     => 'Muitas tentativas com a senha atual errada. Aguarde alguns minutos e tente de novo.',
    ],
    'success' => [
        'cadastrado'        => 'Cadastro realizado! Enviamos um link de confirmação para o seu e-mail. Faça login para continuar.',
        'confirmacao_reenviada' => 'Enviamos um novo link de confirmação. O anterior deixou de valer.',
        'loja_ativada'      => 'Loja ativada. O lojista já pode entrar.',
        'loja_desativada'   => 'Loja desativada. O lojista perdeu o acesso na hora.',
        'plano_alterado'    => 'Plano da loja alterado. O limite novo já vale.',
        'email_confirmado'  => 'E-mail confirmado. Bem-vindo ao FidelityX!',
        'logged'            => 'Bem-vindo de volta!',
        'logout'            => 'Você saiu da sua conta.',
        'cliente_cadastrado' => 'Cliente cadastrado. Já pode lançar os pontos.',
        'premio_criado'     => 'Prêmio cadastrado.',
        'premio_atualizado' => 'Prêmio atualizado.',
        'premio_editado'    => 'Prêmio salvo.',
        'premio_excluido'   => 'Prêmio excluído.',
        'premio_desativado_resgatado' => 'Este prêmio já foi resgatado, então foi desativado em vez de apagado (o histórico continua completo).',
        'resgate_realizado' => 'Resgate realizado com sucesso.',
        'consentimento_registrado' => 'Consentimento registrado.',
        'cliente_excluido'  => 'Dados do cliente excluídos.',
        'nome_corrigido'       => 'Nome corrigido.',
        'telefone_trocado'     => 'Telefone trocado. Saldo e histórico vieram junto.',
        'lancamento_estornado' => 'Lançamento estornado. Os pontos saíram do saldo.',
        'resgate_estornado'    => 'Resgate estornado. Os pontos voltaram ao saldo.',
        'regra_salva'       => 'Regra de pontos salva.',
        'regra_removida'    => 'Regra de pontos removida.',
        'validade_salva'    => 'Validade dos pontos salva.',
        'perfil_atualizado' => 'Dados da loja atualizados.',
        'reset_enviado'     => 'Se o e-mail for de uma loja cadastrada, enviamos um link para criar uma nova senha. Ele vale por 1 hora.',
        'senha_redefinida'  => 'Senha nova criada. Entre com ela.',
        'senha_alterada'    => 'Senha alterada. Os outros aparelhos conectados precisam entrar de novo.',
    ],
];

// campos que cada erro aponta (pelo name do input): o public/js/forms.js marca, liga a mensagem e poe o foco.
// erro de login nao aponta campo de proposito (nao diz se o errado foi o e-mail ou a senha).
$flashFields = [
    'telefone_invalido'         => ['phone'],
    'email_invalido'            => ['email'],
    'documento_invalido'        => ['document'],
    'senha_curta'               => ['password', 'password_confirm', 'new_password', 'new_password_confirm'],
    'senha_longa'               => ['password', 'password_confirm', 'new_password', 'new_password_confirm'],
    'senhas_diferentes'         => ['password_confirm', 'new_password_confirm'],
    'senha_igual'               => ['new_password', 'new_password_confirm'],
    'senha_atual_incorreta'     => ['current_password'],
    'nome_obrigatorio'          => ['name'],
    'nome_invalido'             => ['name'],
    'consentimento_obrigatorio' => ['consent'],
    'confirmacao_obrigatoria'   => ['confirm'],
    'pontos_invalidos'          => ['points'],
    'valor_invalido'            => ['amount'],
    'valor_sem_pontos'          => ['amount'],
    'valor_pontos_demais'       => ['amount'],
    'regra_invalida'            => ['rule'],
    'validade_invalida'         => ['expiry_months'],
    'telefone_novo_invalido'    => ['new_phone'],
    'telefone_igual'            => ['new_phone'],
    'telefone_ja_cliente'       => ['new_phone'],
];

foreach (['error', 'success'] as $flashType):
    $flashCode = $_GET[$flashType] ?? null;
    if (is_string($flashCode) && isset($flashMessages[$flashType][$flashCode])):
        $fields = $flashType === 'error' ? ($flashFields[$flashCode] ?? []) : []; ?>
        <div class="alert alert-<?= $flashType ?>" id="flash-<?= $flashType ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"<?php if ($fields): ?> data-fields="<?= e(implode(' ', $fields)) ?>"<?php endif; ?>><?= e($flashMessages[$flashType][$flashCode]) ?></div>
    <?php endif;
endforeach;
