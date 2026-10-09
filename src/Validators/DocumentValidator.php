<?php

namespace App\Validators;

// cpf ou cnpj, so pelo digito verificador (sem consulta externa, ver docs/adr/001).
// desde julho de 2026 a Receita emite cnpj alfanumerico: as 12 primeiras posicoes podem ter letras
// (A-Z) e os 2 digitos verificadores continuam numericos. os cnpjs so com numeros continuam valendo.
class DocumentValidator{
    // o que o lojista digitou -> so letras maiusculas e digitos (tira mascara, espaco e pontuacao).
    // e esse o valor gravado no banco.
    public static function normalize($document): string {
        return preg_replace('/[^0-9A-Z]/', '', strtoupper((string)$document));
    }

    public static function isValid($document){

        $document   =   self::normalize($document);

        // cpf e so numero; cnpj tem 12 posicoes alfanumericas + 2 digitos verificadores
        if(preg_match('/^\d{11}$/', $document)){
            return self::validarCPF($document);
        }
        elseif(preg_match('/^[0-9A-Z]{12}\d{2}$/', $document)){
            return self::validarCNPJ($document);
        }

        return false;
    }

    private static function validarCPF($document){

        // bloqueia cpf invalido com todos os dígitos iguais
        // Ex: 11111111111, 00000000000, etc
        if (preg_match('/(\d)\1{10}/', $document)) {
            return false;
        }

        // ============================
        // CÁLCULO DOS DÍGITOS
        // ============================

        // Loop para calcular os 2 últimos dígitos
        for ($t = 9; $t < 11; $t++) {

            $soma = 0;

            // Multiplica cada dígito pelos pesos decrescentes
            for ($i = 0; $i < $t; $i++) {
                $soma += $document[$i] * (($t + 1) - $i);
            }

            // Aplica regra do módulo 11
            $digito = ((10 * $soma) % 11) % 10;

            // Compara com o dígito real do CPF
            if ($document[$t] != $digito) {
                return false;
            }
        }

        // se passou por tudo é valido
        return true;
    }

    // regra da Receita (vale para o numerico e o alfanumerico): cada caractere vale o codigo ascii
    // menos 48 ('0'..'9' = 0..9, 'A' = 17 ... 'Z' = 42), pesos 5 4 3 2 9 8 7 6 5 4 3 2 no primeiro
    // digito e 6 5 4 3 2 9 8 7 6 5 4 3 2 no segundo, modulo 11 (resto 0 ou 1 = digito 0).
    private static function validarCNPJ($document){
        // bloqueia sequências inválidas (ex: 11111111111111)
        if (preg_match('/^(.)\1{13}$/', $document)) {
            return false;
        }

        $digito1 = self::digitoCNPJ(substr($document, 0, 12));
        $digito2 = self::digitoCNPJ(substr($document, 0, 12) . $digito1);

        return $document[12] === (string)$digito1 && $document[13] === (string)$digito2;
    }

    private static function digitoCNPJ(string $base): int {
        $soma = 0;
        // primeiro digito (12 posicoes) comeca no peso 5; o segundo (13) no 6. quando chega em 2, volta pro 9
        $peso = strlen($base) - 7;
        for ($i = 0; $i < strlen($base); $i++) {
            $soma += (ord($base[$i]) - 48) * $peso;
            $peso = $peso === 2 ? 9 : $peso - 1;
        }

        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    }
}
