// Run with: npm run test:js
import assert from 'node:assert/strict';

const store = new Map();
globalThis.localStorage = { getItem: (k) => store.get(k) ?? null, setItem: (k, v) => store.set(k, String(v)) };
globalThis.CustomEvent = class { constructor(type, init) { this.type = type; this.detail = init?.detail; } };
const toasts = [];
globalThis.window = { innerWidth: 3832, devicePixelRatio: 1, addEventListener() {}, removeEventListener() {}, dispatchEvent: (e) => toasts.push(e.detail) };

const { registerStation } = await import('../../resources/js/station.js');
let factory;
registerStation({ data: (name, fn) => { factory = fn; } });

async function run(screens, dpr = 1) {
    store.clear();
    window.devicePixelRatio = dpr;
    window.getScreenDetails = async () => ({ screens });
    const s = factory(1366, 1366);
    s.init();
    await s.detect();
    return s;
}

// Este PC: monitor 1920 (100%) à esquerda, notebook 1536 DIP (125%) à direita, outro monitor acima do notebook.
let s = await run([
    { left: 0, top: -1080, width: 1920, height: 1080, devicePixelRatio: 1 },
    { left: 0, top: 0, width: 1536, height: 960, devicePixelRatio: 1.25 },
    { left: -1920, top: 67, width: 1920, height: 1080, devicePixelRatio: 1 },
]);
assert.equal(s.leftWidth, 1920, 'balcão');
assert.equal(s.rightWidth, 1920, 'cozinha (notebook 1536 DIP × 1,25)');
assert.equal(store.get('nana.station.right'), '1920');

// Loja: dois monitores 1366 a 100%.
s = await run([
    { left: 1366, top: 0, width: 1366, height: 768, devicePixelRatio: 1 },
    { left: 0, top: 0, width: 1366, height: 768, devicePixelRatio: 1 },
]);
assert.equal(s.leftWidth, 1366);
assert.equal(s.rightWidth, 1366);

// Cozinha com monitor mais largo.
s = await run([
    { left: 0, top: 0, width: 1366, height: 768, devicePixelRatio: 1 },
    { left: 1366, top: 0, width: 1600, height: 900, devicePixelRatio: 1 },
]);
assert.equal(s.rightWidth, 1600);
assert.equal(s.columns, '1366px minmax(0, 1600px)');

// Um monitor só: a cozinha fica como estava.
s = await run([{ left: 0, top: 0, width: 1920, height: 1080, devicePixelRatio: 1 }]);
assert.equal(s.leftWidth, 1920);
assert.equal(s.rightWidth, 1366);
assert.equal(store.get('nana.station.right'), undefined);

// Janela passou para a escala do notebook (125%) ou zoom: a divisão continua na borda física.
s = await run([
    { left: -1920, top: 67, width: 1920, height: 1080, devicePixelRatio: 1 },
    { left: 0, top: 0, width: 1536, height: 960, devicePixelRatio: 1.25 },
]);
window.devicePixelRatio = 1.25;
s.onResize();
assert.equal(s.leftWidth, 1536, 'balcão em pixels da janela a 125%');
assert.equal(s.rightWidth, 1536);
assert.equal(s.columns, '1536px minmax(0, 1536px)');

// Detectar com a janela já a 125% grava a mesma medida física.
s = await run([
    { left: -1920, top: 67, width: 1920, height: 1080, devicePixelRatio: 1 },
    { left: 0, top: 0, width: 1536, height: 960, devicePixelRatio: 1.25 },
], 1.25);
assert.equal(store.get('nana.station.left'), '1920');
assert.equal(store.get('nana.station.right'), '1920');
assert.equal(s.leftWidth, 1536);

// Padrão das configurações (1366) com zoom de 110% no Chrome.
store.clear();
window.devicePixelRatio = 1.1;
s = factory(1366, 1366);
assert.equal(s.leftWidth, 1242);

// O instalador manda as larguras reais na URL: valem sem precisar de "Detectar monitores".
store.clear();
window.devicePixelRatio = 1;
window.location = { search: '?balcao=1920&cozinha=1600' };
s = factory(1366, 1366);
s.init();
assert.equal(s.leftWidth, 1920);
assert.equal(s.rightWidth, 1600);
assert.equal(store.get('nana.station.left'), '1920');
assert.equal(store.get('nana.station.right'), '1600');

// Valores inválidos na URL são ignorados.
store.clear();
window.location = { search: '?balcao=abc&cozinha=50' };
s = factory(1366, 1366);
s.init();
assert.equal(s.leftWidth, 1366);
assert.equal(s.rightWidth, 1366);
assert.equal(store.get('nana.station.left'), undefined);
window.location = undefined;

console.log('station detect: OK');
