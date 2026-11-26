import mciEditor from './editor.js';
import mciImageEditor from './image-editor.js';
import mciPush from './push.js';

/*
 * Small Alpine helpers. Livewire ships Alpine; we only register components.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('mciEditor', mciEditor);
    window.Alpine.data('mciImageEditor', mciImageEditor);
    window.Alpine.data('mciPush', mciPush);

    // Full-screen / TV toggle with one-key exit (docs/ARCHITECTURE.md §12.3).
    window.Alpine.data('mciFullscreen', () => ({
        active: false,
        init() {
            document.addEventListener('fullscreenchange', () => { this.active = !!document.fullscreenElement; });
        },
        toggle(el = document.documentElement) {
            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            } else {
                el.requestFullscreen?.().catch(() => {});
            }
        },
    }));

    // TV board: screens in turn, each for its own number of seconds.
    // ← → switch, space pauses; one screen = no rotation.
    window.Alpine.data('mciTvRotator', (screens = []) => ({
        screens,
        index: 0,
        paused: false,
        elapsed: 0,
        get current() { return this.screens[this.index]?.key; },
        get duration() { return (this.screens[this.index]?.seconds || 20) * 1000; },
        get progress() { return Math.min(100, (this.elapsed / this.duration) * 100); },
        init() {
            const params = new URLSearchParams(window.location.search);
            const start = this.screens.findIndex((s) => s.key === params.get('ekran'));
            this.index = start >= 0 ? start : 0;
            this.timer = setInterval(() => {
                if (this.paused || this.screens.length < 2 || document.hidden) return;
                this.elapsed += 250;
                if (this.elapsed >= this.duration) this.go(this.index + 1);
            }, 250);
            this.keys = (e) => {
                if (e.key === 'ArrowRight') this.go(this.index + 1);
                else if (e.key === 'ArrowLeft') this.go(this.index - 1);
                else if (e.key === ' ') { e.preventDefault(); this.paused = !this.paused; }
            };
            window.addEventListener('keydown', this.keys);
        },
        go(i) {
            const n = this.screens.length || 1;
            this.index = ((i % n) + n) % n;
            this.elapsed = 0;
        },
        destroy() { clearInterval(this.timer); window.removeEventListener('keydown', this.keys); },
    }));

    // Live clock for TV / board views (Europe/Warsaw).
    window.Alpine.data('mciClock', () => ({
        time: '',
        date: '',
        init() {
            const tick = () => {
                const now = new Date();
                this.time = now.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Warsaw' });
                this.date = now.toLocaleDateString('pl-PL', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'Europe/Warsaw' });
            };
            tick();
            this.timer = setInterval(tick, 15000);
        },
        destroy() { clearInterval(this.timer); },
    }));
});

/*
 * Installable app (Step 4): register the service worker on every page and
 * keep the browser's install prompt for the "Zainstaluj aplikację" button.
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.mciInstallPrompt = event;
    window.dispatchEvent(new Event('mci:installable'));
});
