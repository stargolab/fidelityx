<?php

namespace App\Support;

// regra de senha do lojista (cadastro e troca de senha).
// o tamanho e contado em bytes: o bcrypt ignora sem avisar o que passa de 72 bytes, entao uma senha
// maior pareceria mais forte do que e. acento e emoji ocupam mais de um byte em utf-8.
// senhas cadastradas antes desta regra continuam entrando (o login nao confere tamanho).
final class PasswordPolicy {
    public const MIN_BYTES = 8;
    public const MAX_BYTES = 72;

    // codigo de erro do flash ('senha_curta' / 'senha_longa') ou null se a senha serve
    public static function problem(string $password): ?string {
        $bytes = strlen($password);
        if ($bytes < self::MIN_BYTES) {
            return 'senha_curta';
        }
        if ($bytes > self::MAX_BYTES) {
            return 'senha_longa';
        }
        return null;
    }
}
