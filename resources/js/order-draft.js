const KEY = 'nana.draft';

function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; }
}

function write(value) {
    try { localStorage.setItem(KEY, JSON.stringify(value)); } catch (e) { /* armazenamento indisponível */ }
}

function clear() {
    try { localStorage.removeItem(KEY); } catch (e) { /* armazenamento indisponível */ }
}

export function registerOrderDraft(Alpine) {
    Alpine.data('orderDraft', () => ({
        timer: null,
        ready: false,
        restoring: false,
        async restore() {
            const draft = read();
            if (!draft) {
                this.ready = true;
                return;
            }
            if (this.restoring) {
                return;
            }
            this.restoring = true;
            try {
                await this.$wire.restoreDraft(draft);
                this.ready = true;
            } catch (e) {
                /* keep the stored draft; retried on the next tick */
            } finally {
                this.restoring = false;
            }
        },
        init() {
            this.restore();
            window.addEventListener('draft-clear', clear);
            this.timer = setInterval(() => this.persist(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
            window.removeEventListener('draft-clear', clear);
        },
        persist() {
            if (!this.ready) {
                this.restore();
                return;
            }
            if (this.$wire.editingOrderId) {
                return;
            }
            const lines = this.$wire.lines || [];
            const customerName = this.$wire.customerName || '';
            if (!customerName && lines.length === 0) {
                clear();
                return;
            }
            write({ uuid: this.$wire.uuid, customerName, takeaway: this.$wire.takeaway, lines });
        },
    }));
}
