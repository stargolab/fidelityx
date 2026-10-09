"use strict";
// tela do cliente no balcao (views/merchant/customer.php). antes era um <script> inline, que a CSP bloqueia.
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).
// Desfazer da confirmacao some depois de alguns segundos (depois disso, o estorno e pelo extrato)
function startUndoTimers() {
    document.querySelectorAll(".launch-undo").forEach((form) => {
        var _a;
        let left = parseInt((_a = form.dataset.undoSeconds) !== null && _a !== void 0 ? _a : "0", 10);
        const timer = form.querySelector(".launch-undo-timer");
        const tick = () => {
            if (left <= 0) {
                form.remove();
                return;
            }
            if (timer)
                timer.textContent = "(" + left + "s)";
            left--;
            setTimeout(tick, 1000);
        };
        tick();
    });
}
// a mesma conta do Money::toCents do servidor: "12,90", "12.90", "1.234,56", "R$ 12,90" -> centavos.
// sinal de menos ou formato estranho -> null
function amountToCents(text) {
    var _a;
    if (text.indexOf("-") >= 0)
        return null;
    let v = text.replace(/[^\d,.]/g, "");
    let intPart;
    let dec = "";
    if (v === "" || (v.match(/,/g) || []).length > 1)
        return null;
    if (v.indexOf(",") >= 0) {
        v = v.replace(/\./g, "");
        [intPart, dec] = v.split(",");
    }
    else {
        const parts = v.split(".");
        if (parts.length > 1 && parts[parts.length - 1].length <= 2)
            dec = (_a = parts.pop()) !== null && _a !== void 0 ? _a : "";
        intPart = parts.join("");
    }
    if ((intPart === "" && dec === "") || dec.length > 2 || intPart.length > 9)
        return null;
    return parseInt(intPart || "0", 10) * 100 + parseInt((dec + "00").slice(0, 2), 10);
}
// valor da compra -> previa dos pontos pela regra (centavos, arredonda pra baixo; o servidor recalcula).
// digitar os pontos na mao apaga o valor, pra nao lancar uma coisa achando que e outra.
function attachAmountPreview() {
    var _a;
    const amount = document.getElementById("amount");
    const points = document.getElementById("points");
    const preview = document.getElementById("amount-preview");
    if (!amount || !points || !preview)
        return;
    const rule = parseInt((_a = amount.dataset.ruleCents) !== null && _a !== void 0 ? _a : "0", 10);
    const original = preview.textContent;
    amount.addEventListener("input", () => {
        const cents = amountToCents(amount.value);
        if (cents === null || rule <= 0) {
            points.value = "";
            preview.textContent = amount.value.trim() === "" ? original : "Valor inválido.";
            return;
        }
        const total = Math.floor(cents / rule);
        points.value = total > 0 ? String(total) : "";
        preview.textContent = total > 0
            ? "= " + total + (total === 1 ? " ponto" : " pontos")
            : "Valor abaixo de 1 ponto.";
    });
    points.addEventListener("input", () => {
        amount.value = "";
        preview.textContent = original;
    });
}
// atalhos +1/+5/+10 somam no campo de pontos (o lancamento continua pelo botao)
function attachQuickPoints() {
    document.querySelectorAll("[data-add]").forEach((button) => {
        button.addEventListener("click", () => {
            var _a;
            const input = document.getElementById("points");
            if (!input)
                return;
            input.value = String((parseInt(input.value, 10) || 0) + parseInt((_a = button.dataset.add) !== null && _a !== void 0 ? _a : "0", 10));
            // avisa que os pontos foram digitados (com regra, isso limpa o valor da compra)
            input.dispatchEvent(new Event("input"));
            input.focus();
        });
    });
}
startUndoTimers();
attachAmountPreview();
attachQuickPoints();
