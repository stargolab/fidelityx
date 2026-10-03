// testa as funcoes de mascara de public/js/masks.js (compilado de src/ts/masks.ts) sem navegador: node tests/js/masks.test.js
const vm = require('vm'), fs = require('fs'), assert = require('assert');
const ctx = { document: { querySelectorAll: () => ({ forEach() {} }) } };
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(require('path').join(__dirname, '../../public/js/masks.js'), 'utf8') + ';this.p=maskPhone;this.d=maskDocument;', ctx);
const cases = [
  ['p', '', ''], ['p', '1', '(1'], ['p', '11', '(11'], ['p', '119', '(11) 9'],
  ['p', '119999', '(11) 9999'], ['p', '1133334444', '(11) 3333-4444'],
  ['p', '11999998888', '(11) 99999-8888'], ['p', '119999988889999', '(11) 99999-8888'],
  ['p', '(11) 99999-8888', '(11) 99999-8888'], ['p', 'abc', ''],
  ['d', '529', '529'], ['d', '5299', '529.9'], ['d', '52998224725', '529.982.247-25'],
  ['d', '529.982.247-25', '529.982.247-25'], ['d', '112223330001', '11.222.333/0001'],
  ['d', '11222333000181', '11.222.333/0001-81'], ['d', '112223330001819999', '11.222.333/0001-81'],
];
for (const [f, i, o] of cases) assert.strictEqual(ctx[f](i), o, f + '(' + i + ')');
console.log('ok', cases.length);
