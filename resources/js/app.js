import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';
import { registerOrderDraft } from './order-draft';
import { registerKitchen } from './kitchen';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
    registerOrderDraft(Alpine);
    registerKitchen(Alpine);
});

registerNetworkErrors();
