<?php
// hook PostToolUse (Edit|Write): roda php -l no arquivo .php editado pelo claude.
// sai com 2 em erro de sintaxe para o claude receber o stderr e corrigir.

$input = json_decode(stream_get_contents(STDIN), true);
$file = $input['tool_input']['file_path'] ?? $input['tool_response']['filePath'] ?? '';

if ($file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php' || !is_file($file)) {
    exit(0);
}

exec(escapeshellarg(PHP_BINARY) . ' -d log_errors=0 -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

if ($code !== 0) {
    fwrite(STDERR, "php -l falhou em {$file}:\n" . implode("\n", $output) . "\n");
    exit(2);
}

exit(0);
