<?php

namespace App\Validators;

class PhoneValidator {
    // remove a mascara do front-end: "(11) 99999-9999" -> "11999999999"
    public static function sanitize($phone): string {
        return preg_replace('/\D/', '', (string)$phone);
    }

    // telefone brasileiro com DDD: 10 digitos (fixo) ou 11 (celular)
    public static function isValid($phone): bool {
        $phone = self::sanitize($phone);
        return (bool)preg_match('/^\d{10,11}$/', $phone);
    }
}
