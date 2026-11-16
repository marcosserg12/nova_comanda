export function registerConnection(Alpine) {
    Alpine.store('conn', { online: true });

    const pusher = window.Echo?.connector?.pusher;
    if (!pusher) {
        return;
    }

    let wasDown = false;
    pusher.connection.bind('state_change', ({ current }) => {
        const online = current === 'connected';
        Alpine.store('conn').online = online;
        if (online && wasDown) {
            window.Livewire?.dispatch('realtime-reconnected');
        }
        if (!online) {
            wasDown = true;
        }
    });
}
