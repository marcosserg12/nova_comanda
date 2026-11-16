export function registerNetworkErrors() {
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                preventDefault();
                if (status === 419) {
                    window.location.reload();
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: 'Sem conexão. Nada foi perdido: tente de novo.', timeout: 6000 },
                }));
            });
        });
    });
}
