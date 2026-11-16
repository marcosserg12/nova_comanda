export function registerNetworkErrors() {
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                preventDefault();

                let message = 'Algo deu errado. Aperte F5 para recarregar.';
                if (status === 0 || status === 503) {
                    message = 'Sem conexão. Nada foi perdido: tente de novo.';
                } else if (status === 419) {
                    message = 'Sua sessão expirou. Aperte F5.';
                }

                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message, timeout: 6000 },
                }));
            });
        });
    });
}
