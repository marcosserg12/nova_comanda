const KEY = 'nana.station.left';
const KITCHEN_MIN = 600;
const SETTINGS_HINT = 'Ajuste a largura em Administração → Configurações.';

function storedLeft() {
    try {
        const value = parseInt(localStorage.getItem(KEY) || '', 10);
        return Number.isInteger(value) && value >= 800 ? value : null;
    } catch (e) {
        return null;
    }
}

function storeLeft(value) {
    try { localStorage.setItem(KEY, String(value)); } catch (e) { /* armazenamento indisponível */ }
}

function toast(type, message) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type, message, timeout: 6000 } }));
}

export function registerStation(Alpine) {
    Alpine.data('station', (configuredLeft = 1366, configuredRight = 1366) => ({
        leftWidth: storedLeft() ?? configuredLeft,
        rightWidth: configuredRight,
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
                if (!screens.length || !(screens[0].width >= 800)) {
                    throw new Error('largura inválida');
                }
                this.leftWidth = screens[0].width;
                storeLeft(screens[0].width);
                toast('success', screens.length > 1
                    ? `Monitor do balcão: ${screens[0].width} px. Cozinha no monitor ao lado.`
                    : `Só um monitor encontrado (${screens[0].width} px).`);
            } catch (e) {
                toast('error', `Não foi possível detectar os monitores. ${SETTINGS_HINT}`);
            } finally {
                this.detecting = false;
            }
        },
    }));
}
