import '@fontsource/archivo/500.css';
import '@fontsource/archivo/600.css';
import '@fontsource/archivo/700.css';
import '@fontsource/archivo/800.css';
import '@fontsource/archivo-black/400.css';
import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';
import { registerOrderDraft } from './order-draft';
import { registerKitchen } from './kitchen';
import { registerAnnouncer } from './announcer';
import { registerLauncher } from './launcher';
import { registerBalcao } from './balcao';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
    registerOrderDraft(Alpine);
    registerKitchen(Alpine);
    registerAnnouncer(Alpine);
    registerLauncher(Alpine);
    registerBalcao(Alpine);
});

registerNetworkErrors();
