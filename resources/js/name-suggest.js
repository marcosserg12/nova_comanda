const LIMIT = 6;

/** "  João  da SILVA" → "joao da silva": comparisons ignore accents, case and extra spaces. */
export function normalize(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

const PARTICLES = new Set(['da', 'das', 'de', 'do', 'dos', 'e']);

/** Same rule as the server: words typed in lower case get a capital letter ("zuleide" → "Zuleide"). */
export function capitalize(text) {
    return text
        .split(' ')
        .map((word, i) => (word === word.toLowerCase() && !(i > 0 && PARTICLES.has(word))
            ? word.charAt(0).toUpperCase() + word.slice(1)
            : word))
        .join(' ');
}

/** @param {string[]} names @returns {{name: string, key: string}[]} */
export function prepare(names) {
    return names.map((name) => ({ name, key: normalize(name) }));
}

/**
 * Up to `limit` suggestions for what was typed, in list order (names already used come first):
 * whole names starting with the text ("Maria Cl" → "Maria Clara"), then, after the first word,
 * the last word completed with a common surname ("Bianca S" → "Bianca Silva").
 */
export function suggestNames(entries, surnames, value, limit = LIMIT) {
    const typed = normalize(value);
    if (typed === '') {
        return [];
    }

    const out = [];
    const seen = new Set([typed]);
    const add = (name, key) => {
        if (!seen.has(key)) {
            seen.add(key);
            out.push(name);
        }
    };
    // A trailing space means the word is finished: "Maria " suggests "Maria Clara", not "Mariana".
    const prefix = /\s$/.test(value) ? `${typed} ` : typed;

    for (const entry of entries) {
        if (out.length >= limit) {
            return out;
        }
        if (entry.key.startsWith(prefix)) {
            add(entry.name, entry.key);
        }
    }

    const text = String(value).replace(/\s+/g, ' ').replace(/^ /, '');
    const lastSpace = text.lastIndexOf(' ');
    const word = lastSpace > 0 ? normalize(text.slice(lastSpace + 1)) : '';
    if (word === '') {
        return out;
    }

    const head = capitalize(text.slice(0, lastSpace).trim());
    const headKey = normalize(head);
    for (const entry of surnames) {
        if (out.length >= limit) {
            break;
        }
        if (entry.key.startsWith(word)) {
            add(`${head} ${entry.name}`, `${headKey} ${entry.key}`);
        }
    }

    return out;
}

let entries = [];
let surnameEntries = [];
let pending = null;

function load() {
    pending ??= fetch('/balcao/nomes', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : { names: null }))
        .then((data) => {
            if (Array.isArray(data.names)) {
                entries = prepare(data.names);
            }
            if (Array.isArray(data.surnames)) {
                surnameEntries = prepare(data.surnames);
            }
        })
        .catch(() => { /* sem sugestões até a próxima tentativa */ })
        .finally(() => { pending = null; });

    return pending;
}

export function registerNameSuggest(Alpine) {
    Alpine.data('nameSuggest', () => ({
        open: false,
        items: [],
        active: -1,
        init() {
            load();
        },
        /** After an order is sent: refresh the list (it now has that name) and go back to the name field. */
        orderSent() {
            this.close();
            load();
            this.$nextTick(() => this.$refs.name?.focus());
        },
        update() {
            this.items = suggestNames(entries, surnameEntries, this.$refs.name.value);
            this.active = -1;
            this.open = this.items.length > 0;
        },
        close() {
            this.open = false;
            this.active = -1;
        },
        move(step) {
            if (!this.open) {
                return;
            }
            const count = this.items.length;
            this.active = this.active < 0 ? (step > 0 ? 0 : count - 1) : (this.active + step + count) % count;
        },
        choose(name, fromKeyboard = false) {
            const input = this.$refs.name;
            input.value = name;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            this.confirm(fromKeyboard);
        },
        /** The ✓ button and Enter: the name is the one typed. Never sends the order. */
        confirm(fromKeyboard = false) {
            this.close();
            const search = document.querySelector('[data-product-search]');
            if (fromKeyboard && search && search.offsetParent !== null) {
                search.focus();
            } else {
                this.$refs.name.blur();
            }
        },
        enter(event) {
            if (this.open && this.active >= 0) {
                event.preventDefault();
                this.choose(this.items[this.active], true);
                return;
            }
            const lines = this.$wire.lines;
            const count = Array.isArray(lines) ? lines.length : Object.keys(lines ?? {}).length;
            if (count === 0) {
                // No dishes yet: Enter only confirms the name (it used to show "Adicione pelo menos um item").
                event.preventDefault();
                this.confirm(true);
                return;
            }
            this.close(); // with dishes, Enter keeps sending the order
        },
        escape(event) {
            if (this.open) {
                event.stopPropagation();
                this.close();
            }
        },
    }));
}
