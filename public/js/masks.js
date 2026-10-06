"use strict";
// mascaras de campo (so apresentacao): o back-end continua limpando os digitos
// (PhoneValidator::sanitize e preg_replace no cadastro), entao sem js tudo segue funcionando.
//
// uso no html: data-mask="phone" ou data-mask="document" no <input>.
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).
function onlyDigits(value) {
    return value.replace(/\D/g, "");
}
// telefone com ddd: fixo (10 digitos) "(11) 3333-4444", celular (11 digitos) "(11) 99999-4444"
function maskPhone(value) {
    const d = onlyDigits(value).slice(0, 11);
    if (d.length === 0)
        return "";
    if (d.length <= 2)
        return "(" + d;
    const ddd = "(" + d.slice(0, 2) + ") ";
    if (d.length <= 6)
        return ddd + d.slice(2);
    // 10 digitos: 4+4; 11 digitos: 5+4
    const split = d.length === 11 ? 7 : 6;
    return ddd + d.slice(2, split) + "-" + d.slice(split);
}
// cpf (ate 11 digitos) "000.000.000-00"; passou de 11 (ou tem letra) vira cnpj "00.000.000/0000-00".
// o cnpj alfanumerico (task 57) tem letras nas 12 primeiras posicoes: entram em maiuscula, com a mesma mascara.
function maskDocument(value) {
    const c = value.toUpperCase().replace(/[^0-9A-Z]/g, "").slice(0, 14);
    if (c.length <= 11 && /^\d*$/.test(c)) {
        return c
            .replace(/^(\d{3})(\d)/, "$1.$2")
            .replace(/^(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
            .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, "$1.$2.$3-$4");
    }
    return c
        .replace(/^(\w{2})(\w)/, "$1.$2")
        .replace(/^(\w{2})\.(\w{3})(\w)/, "$1.$2.$3")
        .replace(/^(\w{2})\.(\w{3})\.(\w{3})(\w)/, "$1.$2.$3/$4")
        .replace(/^(\w{2})\.(\w{3})\.(\w{3})\/(\w{4})(\w)/, "$1.$2.$3/$4-$5");
}
const MASKS = {
    phone: maskPhone,
    document: maskDocument,
};
function attachMask(input) {
    var _a;
    const mask = MASKS[(_a = input.dataset.mask) !== null && _a !== void 0 ? _a : ""];
    if (!mask)
        return;
    const apply = () => {
        const formatted = mask(input.value);
        if (formatted !== input.value)
            input.value = formatted;
    };
    input.addEventListener("input", apply);
    apply(); // valor que ja vem preenchido (ex.: telefone da consulta)
}
document
    .querySelectorAll("input[data-mask]")
    .forEach(attachMask);
