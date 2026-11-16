import { keepScreenAwake } from './device';

const UNDO_MS = 10000;
const HIGHLIGHT_MS = 60000;
const HIGHLIGHT_KINDS = ['created', 'updated'];

export function registerKitchen(Alpine) {
    Alpine.data('kitchenBoard', () => {
        const highlightTimers = new Map();
        let resizeObserver = null;
        let mutationObserver = null;
        let frame = null;

        return {
            selected: 0,
            typed: '',
            lastReady: null,
            undoBar: '',
            undoTimer: null,
            keyHandler: null,
            highlights: {},
            below: 0,
            init() {
                this.keyHandler = (event) => this.onKey(event);
                window.addEventListener('keydown', this.keyHandler, { capture: true });
                keepScreenAwake();
                this.$nextTick(() => {
                    const grid = this.grid();
                    if (!grid) {
                        return;
                    }
                    resizeObserver = new ResizeObserver(() => this.scheduleOverflow());
                    resizeObserver.observe(grid);
                    mutationObserver = new MutationObserver(() => this.scheduleOverflow());
                    mutationObserver.observe(grid, { childList: true, subtree: true, characterData: true });
                    this.updateOverflow();
                });
            },
            destroy() {
                window.removeEventListener('keydown', this.keyHandler, { capture: true });
                resizeObserver?.disconnect();
                mutationObserver?.disconnect();
                cancelAnimationFrame(frame);
                highlightTimers.forEach((timer) => clearTimeout(timer));
                highlightTimers.clear();
                clearTimeout(this.undoTimer);
            },
            grid() {
                return this.$root.querySelector('[data-kitchen-grid]');
            },
            cards() {
                return [...this.$root.querySelectorAll('[data-order-id]')];
            },
            isActive() {
                // Hidden board (Estação with one monitor): leave the numpad to the counter.
                return this.$root.offsetParent !== null;
            },
            highlight({ orderId, kind }) {
                const id = Number(orderId);
                if (!id || !HIGHLIGHT_KINDS.includes(kind)) {
                    return;
                }
                clearTimeout(highlightTimers.get(id));
                this.highlights[id] = kind;
                highlightTimers.set(id, setTimeout(() => this.acknowledge(id), HIGHLIGHT_MS));
            },
            acknowledge(id) {
                clearTimeout(highlightTimers.get(id));
                highlightTimers.delete(id);
                delete this.highlights[id];
            },
            isHighlighted(id) {
                return id in this.highlights;
            },
            highlightKind(id) {
                return this.highlights[id] ?? null;
            },
            scheduleOverflow() {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => this.updateOverflow());
            },
            updateOverflow() {
                const grid = this.grid();
                if (!grid) {
                    this.below = 0;
                    return;
                }
                const bottom = grid.getBoundingClientRect().bottom;
                this.below = this.cards().filter((card) => card.getBoundingClientRect().bottom > bottom + 1).length;
            },
            notFound(number) {
                this.undoBar = '';
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: `Pedido ${number} não está na fila.` } }));
            },
            onKey(event) {
                if (!event.code || !event.code.startsWith('Numpad') || !this.isActive()) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();

                const count = this.cards().length;
                if (this.selected > count - 1) {
                    this.selected = Math.max(0, count - 1);
                }

                const digit = event.code.match(/^Numpad(\d)$/);
                if (digit) {
                    this.typed = (this.typed + digit[1]).slice(-3);
                } else if (event.code === 'NumpadEnter') {
                    this.confirm();
                } else if (event.code === 'NumpadAdd') {
                    this.selected = count === 0 ? 0 : (this.selected + 1) % count;
                } else if (event.code === 'NumpadDecimal') {
                    this.typed = '';
                } else if (event.code === 'NumpadSubtract') {
                    this.undo();
                }
            },
            async confirm() {
                if (this.typed) {
                    const number = parseInt(this.typed, 10);
                    this.typed = '';
                    const id = await this.$wire.markReadyByNumber(number);
                    if (id) {
                        this.remember(id, String(number).padStart(3, '0'));
                    }
                    return;
                }
                const card = this.cards()[this.selected];
                if (card) {
                    this.ready(parseInt(card.dataset.orderId, 10), card.dataset.number);
                }
            },
            ready(id, number) {
                this.acknowledge(id);
                this.$wire.markReady(id);
                this.remember(id, number);
            },
            remember(id, number) {
                this.lastReady = { id, at: Date.now() };
                this.undoBar = `Pedido ${number} pronto`;
                clearTimeout(this.undoTimer);
                this.undoTimer = setTimeout(() => { this.undoBar = ''; this.lastReady = null; }, UNDO_MS);
            },
            undo() {
                if (!this.lastReady || Date.now() - this.lastReady.at > UNDO_MS) {
                    return;
                }
                this.$wire.undoReady(this.lastReady.id);
                this.lastReady = null;
                this.undoBar = '';
                clearTimeout(this.undoTimer);
            },
        };
    });
}
