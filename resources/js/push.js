/**
 * Browser side of Ustawienia → Powiadomienia (docs/ARCHITECTURE.md §6).
 * Detects what this browser can do, explains the iPhone rule (push works
 * only after "Dodaj do ekranu początkowego", iOS 16.4+), asks for
 * permission only after a tap, subscribes with the VAPID key and hands the
 * subscription to Livewire.
 */
export default ({ vapidKey }) => ({
    supported: false,
    ios: false,
    standalone: false,
    permission: 'default',
    endpoint: null,
    busy: false,
    message: '',
    installable: false,

    async init() {
        const ua = navigator.userAgent;
        this.ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        this.standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        this.supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        this.permission = 'Notification' in window ? Notification.permission : 'unsupported';
        this.installable = !!window.mciInstallPrompt;
        window.addEventListener('mci:installable', () => (this.installable = true));

        if (this.supported) {
            const reg = await navigator.serviceWorker.getRegistration('/');
            const sub = reg ? await reg.pushManager.getSubscription() : null;
            this.endpoint = sub?.endpoint ?? null;
        }
    },

    get state() {
        if (!vapidKey) return 'off';
        if (this.ios && !this.standalone) return 'ios-install';
        if (!this.supported) return 'unsupported';
        if (this.permission === 'denied') return 'denied';
        return this.endpoint ? 'on' : 'ready';
    },

    async enable() {
        this.busy = true;
        this.message = '';
        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            await navigator.serviceWorker.ready;
            this.permission = await Notification.requestPermission();
            if (this.permission !== 'granted') {
                this.message = 'Bez zgody przeglądarki nie możemy wysyłać powiadomień.';
                return;
            }
            const sub = (await reg.pushManager.getSubscription())
                || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64ToBytes(vapidKey) }));
            await this.$wire.subscribe(sub.toJSON(), deviceLabel(), platform(this.ios));
            this.endpoint = sub.endpoint;
        } catch (e) {
            this.message = 'Nie udało się włączyć powiadomień: ' + (e?.message || e);
        } finally {
            this.busy = false;
        }
    },

    async disable() {
        this.busy = true;
        try {
            const reg = await navigator.serviceWorker.getRegistration('/');
            const sub = reg ? await reg.pushManager.getSubscription() : null;
            if (sub) {
                await this.$wire.unsubscribeEndpoint(sub.endpoint);
                await sub.unsubscribe();
            }
            this.endpoint = null;
        } finally {
            this.busy = false;
        }
    },

    async install() {
        const prompt = window.mciInstallPrompt;
        if (!prompt) return;
        prompt.prompt();
        await prompt.userChoice;
        window.mciInstallPrompt = null;
        this.installable = false;
    },
});

function base64ToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

function platform(ios) {
    if (ios) return 'ios';
    return /Android/i.test(navigator.userAgent) ? 'android' : 'desktop';
}

function deviceLabel() {
    const ua = navigator.userAgent;
    const os = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android'
        : /Windows/.test(ua) ? 'Windows' : /Mac OS X/.test(ua) ? 'Mac' : /Linux/.test(ua) ? 'Linux' : 'Urządzenie';
    const browser = /Edg\//.test(ua) ? 'Edge' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome'
        : /Safari\//.test(ua) ? 'Safari' : 'przeglądarka';
    return `${os} · ${browser}`;
}
