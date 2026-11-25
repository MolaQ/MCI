import mciEditor from './editor.js';
import mciImageEditor from './image-editor.js';

/*
 * Small Alpine helpers. Livewire ships Alpine; we only register components.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('mciEditor', mciEditor);
    window.Alpine.data('mciImageEditor', mciImageEditor);

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
