// testa o bloqueio de envio duplo de public/js/forms.js (compilado de src/ts/forms.ts) sem navegador: node tests/js/forms.test.js
const vm = require('vm'), fs = require('fs'), assert = require('assert');

// o minimo de DOM que o forms.js usa: guarda os listeners pra disparar os eventos na mao
const listeners = {};
const listen = (type, fn) => { listeners[type] = fn; };
const makeButton = () => {
    const classes = new Set(), attrs = {};
    return {
        classes, attrs,
        classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c) },
        setAttribute: (k, v) => { attrs[k] = v; },
        removeAttribute: (k) => { delete attrs[k]; },
    };
};
const makeForm = (button) => ({
    dataset: {},
    querySelector: () => button,
    querySelectorAll: () => (button.classes.has('is-loading') ? [button] : []),
});
const submit = (form, extra) => {
    const event = Object.assign({ target: form, defaultPrevented: false, submitter: null }, extra);
    event.preventDefault = () => { event.defaultPrevented = true; };
    listeners.submit(event);
    return event;
};

const forms = [];
const ctx = { document: { addEventListener: listen, querySelectorAll: () => forms }, window: { addEventListener: listen } };
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(require('path').join(__dirname, '../../public/js/forms.js'), 'utf8'), ctx);

// primeiro envio passa e o botao clicado entra em "carregando"
let button = makeButton(), form = makeForm(button);
forms.push(form);
assert.strictEqual(submit(form, { submitter: button }).defaultPrevented, false, 'primeiro envio passa');
assert.ok(button.classes.has('is-loading'), 'botao em carregando');
assert.strictEqual(button.attrs['aria-busy'], 'true');
assert.strictEqual(button.attrs.disabled, undefined, 'botao nao fica disabled');

// segundo envio do mesmo formulario e barrado
assert.strictEqual(submit(form, { submitter: button }).defaultPrevented, true, 'segundo envio barrado');

// voltar do navegador com a pagina guardada (bfcache) libera de novo; carga normal nao mexe
listeners.pageshow({ persisted: false });
assert.ok(button.classes.has('is-loading'), 'carga normal nao mexe');
listeners.pageshow({ persisted: true });
assert.ok(!button.classes.has('is-loading'), 'bfcache libera o botao');
assert.strictEqual(button.attrs['aria-busy'], undefined);
assert.strictEqual(submit(form, { submitter: button }).defaultPrevented, false, 'envia de novo depois de voltar');

// confirm() cancelado (onsubmit devolveu false): nada de carregando, e o proximo envio continua valendo
button = makeButton(); form = makeForm(button);
submit(form, { submitter: button, defaultPrevented: true });
assert.ok(!button.classes.has('is-loading'), 'envio cancelado nao marca o botao');
assert.strictEqual(submit(form, { submitter: button }).defaultPrevented, false, 'envia depois do cancelamento');

// enter dentro de um campo (sem submitter): usa o primeiro botao de envio do formulario
button = makeButton(); form = makeForm(button);
submit(form);
assert.ok(button.classes.has('is-loading'), 'enter marca o botao de envio');

console.log('ok');
