// testa public/js/forms.js (compilado de src/ts/forms.ts) sem navegador: node tests/js/forms.test.js
// o dom e de mentira, so com o que o script usa.
const vm = require('vm'), fs = require('fs'), assert = require('assert'), path = require('path');

function element(attrs = {}) {
    const classes = new Set();
    const el = {
        attrs: { ...attrs },
        dataset: {},
        classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c) },
        getAttribute: (n) => (n in el.attrs ? el.attrs[n] : null),
        setAttribute: (n, v) => { el.attrs[n] = String(v); },
        removeAttribute: (n) => { delete el.attrs[n]; },
        closest: () => el.parentDetails || null,
        focus: () => { el.focused = true; },
    };
    return el;
}

function form(buttons) {
    const f = element();
    f.listeners = [];
    f.addEventListener = (type, fn) => f.listeners.push(fn);
    f.querySelectorAll = () => buttons;
    f.submit = (prevented = false) => {
        const event = { defaultPrevented: prevented, preventDefault() { this.defaultPrevented = true; } };
        f.listeners.forEach((fn) => fn(event));
        return event.defaultPrevented;
    };
    return f;
}

function load({ forms = [], fields = [], alert = null }) {
    const timers = [];
    const pageshow = [];
    const ctx = {
        setTimeout: (fn) => timers.push(fn),
        window: { addEventListener: (type, fn) => pageshow.push(fn) },
        document: {
            querySelectorAll: (sel) => {
                if (sel === 'form') return forms;
                const m = sel.match(/^\[name="(.+)"\]$/);
                return m ? fields.filter((f) => f.attrs.name === m[1]) : [];
            },
            querySelector: () => alert,
        },
    };
    vm.createContext(ctx);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/forms.js'), 'utf8'), ctx);
    return { runTimers: () => timers.splice(0).forEach((fn) => fn()), pageshow: (e) => pageshow.forEach((fn) => fn(e)) };
}

let checks = 0;
const check = (cond, msg) => { assert.ok(cond, msg); checks++; };

// envio: botao de enviar fica carregando e desabilitado (depois do envio montar os dados); clique duplo e barrado
{
    const send = element({ type: 'submit' });
    const noType = element();
    const helper = element({ type: 'button' });
    const f = form([send, noType, helper]);
    const page = load({ forms: [f] });

    check(f.submit() === false, 'primeiro envio passa');
    check(send.classList.contains('is-loading') && send.attrs['aria-busy'] === 'true', 'botao carregando');
    check(noType.classList.contains('is-loading'), 'button sem type tambem envia');
    check(!helper.classList.contains('is-loading'), 'botao type=button nao e de envio');
    check(send.disabled !== true, 'ainda nao desabilitou (o valor do botao iria embora do POST)');
    page.runTimers();
    check(send.disabled === true && helper.disabled !== true, 'desabilita depois');
    check(f.submit() === true, 'segundo envio e barrado');

    // voltou pelo historico (pagina do cache): destrava
    page.pageshow({ persisted: true });
    check(send.disabled === false && !send.classList.contains('is-loading') && !('aria-busy' in send.attrs), 'destravou');
    check(f.submit() === false, 'pode enviar de novo');
}

// envio cancelado antes (confirm com Cancelar) nao trava o formulario
{
    const send = element({ type: 'submit' });
    const f = form([send]);
    load({ forms: [f] });
    f.submit(true);
    check(!send.classList.contains('is-loading') && f.dataset.submitting === undefined, 'cancelado nao trava');
}

// erro: o campo apontado pela mensagem fica marcado, ligado a ela e com foco; hidden nao conta
{
    const alert = element();
    alert.id = 'flash-error';
    alert.dataset.fields = 'name phone';
    const details = { open: false };
    const name = element({ name: 'name' });
    name.parentDetails = details;
    const hidden = element({ name: 'phone', type: 'hidden' });
    const phone = element({ name: 'phone' });
    const other = element({ name: 'points' });
    load({ fields: [name, hidden, phone, other], alert });

    check(name.classList.contains('is-invalid') && name.attrs['aria-invalid'] === 'true', 'nome marcado');
    check(name.attrs['aria-describedby'] === 'flash-error', 'ligado a mensagem');
    check(details.open === true, 'details aberto');
    check(name.focused === true && !phone.focused, 'foco no primeiro');
    check(phone.classList.contains('is-invalid'), 'telefone marcado');
    check(!hidden.classList.contains('is-invalid') && !other.classList.contains('is-invalid'), 'hidden e outros nao');
}

console.log('ok', checks);
