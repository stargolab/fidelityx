// comportamento comum dos formularios (so apresentacao: sem js tudo continua funcionando).
// - o botao de enviar mostra "carregando" e o formulario nao e enviado duas vezes (clique duplo no balcao
//   lancaria os pontos em dobro)
// - data-confirm no <form> pergunta antes de enviar (no lugar do onsubmit="return confirm(...)")
// - o campo apontado pela mensagem de erro (data-fields no .alert-error, ver views/partials/flash.php)
//   fica marcado, ligado a mensagem e com o foco
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).

function submitButtons(form: HTMLFormElement): HTMLButtonElement[] {
    return Array.from(form.querySelectorAll<HTMLButtonElement>("button")).filter(
        (button) => (button.getAttribute("type") ?? "submit") === "submit"
    );
}

function lockOnSubmit(form: HTMLFormElement): void {
    form.addEventListener("submit", (event: Event) => {
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
        if (event.defaultPrevented) return;

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

function unlockForm(form: HTMLFormElement): void {
    delete form.dataset.submitting;
    submitButtons(form).forEach((button) => {
        button.disabled = false;
        button.classList.remove("is-loading");
        button.removeAttribute("aria-busy");
    });
}

function markErrorFields(): void {
    const alert = document.querySelector<HTMLElement>(".alert-error[data-fields]");
    if (!alert) return;

    const names = (alert.dataset.fields ?? "").split(" ").filter((name) => name !== "");
    let first: HTMLElement | null = null;
    names.forEach((name) => {
        document.querySelectorAll<HTMLElement>('[name="' + name + '"]').forEach((field) => {
            if (field.getAttribute("type") === "hidden") return;
            field.classList.add("is-invalid");
            field.setAttribute("aria-invalid", "true");
            if (alert.id) field.setAttribute("aria-describedby", alert.id);
            // campo dentro de um <details> fechado (ex.: corrigir nome): abre pra aparecer
            const details = field.closest("details");
            if (details) details.open = true;
            first = first ?? field;
        });
    });
    if (first) (first as HTMLElement).focus();
}

document.querySelectorAll<HTMLFormElement>("form").forEach(lockOnSubmit);
markErrorFields();

// voltar pelo historico traz a pagina do cache do navegador como estava, com o botao travado: destrava
window.addEventListener("pageshow", (event: PageTransitionEvent) => {
    if (event.persisted) document.querySelectorAll<HTMLFormElement>("form").forEach(unlockForm);
});
