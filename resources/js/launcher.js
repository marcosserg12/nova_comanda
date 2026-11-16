const KEY = 'nana.home';

function read() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
}

function write(value) {
    try { localStorage.setItem(KEY, value); } catch (e) { /* armazenamento indisponível */ }
}

function clear() {
    try { localStorage.removeItem(KEY); } catch (e) { /* armazenamento indisponível */ }
}

export function registerLauncher(Alpine) {
    Alpine.data('launcher', (allowed = {}, choosing = false) => ({
        remember: false,
        stored: null,
        redirecting: false,
        init() {
            this.stored = read();
            if (!choosing && this.stored && Object.hasOwn(allowed, this.stored)) {
                this.redirecting = true;
                window.location.replace(allowed[this.stored]);
            }
        },
        choose(key) {
            if (this.remember && key !== 'login') {
                write(key);
                this.stored = key;
            }
        },
        forget() {
            clear();
            this.stored = null;
            this.remember = false;
        },
    }));
}
