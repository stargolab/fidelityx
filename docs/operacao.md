# Operação — FidelityX

Como subir o sistema em produção, atualizar o banco, fazer backup e ler o log de erros.

## Configuração

Toda a configuração vem de **variáveis de ambiente**. O arquivo `.env` na raiz é opcional: se existir, só completa o que o ambiente não definiu (o ambiente sempre tem prioridade). Em produção com Docker não há `.env` dentro do container.

| Variável | Obrigatória | Para que serve |
|---|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER` | sim | Conexão com o banco. Sem elas toda rota responde 503 e o log diz quais faltam. |
| `DB_PORT`, `DB_PASS` | não | Porta (padrão `3306`) e senha (pode ser vazia no XAMPP). |
| `APP_URL` | não | Endereço público, usado no QR do cartaz. Atrás de proxy, preencha. |
| `APP_TIMEZONE` | não | Fuso da aplicação (padrão `America/Sao_Paulo`). |
| `TRUSTED_PROXIES` | não | IPs ou faixas CIDR dos proxies confiáveis, separados por vírgula. Ver [Proxy reverso](#proxy-reverso). |
| `LOG_FILE` | não | Arquivo do log de erros. Vazio usa o destino do `php.ini`. |
| `BACKUP_DIR`, `BACKUP_KEEP_DAYS`, `MYSQLDUMP_BIN` | não | Backup: pasta (padrão `storage/backups`), dias guardados (padrão `14`, `0` nunca apaga) e caminho do `mysqldump`. |
| `DB_ROOT_PASS`, `APP_PORT` | só no Compose | Senha do root do MySQL do container e porta publicada no host (padrão `8080`). |

## Docker

O `Dockerfile` gera a imagem de produção (Apache + PHP 8.3, só a pasta `public/` é servida, dependências sem as de desenvolvimento, `display_errors` desligado). O `docker-compose.yml` sobe a aplicação e um MySQL 8.

```bash
# 1. senhas: DB_PASS (usuário "fidelityx" do banco) e DB_ROOT_PASS no .env desta pasta
cp .env.example .env

# 2. subir (a primeira subida cria o banco a partir do database/schema.sql)
docker compose up -d --build

# 3. acompanhar
docker compose ps
docker compose logs -f app
```

O sistema fica em `http://localhost:8080` (troque a porta com `APP_PORT`).

Volumes: `dbdata` (dados do MySQL), `sessions` (sessões do PHP: trocar o container não desloga os lojistas) e `backups` (arquivos do backup). **`docker compose down -v` apaga os três**; use `docker compose down` sem `-v`.

O `schema.sql` só é importado na primeira subida, com o volume do banco vazio. Depois disso o banco é atualizado pelas migrations (abaixo).

Para colocar na internet, ponha um proxy com HTTPS na frente (Nginx, Caddy, Traefik ou o load balancer do provedor) e configure `APP_URL` e `TRUSTED_PROXIES`.

### Proxy reverso

Atrás de proxy, a conexão que o PHP enxerga vem do proxy, não do cliente. Sem configurar, o limite de tentativas contaria todos os clientes como um só IP e o cookie de sessão não seria marcado como `Secure`.

Isso pesa no login: o bloqueio por tentativas vale para quem errou a senha (e-mail + IP), justamente para ninguém conseguir trancar a dona de uma conta errando a senha dela de propósito. Se todos os clientes aparecerem com o IP do proxy, esse cuidado se perde (o erro de um estranho volta a bloquear a dona) e 20 erros de login de qualquer pessoa bloqueiam o login de todas as lojas por 15 minutos. **Atrás de proxy, `TRUSTED_PROXIES` é obrigatório.**

`TRUSTED_PROXIES` lista quem pode informar o IP e o protocolo reais:

```
TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12
```

- Requisição vinda de um endereço da lista: o IP do cliente sai do `X-Forwarded-For` (lido da direita para a esquerda, parando no primeiro endereço que não é proxy confiável) e o HTTPS do `X-Forwarded-Proto`.
- Requisição vinda de qualquer outro endereço: os cabeçalhos são ignorados e vale o IP da conexão. É o comportamento com a variável vazia.
- Liste só os seus proxies. Faixa larga demais (ex.: `0.0.0.0/0`) deixa qualquer pessoa escolher o próprio IP e furar o limite de tentativas.
- O proxy precisa **sobrescrever ou acrescentar** o `X-Forwarded-For` (o padrão do Nginx com `$proxy_add_x_forwarded_for`) e enviar `X-Forwarded-Proto`.
- Com HTTPS reconhecido (direto ou pelo `X-Forwarded-Proto` de um proxy da lista), toda resposta leva `Strict-Transport-Security: max-age=31536000` (task 48): o navegador passa a usar só HTTPS no domínio por um ano. Sem `includeSubDomains`/`preload`; ligue-os no proxy se o domínio todo for HTTPS.

Com o Compose, o proxy no host chega ao container pelo gateway da rede do Docker, normalmente um endereço em `172.16.0.0/12`.

## Rota de saúde

`GET /index.php?url=health` responde `200 {"status":"ok"}` quando a aplicação consegue falar com o banco e `503 {"status":"erro"}` quando não (o motivo vai para o log, nunca para a resposta). Ela roda antes da sessão, então checar a cada 30 s não cria arquivo no volume de sessões, e sai com `Cache-Control: no-store`. É o endereço para o monitor externo e para o `HEALTHCHECK` do container (task 56; a troca do healthcheck no `Dockerfile` e o monitor com alerta ainda estão pendentes).

## Migrations

A tabela `schema_migrations` guarda o nome de cada arquivo de `database/migrations/` que já rodou no banco.

```bash
php bin/migrate.php            # roda as pendentes, em ordem
php bin/migrate.php status     # lista o que já rodou e o que falta
php bin/migrate.php baseline 003   # marca como aplicadas, sem rodar, todas até a 003
```

No Docker: `docker compose exec app php bin/migrate.php`.

- **Banco novo**: importe o `database/schema.sql`. Ele é o estado final e já registra todas as migrations; não há nada pendente.
- **Banco criado antes deste controle**: o sistema não tem como saber o que já rodou nele. Rode uma vez `php bin/migrate.php baseline NNN` com a última migration que você já tinha aplicado à mão (quem estava em dia com a `main` usa `baseline 003`) e depois `php bin/migrate.php`.
- O controle é pelo **nome do arquivo**, não pelo maior número aplicado. Migration que entra depois com número menor (dois PRs abertos ao mesmo tempo) continua pendente e roda normalmente.
- O `USE fidelityx;` dos arquivos é ignorado: a migration roda no banco de `DB_NAME`.
- O MySQL não desfaz `ALTER`/`CREATE`. Se uma migration falhar no meio, ela não é registrada e o erro diz em qual comando parou; corrija o banco e rode de novo.

**Migration nova**: crie `database/migrations/NNN_descricao.sql`, aplique a mesma mudança no `schema.sql` e acrescente o nome no `INSERT INTO schema_migrations` do fim do `schema.sql`. O teste `MigratorTest` falha se faltar a linha.

## Backup

```bash
php bin/backup.php
```

Gera `BACKUP_DIR/<banco>-AAAAMMDD-HHMMSS.sql.gz` com o `mysqldump` (cópia consistente, sem travar o sistema) e apaga os backups mais antigos que `BACKUP_KEEP_DAYS`. A limpeza só acontece depois que o backup novo foi gravado, e só mexe em arquivos com esse padrão de nome. Em caso de falha o comando sai com código 1.

Agende uma vez por dia. Exemplos:

```
# cron no servidor (3h da manhã), com Docker
0 3 * * * cd /caminho/do/fidelityx && docker compose exec -T app php bin/backup.php >> /var/log/fidelityx-backup.log 2>&1

# cron sem Docker
0 3 * * * cd /caminho/do/fidelityx && php bin/backup.php >> /var/log/fidelityx-backup.log 2>&1
```

No Windows (XAMPP), use o Agendador de Tarefas chamando `C:\xampp\php\php.exe bin\backup.php` na pasta do projeto.

Cuidados:

- O arquivo contém os dados dos clientes (telefone e nome). Guarde com acesso restrito.
- **Copie os backups para fora do servidor** (outro disco, bucket). Backup que mora só na mesma máquina some junto com ela. No Compose os arquivos ficam no volume `backups`; para tirar uma cópia: `docker compose cp app:/var/backups/fidelityx ./backups`.
- Teste a restauração de tempos em tempos, num banco separado.

### Restaurar

```bash
# sem Docker
gunzip -c fidelityx-20261005-030000.sql.gz | mysql -u root -p fidelityx

# com Docker (o arquivo está no host)
gunzip -c fidelityx-20261005-030000.sql.gz | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" fidelityx'
```

A restauração **substitui** as tabelas do banco de destino pelo conteúdo do backup. Depois dela, rode `php bin/migrate.php` (o backup pode ser anterior à última migration).

## Registro de erros

- Exceção não tratada e erro fatal viram uma linha no log e a página 500. A página mostra um código curto (ex.: `ab12cd34`) e a mesma sequência aparece na linha do log: quando um lojista relatar o erro, procure por ela.
- Formato: `[uncaught ab12cd34] Classe: mensagem em arquivo:linha | MÉTODO rota | caminho das chamadas`.
- Da requisição entram só o método e a rota. Query string, corpo do formulário e argumentos das funções ficam de fora, porque podem ter telefone e senha. A mensagem do erro em si pode citar um valor (ex.: chave duplicada), então trate o log como dado restrito.
- Warnings e notices do PHP vão para o mesmo destino.

Destino:

- **Docker**: saída do container. `docker compose logs app` (o Docker guarda e rotaciona conforme o driver de log configurado).
- **Arquivo**: defina `LOG_FILE=/caminho/fidelityx.log`. A pasta é criada se faltar; sem permissão de escrita o sistema avisa no destino padrão e segue usando-o. Configure a rotação do arquivo no servidor (ex.: `logrotate`).
- Sem `LOG_FILE`, vale o `error_log` do `php.ini`.
