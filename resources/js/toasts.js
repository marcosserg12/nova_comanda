export function registerToasts(Alpine) {
    Alpine.data('toasts', () => ({
        items: [],
        push(detail) {
            const toast = {
                id: Date.now() + Math.random(),
                type: detail.type ?? 'success',
                message: detail.message,
                action: detail.action ?? null,
            };
            this.items.push(toast);
            setTimeout(() => this.dismiss(toast.id), detail.timeout ?? 4000);
        },
        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    }));
}
