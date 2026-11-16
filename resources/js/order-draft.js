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
        init() {
            const draft = read();
            if (draft) {
                this.$wire.restoreDraft(draft);
            }
            window.addEventListener('draft-clear', clear);
            this.timer = setInterval(() => this.persist(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
            window.removeEventListener('draft-clear', clear);
        },
        persist() {
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
