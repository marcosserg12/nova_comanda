/*
 * Visual sweep of the redesign: screenshots + overflow/visibility checks at the approved sizes.
 * Usage (Git Bash, from the project root): node tests/Browser/screenshots.cjs
 * Needs NANA_E2E_USER / NANA_E2E_PASSWORD (local admin user); NANA_BASE_URL defaults to http://127.0.0.1:8000.
 */
const fs = require('fs');
const path = require('path');
const s = require('./support.cjs');

const OUT = path.resolve(__dirname, '../../storage/app/screenshots');
const results = [];

async function check(label, run) {
    try {
        await run();
        results.push([label, 'OK']);
        console.log(`OK      ${label}`);
    } catch (error) {
        results.push([label, `FALHOU: ${error.message}`]);
        console.log(`FALHOU  ${label}: ${error.message}`);
    }
}

async function shot(page, name) {
    await page.screenshot({ path: path.join(OUT, `${name}.png`) });
}

async function checkKitchen(page, label, scope = '') {
    const status = await s.kitchenStatus(page, scope);
    if (status.total === 0) {
        s.fail(`${label}: nenhum pedido na cozinha`);
    }
    if (status.buttonsCut > 0) {
        s.fail(`${label}: ${status.buttonsCut} botões PRONTO cortados`);
    }
    const expected = status.below > 0 ? `+${status.below} ${status.below === 1 ? 'pedido abaixo' : 'pedidos abaixo'} ↓` : '';
    if (status.moreText !== expected) {
        s.fail(`${label}: rodapé "${status.moreText}" ≠ "${expected}"`);
    }
    if (status.total >= 10 && status.maxItemsVisible <= 2 && status.visible < 8) {
        s.fail(`${label}: só ${status.visible} pedidos inteiros visíveis (esperado 8)`);
    }
    return status;
}

async function checkWideCounter(page, label) {
    await s.expectNoHorizontalScroll(page, label);
    await s.expectInViewport(page, '[data-send-button]', `${label}: botão Enviar`);
    await s.expectInViewport(page, '[data-order-total]', `${label}: total`);
    await s.expectLastProductReachable(page, label);
}

(async () => {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);

        // At least 10 orders in the kitchen, so "+N pedidos abaixo" can be measured.
        const probe = await s.open(browser, '/cozinha', { width: 1366, height: 768, state });
        const { total } = await s.kitchenStatus(probe.page);
        await probe.context.close();
        if (total < 10) {
            const counter = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
            for (let i = total; i < 10; i += 1) {
                await s.placeOrder(counter.page, `Teste varredura ${i + 1}`);
            }
            await counter.context.close();
        }

        await check('Início 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/?escolher=1', { width: 1366, height: 768, state });
            await s.expectNoHorizontalScroll(page, 'início');
            await s.expectInViewport(page, '[data-screen]', 'início: blocos');
            await shot(page, '01-inicio-1366x768');
            s.expectNoErrors(errors, 'início');
            await context.close();
        });

        await check('Login 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/login', { width: 1366, height: 768 });
            await s.expectNoHorizontalScroll(page, 'login');
            await s.expectInViewport(page, 'form button[type="submit"]', 'login: botão Entrar');
            await shot(page, '02-login-1366x768');
            s.expectNoErrors(errors, 'login');
            await context.close();
        });

        await check('Balcão 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
            await checkWideCounter(page, 'balcão 1366x768');
            await s.expectInViewport(page, '[data-in-kitchen-column]', 'balcão 1366x768: Na cozinha');
            await shot(page, '03-balcao-1366x768');
            s.expectNoErrors(errors, 'balcão 1366x768');
            await context.close();
        });

        await check('Balcão 1280×800 (tablet deitado)', async () => {
            const { page, context, errors } = await s.open(browser, '/balcao', { width: 1280, height: 800, state, touch: true });
            await checkWideCounter(page, 'balcão 1280x800');
            await shot(page, '04-balcao-1280x800');
            s.expectNoErrors(errors, 'balcão 1280x800');
            await context.close();
        });

        await check('Balcão 800×1280 (tablet em pé)', async () => {
            const { page, context, errors } = await s.open(browser, '/balcao', { width: 800, height: 1280, state, touch: true });
            await s.expectNoHorizontalScroll(page, 'balcão 800x1280');
            await s.expectInViewport(page, '[data-order-bar]', 'balcão 800x1280: barra do pedido');
            await s.expectLastProductReachable(page, 'balcão 800x1280');
            await shot(page, '05-balcao-800x1280');
            await page.tap('[data-order-bar]');
            await s.expectInViewport(page, '[data-send-button]', 'balcão 800x1280: Enviar no pedido aberto');
            await s.expectInViewport(page, '[data-order-total]', 'balcão 800x1280: total no pedido aberto');
            await shot(page, '06-balcao-800x1280-pedido');
            s.expectNoErrors(errors, 'balcão 800x1280');
            await context.close();
        });

        await check('Cozinha 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/cozinha', { width: 1366, height: 768, state });
            await s.expectNoHorizontalScroll(page, 'cozinha');
            const status = await checkKitchen(page, 'cozinha');
            console.log('        cozinha:', JSON.stringify(status));
            await shot(page, '07-cozinha-1366x768');
            s.expectNoErrors(errors, 'cozinha');
            await context.close();
        });

        await check('Cozinha: destaque na borda e aviso no rodapé 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/cozinha', { width: 1366, height: 768, state });
            const ids = await page.$$eval('[data-order-id]', (cards) => cards.map((card) => card.dataset.orderId));
            if (ids.length < 10) {
                s.fail(`cozinha: só ${ids.length} pedidos`);
            }
            // First row, first column: the yellow outline must not be clipped by the grid.
            await page.evaluate((orderId) => window.dispatchEvent(new CustomEvent('kitchen-highlight', { detail: { orderId, kind: 'created' } })), ids[0]);
            await page.waitForTimeout(300);
            const edge = await page.evaluate(() => {
                const grid = document.querySelector('[data-kitchen-grid]').getBoundingClientRect();
                const card = document.querySelector('[data-order-id]').getBoundingClientRect();
                return { left: card.left - grid.left, top: card.top - grid.top };
            });
            // outline reaches 2 (inset) + 2 (offset) + 6 (width) = 10 px beyond the card
            if (edge.left < 10 || edge.top < 10) {
                s.fail(`destaque cortado na borda (folga ${JSON.stringify(edge)} < 10 px)`);
            }
            await shot(page, 'kitchen-highlight-edge');
            // Highlighted card below the fold: the whole footer turns into the alert bar.
            await page.evaluate((orderId) => window.dispatchEvent(new CustomEvent('kitchen-highlight', { detail: { orderId, kind: 'created' } })), ids[ids.length - 1]);
            await page.locator('[data-kitchen-alert-bar]').waitFor({ state: 'visible', timeout: 3000 });
            await s.expectInViewport(page, '[data-kitchen-alert-bar]', 'cozinha: barra de aviso');
            const bar = await page.evaluate(() => {
                const footer = document.querySelector('[data-kitchen-footer]');
                const hint = [...footer.querySelectorAll('span')].find((el) => el.textContent.includes('Teclado numérico'));
                return { bg: getComputedStyle(footer).backgroundColor, hintHidden: !hint || hint.getClientRects().length === 0 };
            });
            if (bar.bg !== 'rgb(255, 183, 3)' || !bar.hintHidden) {
                s.fail(`rodapé de aviso inválido ${JSON.stringify(bar)}`);
            }
            await shot(page, 'kitchen-alert-bar');
            s.expectNoErrors(errors, 'cozinha destaque');
            await context.close();
        });

        await check('Estação 2×1366×768', async () => {
            const leftIs1366 = () => { try { localStorage.setItem('nana.station.left', '1366'); } catch (e) {} };
            const { page, context, errors } = await s.open(browser, '/estacao', { width: 2732, height: 768, state, init: leftIs1366 });
            const widths = await page.evaluate(() => ({
                left: Math.round(document.querySelector('[data-station-left]').getBoundingClientRect().width),
                right: Math.round(document.querySelector('[data-station-right]').getBoundingClientRect().width),
            }));
            if (widths.left !== 1366 || widths.right !== 1366) {
                s.fail(`estação: larguras ${JSON.stringify(widths)} (esperado 1366 + 1366)`);
            }
            await s.expectNoHorizontalScroll(page, 'estação');
            await s.expectInViewport(page, '[data-send-button]', 'estação: botão Enviar');
            await checkKitchen(page, 'estação (cozinha)', '[data-station-right]');
            await shot(page, '08-estacao-2732x768');
            s.expectNoErrors(errors, 'estação');
            await context.close();
        });

        await check('Painel da TV 1920×1080', async () => {
            const { page, context, errors } = await s.open(browser, '/painel', { width: 1920, height: 1080 });
            await page.click('text=Toque para começar');
            await page.waitForTimeout(300);
            await s.expectNoHorizontalScroll(page, 'TV');
            await s.expectInViewport(page, '[data-tv-preparing]', 'TV: preparando');
            if (await page.locator('[data-tv-overflow]').count()) {
                await s.expectInViewport(page, '[data-tv-overflow]', 'TV: +N');
            }
            if (await page.locator('[data-tv-ready]').count()) {
                await s.expectInViewport(page, '[data-tv-ready]', 'TV: prontos');
            }
            await s.expectInViewport(page, 'img[alt="QR code para acompanhar o pedido"]', 'TV: QR');
            await shot(page, '09-tv-1920x1080');
            s.expectNoErrors(errors, 'TV');
            await context.close();
        });

        await check('Celular 390×844', async () => {
            const { page, context, errors } = await s.open(browser, '/acompanhar', { width: 390, height: 844, touch: true });
            await s.expectNoHorizontalScroll(page, 'celular');
            await s.expectInViewport(page, 'input[x-model="mine"]', 'celular: campo Seu número');
            const preparingNumbers = page.locator('[data-phone-preparing]');
            if (await preparingNumbers.count()) {
                const first = await preparingNumbers.first().getAttribute('data-phone-preparing');
                await page.fill('input[x-model="mine"]', String(parseInt(first, 10)));
                await page.locator('[data-my-order="preparing"]').waitFor({ state: 'visible', timeout: 3000 });
                await s.expectInViewport(page, '[data-my-order="preparing"]', 'celular: cartão do meu pedido');
            }
            await shot(page, '10-celular-390x844');
            s.expectNoErrors(errors, 'celular');
            await context.close();
        });
    } finally {
        await browser.close();
    }

    console.log('\n| Tela e tamanho | Resultado |\n|---|---|');
    for (const [label, result] of results) {
        console.log(`| ${label} | ${result} |`);
    }
    process.exit(results.some(([, result]) => result !== 'OK') ? 1 : 0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
