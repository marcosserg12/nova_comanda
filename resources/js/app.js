import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
});

registerNetworkErrors();
