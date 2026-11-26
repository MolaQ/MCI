/**
 * Image editor used for every logo and content image (docs/ARCHITECTURE.md §12.5).
 *
 * Everything happens on a <canvas> in the browser, without libraries:
 * crop frame with aspect presets, zoom (slider, wheel, pinch), pan by
 * dragging, rotate / flip, reset, and background removal — either a colour
 * connected to the edges (flood fill, keeps inner white details) or every
 * pixel of that colour — with tolerance and soft edges. The result is
 * exported as WebP (PNG fallback) and uploaded with $wire.upload(); the
 * server re-validates and re-encodes it (App\Domain\Media\Actions\StoreImage).
 *
 * Usage: x-data="mciImageEditor({ model: 'logoUpload', aspect: 'free', maxSize: 1024 })"
 */
const WORK_MAX = 2048;

export default (options = {}) => ({
    model: options.model,
    presets: options.presets ?? ['free', '1:1', '4:3', '16:9', '3:1'],
    aspect: options.aspect ?? 'free',
    maxSize: options.maxSize ?? 1600,
    allowBackground: options.background ?? true,
    // Optional mask: 'square' (default) or 'circle' (outside the circle becomes transparent).
    shapes: options.shapes ?? [],
    shape: (options.shapes ?? [])[0] ?? 'square',
    // Gallery mode: each result is handed to the server and the picker resets.
    gallery: options.gallery ?? false,
    open: false,
    busy: false,
    progress: 0,
    error: '',
    fileName: '',
    resultUrl: null,
    // transform
    rotation: 0,
    flipX: false,
    flipY: false,
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    // background removal
    bgMode: 'off', // off | edges | all
    bgColor: '#ffffff',
    tolerance: 12,
    softness: 6,
    picking: false,
    format: 'webp',
    // internals (not reactive-heavy)
    _wire: null,
    _source: null,
    _base: null,
    _work: null,
    _drag: null,
    _pinch: null,
    _pending: null,

    init() {
        // Keep the Livewire handle: the editor overlay is teleported to <body>.
        this._wire = this.$wire;
        this.$watch('aspect', () => this.$nextTick(() => this.fitFrame()));
        ['bgMode', 'bgColor', 'tolerance', 'softness'].forEach((key) => this.$watch(key, () => this.rebuildSoon()));
        ['zoom', 'offsetX', 'offsetY', 'shape'].forEach((key) => this.$watch(key, () => this.draw()));
        this._onResize = () => this.open && this.fitFrame();
        window.addEventListener('resize', this._onResize);
    },

    destroy() {
        window.removeEventListener('resize', this._onResize);
        if (this.resultUrl) URL.revokeObjectURL(this.resultUrl);
    },

    choose() {
        this.$refs.file.value = '';
        this.$refs.file.click();
    },

    async load(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        this.error = '';
        if (!/^image\/(png|jpe?g|webp|gif|bmp)$/i.test(file.type)) {
            this.error = 'Wybierz obraz PNG, JPG, WebP lub GIF.';
            return;
        }
        try {
            this._source = await this.decode(file);
        } catch {
            this.error = 'Nie udało się otworzyć obrazu.';
            return;
        }
        this.fileName = file.name.replace(/\.[^.]+$/, '');
        this.reset(false);
        this.bgColor = this.cornerColor();
        this.open = true;
        document.documentElement.classList.add('overflow-hidden');
        this.$nextTick(() => {
            this.rebuild();
            this.fitFrame();
        });
    },

    async decode(file) {
        if ('createImageBitmap' in window) {
            try {
                return await createImageBitmap(file, { imageOrientation: 'from-image' });
            } catch {
                /* fall through to <img> */
            }
        }
        const url = URL.createObjectURL(file);
        try {
            const img = new Image();
            img.src = url;
            await img.decode();
            return img;
        } finally {
            URL.revokeObjectURL(url);
        }
    },

    close() {
        this.open = false;
        this.picking = false;
        document.documentElement.classList.remove('overflow-hidden');
    },

    reset(redraw = true) {
        this.rotation = 0;
        this.flipX = false;
        this.flipY = false;
        this.zoom = 1;
        this.offsetX = 0;
        this.offsetY = 0;
        this.bgMode = 'off';
        this.tolerance = 12;
        this.softness = 6;
        this.format = 'webp';
        if (redraw) this.rebuild();
    },

    rotate(deg) {
        this.rotation = (this.rotation + deg + 360) % 360;
        this.rebuild();
    },

    flip(axis) {
        axis === 'x' ? (this.flipX = !this.flipX) : (this.flipY = !this.flipY);
        this.rebuild();
    },

    /* ---------- work canvas: rotation, flip, background removal ---------- */

    rebuildSoon() {
        clearTimeout(this._pending);
        this._pending = setTimeout(() => this.rebuild(), 60);
    },

    rebuild() {
        const src = this._source;
        if (!src) return;
        const sw = src.width;
        const sh = src.height;
        const scale = Math.min(1, WORK_MAX / Math.max(sw, sh));
        const w = Math.round(sw * scale);
        const h = Math.round(sh * scale);
        const turned = this.rotation % 180 !== 0;
        const base = document.createElement('canvas');
        base.width = turned ? h : w;
        base.height = turned ? w : h;
        const ctx = base.getContext('2d', { willReadFrequently: true });
        ctx.translate(base.width / 2, base.height / 2);
        ctx.rotate((this.rotation * Math.PI) / 180);
        ctx.scale(this.flipX ? -1 : 1, this.flipY ? -1 : 1);
        ctx.drawImage(src, -w / 2, -h / 2, w, h);
        this._base = base;

        if (this.bgMode === 'off') {
            this._work = base;
        } else {
            const work = document.createElement('canvas');
            work.width = base.width;
            work.height = base.height;
            const wctx = work.getContext('2d', { willReadFrequently: true });
            wctx.drawImage(base, 0, 0);
            this.removeBackground(wctx, work.width, work.height);
            this._work = work;
        }
        this.draw();
    },

    removeBackground(ctx, w, h) {
        const image = ctx.getImageData(0, 0, w, h);
        const d = image.data;
        const [r0, g0, b0] = hexToRgb(this.bgColor);
        const max = 441.67; // distance black → white
        const hard = (this.tolerance / 100) * max;
        const soft = hard + (this.softness / 100) * max;
        const distance = (i) => Math.hypot(d[i] - r0, d[i + 1] - g0, d[i + 2] - b0);
        const apply = (i, dist) => {
            if (dist <= hard) d[i + 3] = 0;
            else if (dist < soft) d[i + 3] = Math.min(d[i + 3], Math.round((255 * (dist - hard)) / (soft - hard)));
        };

        if (this.bgMode === 'all') {
            for (let i = 0; i < d.length; i += 4) apply(i, distance(i));
        } else {
            // Flood fill from every edge pixel through pixels close to the colour.
            const seen = new Uint8Array(w * h);
            const stack = [];
            const push = (x, y) => {
                const p = y * w + x;
                if (!seen[p]) {
                    seen[p] = 1;
                    stack.push(p);
                }
            };
            for (let x = 0; x < w; x++) {
                push(x, 0);
                push(x, h - 1);
            }
            for (let y = 0; y < h; y++) {
                push(0, y);
                push(w - 1, y);
            }
            while (stack.length) {
                const p = stack.pop();
                const i = p * 4;
                const dist = distance(i);
                if (dist >= soft) continue;
                apply(i, dist);
                const x = p % w;
                const y = (p - x) / w;
                if (x > 0) push(x - 1, y);
                if (x < w - 1) push(x + 1, y);
                if (y > 0) push(x, y - 1);
                if (y < h - 1) push(x, y + 1);
            }
        }
        ctx.putImageData(image, 0, 0);
    },

    cornerColor() {
        const src = this._source;
        if (!src) return '#ffffff';
        const c = document.createElement('canvas');
        c.width = src.width;
        c.height = src.height;
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(src, 0, 0);
        const pts = [
            [0, 0],
            [src.width - 1, 0],
            [0, src.height - 1],
            [src.width - 1, src.height - 1],
        ];
        const sum = [0, 0, 0];
        pts.forEach(([x, y]) => {
            const px = ctx.getImageData(x, y, 1, 1).data;
            sum[0] += px[0];
            sum[1] += px[1];
            sum[2] += px[2];
        });
        return rgbToHex(...sum.map((v) => Math.round(v / pts.length)));
    },

    /* ---------- frame (crop area) and preview ---------- */

    ratio() {
        if (this.aspect === 'free') {
            return this._work ? this._work.width / this._work.height : 1;
        }
        const [a, b] = this.aspect.split(':').map(Number);
        return a / b;
    },

    fitFrame() {
        const stage = this.$refs.stage;
        const canvas = this.$refs.preview;
        if (!stage || !canvas) return;
        const maxW = stage.clientWidth - 16;
        const maxH = Math.max(180, stage.clientHeight - 16);
        const r = this.ratio();
        let w = maxW;
        let h = w / r;
        if (h > maxH) {
            h = maxH;
            w = h * r;
        }
        const dpr = window.devicePixelRatio || 1;
        canvas.style.width = `${Math.round(w)}px`;
        canvas.style.height = `${Math.round(h)}px`;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        this.draw();
    },

    /** Scale that fits the whole work image inside a frame of width fw. */
    baseScale(fw, fh) {
        const work = this._work;
        return Math.min(fw / work.width, fh / work.height);
    },

    fill() {
        const canvas = this.$refs.preview;
        if (!this._work || !canvas) return;
        const contain = this.baseScale(canvas.width, canvas.height);
        const cover = Math.max(canvas.width / this._work.width, canvas.height / this._work.height);
        this.offsetX = 0;
        this.offsetY = 0;
        this.zoom = +(cover / contain).toFixed(3);
    },

    renderTo(ctx, fw, fh) {
        const work = this._work;
        const s = this.baseScale(fw, fh) * this.zoom;
        ctx.clearRect(0, 0, fw, fh);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(
            work,
            fw / 2 + this.offsetX * fw - (work.width * s) / 2,
            fh / 2 + this.offsetY * fh - (work.height * s) / 2,
            work.width * s,
            work.height * s,
        );
        if (this.shape === 'circle') {
            ctx.save();
            ctx.globalCompositeOperation = 'destination-in';
            ctx.beginPath();
            ctx.arc(fw / 2, fh / 2, Math.min(fw, fh) / 2, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        }
    },

    draw() {
        const canvas = this.$refs.preview;
        if (!canvas || !this._work) return;
        this.renderTo(canvas.getContext('2d'), canvas.width, canvas.height);
    },

    /* ---------- pointer: pan, pinch, wheel, colour picker ---------- */

    pointerDown(e) {
        if (this.picking) return this.pick(e);
        e.currentTarget.setPointerCapture(e.pointerId);
        this._drag = { x: e.clientX, y: e.clientY, ox: this.offsetX, oy: this.offsetY, id: e.pointerId };
    },

    pointerMove(e) {
        if (!this._drag || this._drag.id !== e.pointerId) return;
        const rect = e.currentTarget.getBoundingClientRect();
        this.offsetX = this._drag.ox + (e.clientX - this._drag.x) / rect.width;
        this.offsetY = this._drag.oy + (e.clientY - this._drag.y) / rect.height;
    },

    pointerUp() {
        this._drag = null;
    },

    wheel(e) {
        this.setZoom(this.zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08));
    },

    touchStart(e) {
        if (e.touches.length === 2) {
            this._drag = null;
            this._pinch = { d: touchDistance(e.touches), z: this.zoom };
        }
    },

    touchMove(e) {
        if (this._pinch && e.touches.length === 2) {
            this.setZoom(this._pinch.z * (touchDistance(e.touches) / this._pinch.d));
        }
    },

    touchEnd() {
        this._pinch = null;
    },

    setZoom(value) {
        this.zoom = +Math.min(8, Math.max(0.2, value)).toFixed(3);
    },

    pick(e) {
        const canvas = this.$refs.preview;
        const base = this._base;
        if (!base) return;
        const rect = canvas.getBoundingClientRect();
        const fw = canvas.width;
        const fh = canvas.height;
        const cx = ((e.clientX - rect.left) / rect.width) * fw;
        const cy = ((e.clientY - rect.top) / rect.height) * fh;
        const sc = this.baseScale(fw, fh) * this.zoom;
        const x = Math.floor((cx - (fw / 2 + this.offsetX * fw - (base.width * sc) / 2)) / sc);
        const y = Math.floor((cy - (fh / 2 + this.offsetY * fh - (base.height * sc) / 2)) / sc);
        this.picking = false;
        if (x < 0 || y < 0 || x >= base.width || y >= base.height) return;
        const px = base.getContext('2d').getImageData(x, y, 1, 1).data;
        if (px[3] > 0) this.bgColor = rgbToHex(px[0], px[1], px[2]);
        if (this.bgMode === 'off') this.bgMode = 'edges';
    },

    /* ---------- export + upload ---------- */

    outputSize() {
        const canvas = this.$refs.preview;
        // Native resolution of the cropped area, capped by maxSize.
        const s = this.baseScale(canvas.width, canvas.height) * this.zoom;
        const nativeW = canvas.width / s;
        const nativeH = canvas.height / s;
        const cap = Math.min(1, this.maxSize / Math.max(nativeW, nativeH));
        return [Math.max(1, Math.round(nativeW * cap)), Math.max(1, Math.round(nativeH * cap))];
    },

    async apply() {
        if (!this._work || this.busy) return;
        this.busy = true;
        this.error = '';
        const [w, h] = this.outputSize();
        const out = document.createElement('canvas');
        out.width = w;
        out.height = h;
        this.renderTo(out.getContext('2d'), w, h);
        const type = this.format === 'png' ? 'image/png' : 'image/webp';
        let blob = await new Promise((r) => out.toBlob(r, type, 0.9));
        if (!blob) blob = await new Promise((r) => out.toBlob(r, 'image/png'));
        const ext = blob.type === 'image/webp' ? 'webp' : 'png';
        const file = new File([blob], `${this.fileName || 'obraz'}.${ext}`, { type: blob.type });

        this.progress = 0;
        this._wire.upload(
            this.model,
            file,
            () => {
                this.busy = false;
                if (this.resultUrl) URL.revokeObjectURL(this.resultUrl);
                this.resultUrl = this.gallery ? null : URL.createObjectURL(blob);
                this.close();
            },
            () => {
                this.busy = false;
                this.error = 'Nie udało się wysłać obrazu. Sprawdź połączenie i rozmiar pliku.';
            },
            (event) => (this.progress = event.detail.progress),
        );
    },

    discard() {
        if (this.resultUrl) URL.revokeObjectURL(this.resultUrl);
        this.resultUrl = null;
        this._wire.set(this.model, null);
    },
});

function hexToRgb(hex) {
    const v = parseInt(hex.replace('#', ''), 16);
    return [(v >> 16) & 255, (v >> 8) & 255, v & 255];
}

function rgbToHex(r, g, b) {
    return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
}

function touchDistance(t) {
    return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
}
