"use strict";
// envio de formulario: o botao clicado mostra "carregando" e um segundo envio do mesmo
// formulario e barrado (clique duplo ou enter repetido lancaria pontos duas vezes).
// e so conforto: sem js o formulario segue funcionando, e o servidor continua validando tudo.
//
// vale para todo <form> da pagina, sem atributo nenhum no html.
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).
// o botao nao recebe "disabled": botao desabilitado nao e enviado junto com o formulario e perde
// o foco do teclado. quem barra o segundo envio e a marca no <form>; o css (.is-loading) so mostra.
function startSubmit(form, button) {
    if (form.dataset.submitting === "1")
        return false;
    form.dataset.submitting = "1";
    if (button) {
        button.classList.add("is-loading");
        button.setAttribute("aria-busy", "true");
        button.setAttribute("aria-disabled", "true");
    }
    return true;
}
function resetSubmit(form) {
    delete form.dataset.submitting;
    form.querySelectorAll(".is-loading").forEach((button) => {
        button.classList.remove("is-loading");
        button.removeAttribute("aria-busy");
        button.removeAttribute("aria-disabled");
    });
}
// escuta no document: roda depois do onsubmit do proprio formulario, entao um confirm()
// cancelado ja chega aqui com defaultPrevented e o botao nao fica preso em "carregando".
document.addEventListener("submit", (event) => {
    var _a;
    if (event.defaultPrevented)
        return;
    const form = event.target;
    // submitter e o botao que disparou o envio; no enter dentro de um campo, cai no primeiro botao de envio
    const button = (_a = event.submitter) !== null && _a !== void 0 ? _a : form.querySelector('button[type="submit"], button:not([type])');
    if (!startSubmit(form, button))
        event.preventDefault();
});
// voltar do navegador pode trazer a pagina guardada como estava (bfcache), com o botao
// ainda em "carregando": nesse caso libera tudo de novo.
window.addEventListener("pageshow", (event) => {
    if (!event.persisted)
        return;
    document.querySelectorAll("form").forEach(resetSubmit);
});
