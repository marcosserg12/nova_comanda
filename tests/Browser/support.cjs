/*
 * Helpers for the redesign's browser checks (Playwright via plain node).
 * Env: NANA_BASE_URL (default http://127.0.0.1:8000), NANA_E2E_USER / NANA_E2E_PASSWORD (local admin user),
 * NANA_PLAYWRIGHT_PATH / NANA_CHROME_PATH to override the local Playwright install.
 */
const PLAYWRIGHT_PATH = process.env.NANA_PLAYWRIGHT_PATH
    || 'C:/Users/mstjs/AppData/Roaming/npm/node_modules/@playwright/cli/node_modules/playwright';
const CHROME_PATH = process.env.NANA_CHROME_PATH
    || 'C:/Users/mstjs/AppData/Local/ms-playwright/chromium-1248/chrome-win64/chrome.exe';
const BASE_URL = (process.env.NANA_BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '[::1]'];

const { chromium } = require(PLAYWRIGHT_PATH);

function fail(message) {
    throw new Error(message);
}

async function launch() {
    return chromium.launch({ executablePath: CHROME_PATH, headless: true });
}

/** Logs in once and returns the storage state; the login route accepts only 5 attempts per minute. */
async function loginState(browser) {
    const username = process.env.NANA_E2E_USER;
    const password = process.env.NANA_E2E_PASSWORD;
    if (!username || !password) {
        fail('Defina NANA_E2E_USER e NANA_E2E_PASSWORD (usuário admin local) antes de rodar.');
    }
    const context = await browser.newContext({ baseURL: BASE_URL });
    const page = await context.newPage();
    await page.goto('/login');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.click('button[type="submit"]'),
    ]);
    const state = await context.storageState();
    await context.close();
    return state;
}

async function open(browser, path, { width, height, state = null, touch = false, init = null, reducedMotion = 'no-preference' } = {}) {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        viewport: { width, height },
        hasTouch: touch,
        reducedMotion,
        storageState: state ?? undefined,
    });
    if (init) {
        await context.addInitScript(init);
    }
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (request) => {
        const url = request.url();
        if (/^(https?|wss?):/.test(url) && !LOCAL_HOSTS.includes(new URL(url).hostname)) {
            errors.push(`requisição externa: ${url}`);
        }
    });
    await page.goto(path, { waitUntil: 'load' });
    await page.waitForFunction(() => window.Alpine !== undefined && document.readyState === 'complete');
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(400);
    return { context, page, errors };
}

function expectNoErrors(errors, label) {
    if (errors.length > 0) {
        fail(`${label}: ${errors.join(' | ')}`);
    }
}

async function expectNoHorizontalScroll(page, label) {
    const m = await page.evaluate(() => ({ scroll: document.scrollingElement.scrollWidth, inner: window.innerWidth }));
    if (m.scroll > m.inner) {
        fail(`${label}: rolagem horizontal (${m.scroll}px > ${m.inner}px)`);
    }
}

/** Every rendered element matching the selector must sit entirely inside the viewport. Returns how many. */
async function expectInViewport(page, selector, label) {
    const r = await page.evaluate((sel) => {
        const els = [...document.querySelectorAll(sel)].filter((el) => el.getClientRects().length > 0);
        const cut = els.filter((el) => {
            const b = el.getBoundingClientRect();
            return b.top < -0.5 || b.left < -0.5 || b.bottom > window.innerHeight + 0.5 || b.right > window.innerWidth + 0.5;
        });
        return { count: els.length, cut: cut.length };
    }, selector);
    if (r.count === 0) {
        fail(`${label}: nada visível para ${selector}`);
    }
    if (r.cut > 0) {
        fail(`${label}: ${r.cut} de ${r.count} elementos ${selector} cortados`);
    }
    return r.count;
}

async function expectLastProductReachable(page, label) {
    const problem = await page.evaluate(async () => {
        const grid = document.querySelector('[data-product-grid]');
        if (!grid || grid.getClientRects().length === 0) {
            return 'grade de pratos não visível';
        }
        grid.scrollTop = grid.scrollHeight;
        await new Promise((resolve) => requestAnimationFrame(resolve));
        const tiles = grid.querySelectorAll('[data-product-tile]');
        if (tiles.length === 0) {
            return 'nenhum prato';
        }
        const last = tiles[tiles.length - 1].getBoundingClientRect();
        const box = grid.getBoundingClientRect();
        const ok = last.bottom <= box.bottom + 1 && last.top >= box.top - 1 && box.bottom <= window.innerHeight + 0.5;
        grid.scrollTop = 0;
        return ok ? '' : 'último prato cortado';
    });
    if (problem) {
        fail(`${label}: ${problem}`);
    }
}

/** Kitchen grid status: total cards, fully visible ones, cut PRONTO buttons and the footer text. */
async function kitchenStatus(page, scope = '') {
    return page.evaluate((root) => {
        const grid = document.querySelector(`${root} [data-kitchen-grid]`.trim());
        if (!grid) {
            return { total: 0, visible: 0, below: 0, buttonsCut: 0, moreText: '', maxItemsVisible: 0 };
        }
        const limit = grid.getBoundingClientRect();
        const cards = [...grid.querySelectorAll('[data-order-id]')];
        const visible = cards.filter((card) => card.getBoundingClientRect().bottom <= limit.bottom + 1);
        const buttonsCut = visible.filter((card) => {
            const b = card.querySelector('[data-ready-button]').getBoundingClientRect();
            return b.bottom > limit.bottom + 1 || b.right > window.innerWidth + 0.5;
        }).length;
        const more = document.querySelector(`${root} [data-kitchen-more]`.trim());
        const moreText = more && more.getClientRects().length > 0 ? more.textContent.trim() : '';
        const maxItemsVisible = Math.max(0, ...visible.map((card) => card.querySelectorAll('li').length));
        return { total: cards.length, visible: visible.length, below: cards.length - visible.length, buttonsCut, moreText, maxItemsVisible };
    }, scope);
}

/** Places a one-item order on a wide counter screen (name field and product grid visible). */
async function placeOrder(page, customerName, productName = 'Filé de frango') {
    await page.locator('[data-product-tile]', { hasText: productName }).first().click();
    const name = page.locator('[x-ref="name"]');
    await name.fill(customerName);
    await name.press('Enter');
    await page.locator('text=enviado!').first().waitFor({ timeout: 10000 });
    await page.waitForTimeout(300);
}

module.exports = {
    BASE_URL,
    fail,
    launch,
    loginState,
    open,
    expectNoErrors,
    expectNoHorizontalScroll,
    expectInViewport,
    expectLastProductReachable,
    kitchenStatus,
    placeOrder,
};
