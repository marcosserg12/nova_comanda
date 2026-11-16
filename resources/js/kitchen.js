import { beep } from './device';

const UNDO_MS = 10000;

export function registerKitchen(Alpine) {
    Alpine.data('kitchenBoard', () => ({
        selected: 0,
        typed: '',
        lastReady: null,
        undoBar: '',
        undoTimer: null,
        keyHandler: null,
        init() {
            this.keyHandler = (event) => this.onKey(event);
            window.addEventListener('keydown', this.keyHandler, { capture: true });
        },
        destroy() {
            window.removeEventListener('keydown', this.keyHandler, { capture: true });
        },
        cards() {
            return [...this.$root.querySelectorAll('[data-order-id]')];
        },
        beep() {
            beep();
        },
        notFound(number) {
            this.undoBar = '';
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: `Pedido ${number} não está na fila.` } }));
        },
        onKey(event) {
            if (!event.code || !event.code.startsWith('Numpad')) {
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
    }));
}
