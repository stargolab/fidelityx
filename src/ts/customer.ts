// tela do cliente no balcao (views/merchant/customer.php). antes era um <script> inline, que a CSP bloqueia.
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).

// Desfazer da confirmacao some depois de alguns segundos (depois disso, o estorno e pelo extrato)
function startUndoTimers(): void {
    document.querySelectorAll<HTMLFormElement>(".launch-undo").forEach((form) => {
        let left = parseInt(form.dataset.undoSeconds ?? "0", 10);
        const timer = form.querySelector<HTMLElement>(".launch-undo-timer");
        const tick = (): void => {
            if (left <= 0) {
                form.remove();
                return;
            }
            if (timer) timer.textContent = "(" + left + "s)";
            left--;
            setTimeout(tick, 1000);
        };
        tick();
    });
}

// a mesma conta do Money::toCents do servidor: "12,90", "12.90", "1.234,56", "R$ 12,90" -> centavos.
// sinal de menos ou formato estranho -> null
function amountToCents(text: string): number | null {
    if (text.indexOf("-") >= 0) return null;
    let v = text.replace(/[^\d,.]/g, "");
    let intPart: string;
    let dec = "";
    if (v === "" || (v.match(/,/g) || []).length > 1) return null;
    if (v.indexOf(",") >= 0) {
        v = v.replace(/\./g, "");
        [intPart, dec] = v.split(",");
    } else {
        const parts = v.split(".");
        if (parts.length > 1 && parts[parts.length - 1].length <= 2) dec = parts.pop() ?? "";
        intPart = parts.join("");
    }
    if ((intPart === "" && dec === "") || dec.length > 2 || intPart.length > 9) return null;
    return parseInt(intPart || "0", 10) * 100 + parseInt((dec + "00").slice(0, 2), 10);
}

// valor da compra -> previa dos pontos pela regra (centavos, arredonda pra baixo; o servidor recalcula).
// digitar os pontos na mao apaga o valor, pra nao lancar uma coisa achando que e outra.
function attachAmountPreview(): void {
    const amount = document.getElementById("amount") as HTMLInputElement | null;
    const points = document.getElementById("points") as HTMLInputElement | null;
    const preview = document.getElementById("amount-preview");
    if (!amount || !points || !preview) return;

    const rule = parseInt(amount.dataset.ruleCents ?? "0", 10);
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
function attachQuickPoints(): void {
    document.querySelectorAll<HTMLButtonElement>("[data-add]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById("points") as HTMLInputElement | null;
            if (!input) return;
            input.value = String((parseInt(input.value, 10) || 0) + parseInt(button.dataset.add ?? "0", 10));
            // avisa que os pontos foram digitados (com regra, isso limpa o valor da compra)
            input.dispatchEvent(new Event("input"));
            input.focus();
        });
    });
}

startUndoTimers();
attachAmountPreview();
attachQuickPoints();
