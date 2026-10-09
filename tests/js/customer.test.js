// testa a conta de valor da compra do public/js/customer.js (compilado de src/ts/customer.ts) sem navegador:
// node tests/js/customer.test.js. tem que bater com o Money::toCents do servidor (tests/Unit/MoneyTest.php).
const vm = require('vm'), fs = require('fs'), assert = require('assert'), path = require('path');
const ctx = {
    document: { querySelectorAll: () => [], getElementById: () => null },
    setTimeout: () => {},
};
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/customer.js'), 'utf8') + ';this.c=amountToCents;', ctx);

const cases = [
    ['12', 1200], ['12,90', 1290], ['12,9', 1290], ['12.90', 1290], ['1.234', 123400], ['1.234,56', 123456],
    [' R$ 12,90 ', 1290], ['0,01', 1], [',50', 50],
    ['', null], ['abc', null], ['12,345', null], ['1,2,3', null], ['9999999999', null],
    ['-10', null], ['R$ -12,90', null],
];
for (const [input, cents] of cases) assert.strictEqual(ctx.c(input), cents, 'amountToCents(' + input + ')');
console.log('ok', cases.length);
