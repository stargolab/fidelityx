"use strict";
// comportamento comum dos formularios (so apresentacao: sem js tudo continua funcionando).
// - o botao de enviar mostra "carregando" e o formulario nao e enviado duas vezes (clique duplo no balcao
//   lancaria os pontos em dobro)
// - data-confirm no <form> pergunta antes de enviar (no lugar do onsubmit="return confirm(...)")
// - o campo apontado pela mensagem de erro (data-fields no .alert-error, ver views/partials/flash.php)
//   fica marcado, ligado a mensagem e com o foco
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).
function submitButtons(form) {
    return Array.from(form.querySelectorAll("button")).filter((button) => { var _a; return ((_a = button.getAttribute("type")) !== null && _a !== void 0 ? _a : "submit") === "submit"; });
}
function lockOnSubmit(form) {
    form.addEventListener("submit", (event) => {
        if (form.dataset.submitting === "1") {
            event.preventDefault();
            return;
        }
        // pergunta antes de enviar (excluir premio, estornar...): data-confirm no lugar do onsubmit,
        // que a CSP bloqueia
        const question = form.dataset.confirm;
        if (question && !window.confirm(question)) {
            event.preventDefault();
            return;
        }
        // envio cancelado antes por outro script: nada a travar
        if (event.defaultPrevented)
            return;
        form.dataset.submitting = "1";
        const buttons = submitButtons(form);
        buttons.forEach((button) => {
            button.classList.add("is-loading");
            button.setAttribute("aria-busy", "true");
        });
        // desabilita so depois que o navegador montou os dados do envio (botao desabilitado nao vai no POST)
        setTimeout(() => buttons.forEach((button) => (button.disabled = true)), 0);
    });
}
function unlockForm(form) {
    delete form.dataset.submitting;
    submitButtons(form).forEach((button) => {
        button.disabled = false;
        button.classList.remove("is-loading");
        button.removeAttribute("aria-busy");
    });
}
function markErrorFields() {
    var _a;
    const alert = document.querySelector(".alert-error[data-fields]");
    if (!alert)
        return;
    const names = ((_a = alert.dataset.fields) !== null && _a !== void 0 ? _a : "").split(" ").filter((name) => name !== "");
    let first = null;
    names.forEach((name) => {
        document.querySelectorAll('[name="' + name + '"]').forEach((field) => {
            if (field.getAttribute("type") === "hidden")
                return;
            field.classList.add("is-invalid");
            field.setAttribute("aria-invalid", "true");
            if (alert.id)
                field.setAttribute("aria-describedby", alert.id);
            // campo dentro de um <details> fechado (ex.: corrigir nome): abre pra aparecer
            const details = field.closest("details");
            if (details)
                details.open = true;
            first = first !== null && first !== void 0 ? first : field;
        });
    });
    if (first)
        first.focus();
}
document.querySelectorAll("form").forEach(lockOnSubmit);
markErrorFields();
// voltar pelo historico traz a pagina do cache do navegador como estava, com o botao travado: destrava
window.addEventListener("pageshow", (event) => {
    if (event.persisted)
        document.querySelectorAll("form").forEach(unlockForm);
});
