let audioCtx = null;
let wakeLock = null;
let keepingAwake = false;

export function audio() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

function tone(freq, start, duration, volume = 0.3) {
    const ctx = audio();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0, ctx.currentTime + start);
    gain.gain.linearRampToValueAtTime(volume, ctx.currentTime + start + 0.02);
    gain.gain.linearRampToValueAtTime(0, ctx.currentTime + start + duration);
    osc.connect(gain).connect(ctx.destination);
    osc.start(ctx.currentTime + start);
    osc.stop(ctx.currentTime + start + duration + 0.05);
}

/** Short confirmation sound of the TV "Toque para começar" gate. */
export function beep() {
    tone(880, 0, 0.18);
    tone(880, 0.25, 0.18);
}

export function chime() {
    tone(660, 0, 0.35, 0.4);
    tone(880, 0.3, 0.35, 0.4);
    tone(1320, 0.6, 0.6, 0.4);
}

export async function requestWakeLock() {
    try {
        if ('wakeLock' in navigator && document.visibilityState === 'visible') {
            wakeLock = await navigator.wakeLock.request('screen');
        }
    } catch (e) {
        // sem suporte ou negado: segue sem travar a tela
    }
}

/** Keeps the screen on: asks now and again whenever the page becomes visible. Needs no user gesture. */
export function keepScreenAwake() {
    if (keepingAwake) {
        return;
    }
    keepingAwake = true;
    requestWakeLock();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            requestWakeLock();
        }
    });
}

export function registerDevice(Alpine) {
    Alpine.data('deviceGate', (fullscreen = false) => ({
        open: true,
        init() {
            // Fully Kiosk / Chrome com autoplay liberado: o áudio já nasce destravado.
            if (audio().state === 'running') {
                keepScreenAwake();
                this.open = false;
            }
        },
        async start() {
            await audio().resume();
            beep();
            keepScreenAwake();
            if (fullscreen && document.documentElement.requestFullscreen) {
                try {
                    await document.documentElement.requestFullscreen();
                } catch (e) {
                    // tela cheia recusada: segue normal
                }
            }
            this.open = false;
        },
    }));

    Alpine.data('clock', () => ({
        time: '',
        init() {
            const tick = () => {
                this.time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            };
            tick();
            setInterval(tick, 10000);
        },
    }));
}
