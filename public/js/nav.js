"use strict";
// menu do painel (carregado no <head> do views/partials/merchant-header.php, antes do corpo).
// sem import/export de proposito: o arquivo e carregado como script comum (<script src>).
// marca que o js esta ligado ja no <head>: so assim o menu do celular comeca fechado
// (sem js os links ficam visiveis). antes era um <script> inline, que a CSP bloqueia.
document.documentElement.classList.add("js");
document.addEventListener("DOMContentLoaded", () => {
    // abre/fecha o menu no celular (no pc o botao fica escondido e o menu sempre aparece)
    const toggle = document.querySelector(".nav-toggle");
    const menu = document.getElementById("nav-menu");
    if (toggle && menu) {
        toggle.addEventListener("click", () => {
            const open = toggle.getAttribute("aria-expanded") === "true";
            toggle.setAttribute("aria-expanded", String(!open));
            menu.classList.toggle("open", !open);
        });
    }
    // botao de imprimir (cartaz): data-print no lugar do onclick
    document.querySelectorAll("[data-print]").forEach((button) => {
        button.addEventListener("click", () => window.print());
    });
});
