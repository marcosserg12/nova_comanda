const WIDE = 1280;

export function registerBalcao(Alpine) {
    Alpine.data('balcaoPane', () => ({
        tab: 'novo',
        kitchenCount: 0,
        observer: null,
        init() {
            // "Na cozinha" is a tab only below 1280 px; when the pane widens the column is back on screen.
            this.observer = new ResizeObserver(([entry]) => {
                if (entry.contentRect.width >= WIDE && this.tab === 'cozinha') {
                    this.tab = 'novo';
                }
            });
            this.observer.observe(this.$root);
        },
        destroy() {
            this.observer?.disconnect();
        },
    }));
}
