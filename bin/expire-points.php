<?php
// vence o saldo de pontos parado alem do prazo de cada loja (merchants.points_expiry_months, task 31).
//
//   php bin/expire-points.php             vence os saldos e registra cada um no historico (points_log 'expire')
//   php bin/expire-points.php --dry-run   so mostra quantos cartoes e pontos venceriam, sem alterar nada
//
// agendar uma vez por dia (cron, agendador de tarefas). pode rodar quantas vezes quiser: saldo ja vencido
// nao vence de novo. sai com codigo 1 se falhar, pra o agendador avisar. ver docs/operacao.md.

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Models\LoyaltyCardModel;
use App\Support\Env;
use App\Support\ErrorLog;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

Env::load(dirname(__DIR__));
date_default_timezone_set(app_timezone());
$logToFile = ErrorLog::useFile(Env::get('LOG_FILE'));

$option = $argv[1] ?? '';

try {
    if ($option !== '' && $option !== '--dry-run') {
        throw new RuntimeException("opcao desconhecida: $option (use sem nada ou com --dry-run)");
    }
    if (Env::missing() !== []) {
        throw new RuntimeException('variaveis obrigatorias sem valor: ' . implode(', ', Env::missing()));
    }

    $cards = new LoyaltyCardModel(Database::connect());

    if ($option === '--dry-run') {
        $preview = $cards->countExpirable();
        printf("venceriam agora: %d cartao(oes), %d ponto(s). nada foi alterado.\n", $preview['cards'], $preview['points']);
        exit(0);
    }

    $result = $cards->expireIdle();
    echo $result['cards'] === 0
        ? "nada a vencer.\n"
        : sprintf("%d cartao(oes) com saldo vencido, %d ponto(s) no total.\n", $result['cards'], $result['points']);
} catch (Throwable $e) {
    // vai pro terminal e, se houver LOG_FILE, pro log de erros (o agendador nem sempre guarda a saida)
    fwrite(STDERR, 'erro ao vencer pontos: ' . $e->getMessage() . "\n");
    if ($logToFile) {
        error_log('[expire-points] ' . $e->getMessage());
    }
    exit(1);
}
