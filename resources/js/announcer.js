import { chime } from './device';

export function registerAnnouncer(Alpine) {
    Alpine.data('announcer', (durationMs = 8000) => ({
        queue: [],
        current: null,
        progress: 0,
        timer: null,
        enqueue({ orderId, number, name }) {
            const known = this.current?.orderId === orderId || this.queue.some((entry) => entry.orderId === orderId);
            if (known) {
                return;
            }
            this.queue.push({ orderId, number, name });
            if (!this.current) {
                this.next();
            }
        },
        cancel(orderId) {
            this.queue = this.queue.filter((entry) => entry.orderId !== orderId);
        },
        next() {
            this.current = this.queue.shift() ?? null;
            if (!this.current) {
                return;
            }
            try { chime(); } catch (e) { /* áudio indisponível */ }
            const started = Date.now();
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                this.progress = Math.min(100, ((Date.now() - started) / durationMs) * 100);
                if (this.progress >= 100) {
                    clearInterval(this.timer);
                    this.progress = 0;
                    this.current = null;
                    this.$nextTick(() => this.next());
                }
            }, 100);
        },
    }));

    Alpine.data('trackMine', () => ({
        mine: '',
        alert: false,
        init() {
            try { this.mine = localStorage.getItem('nana.mine') || ''; } catch (e) { this.mine = ''; }
            this.$watch('mine', (value) => {
                try { localStorage.setItem('nana.mine', value); } catch (e) { /* armazenamento indisponível */ }
            });
        },
        isMine(number) {
            return this.mine !== '' && number === String(parseInt(this.mine, 10)).padStart(3, '0');
        },
        onAnnounce({ number }) {
            if (!this.isMine(number)) {
                return;
            }
            this.alert = true;
            if (navigator.vibrate) {
                navigator.vibrate([300, 150, 300, 150, 300]);
            }
        },
    }));
}
