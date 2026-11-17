const LEFT_KEY = 'nana.station.left';
const RIGHT_KEY = 'nana.station.right';
const KITCHEN_MIN = 600;
const SETTINGS_HINT = 'Ajuste a largura em Administração → Configurações.';

function stored(key, min) {
    try {
        const value = parseInt(localStorage.getItem(key) || '', 10);
        return Number.isInteger(value) && value >= min ? value : null;
    } catch (e) {
        return null;
    }
}

function store(key, value) {
    try { localStorage.setItem(key, String(value)); } catch (e) { /* armazenamento indisponível */ }
}

/** Screen width in this window's CSS pixels (monitors can use different Windows scaling). */
function cssWidth(screen) {
    return Math.round(screen.width * (screen.devicePixelRatio || 1) / (window.devicePixelRatio || 1));
}

/** The monitor touching the right edge of `left` and sharing part of its height. */
function rightNeighbour(screens, left) {
    const edge = left.left + left.width;
    return screens.find((s) => s !== left
        && Math.abs(s.left - edge) <= 2
        && Math.min(s.top + s.height, left.top + left.height) - Math.max(s.top, left.top) > 0) ?? null;
}

function toast(type, message) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type, message, timeout: 6000 } }));
}

export function registerStation(Alpine) {
    Alpine.data('station', (configuredLeft = 1366, configuredRight = 1366) => ({
        leftWidth: stored(LEFT_KEY, 800) ?? configuredLeft,
        rightWidth: stored(RIGHT_KEY, KITCHEN_MIN) ?? configuredRight,
        viewport: window.innerWidth,
        detecting: false,
        onResize: null,
        init() {
            this.onResize = () => { this.viewport = window.innerWidth; };
            window.addEventListener('resize', this.onResize);
        },
        destroy() {
            window.removeEventListener('resize', this.onResize);
        },
        get single() {
            return this.viewport < this.leftWidth + KITCHEN_MIN;
        },
        get columns() {
            return this.single ? 'minmax(0, 1fr)' : `${this.leftWidth}px minmax(0, ${this.rightWidth}px)`;
        },
        async detect() {
            if (!('getScreenDetails' in window)) {
                toast('error', `Este navegador não detecta os monitores. ${SETTINGS_HINT}`);
                return;
            }
            this.detecting = true;
            try {
                const details = await window.getScreenDetails();
                const screens = [...details.screens].sort((a, b) => a.left - b.left);
                const left = screens[0];
                const leftWidth = left ? cssWidth(left) : 0;
                if (!(leftWidth >= 800)) {
                    throw new Error('largura inválida');
                }
                this.leftWidth = leftWidth;
                store(LEFT_KEY, leftWidth);
                const right = rightNeighbour(screens, left);
                const rightWidth = right ? cssWidth(right) : 0;
                if (rightWidth >= KITCHEN_MIN) {
                    this.rightWidth = rightWidth;
                    store(RIGHT_KEY, rightWidth);
                }
                if (rightWidth >= KITCHEN_MIN) {
                    toast('success', `Monitor do balcão: ${leftWidth} px. Cozinha no monitor ao lado: ${rightWidth} px.`);
                } else if (screens.length > 1) {
                    toast('error', `Monitor do balcão: ${leftWidth} px, mas não há monitor colado à direita dele. Confira a posição dos monitores no Windows.`);
                } else {
                    toast('success', `Só um monitor encontrado (${leftWidth} px).`);
                }
            } catch (e) {
                toast('error', `Não foi possível detectar os monitores. ${SETTINGS_HINT}`);
            } finally {
                this.detecting = false;
            }
        },
    }));
}
