/*
 * Shared rich-text editor with @mentions (docs/ARCHITECTURE.md §12.1).
 *
 *   <div x-data="mciEditor({ property: 'body', searchUrl: '/wzmianki', placeholder: '…' })" wire:ignore> … </div>
 *
 * Tiptap is kept outside Alpine's reactive proxy (closure variable); the
 * HTML is pushed to the Livewire property on every change (deferred, so it
 * travels with the next request). The server sanitizes and re-validates
 * everything — this file is only convenience.
 */
// Tiptap is loaded on demand (separate chunk), so pages without an editor
// stay light — important on phones and the TV display.
const loadTiptap = () => Promise.all([
    import('@tiptap/core'),
    import('@tiptap/starter-kit'),
    import('@tiptap/extension-mention'),
    import('@tiptap/extension-placeholder'),
    import('@tiptap/extension-link'),
]).then(([core, starter, mention, placeholder, link]) => ({
    Editor: core.Editor,
    StarterKit: starter.default,
    Mention: mention.default,
    Placeholder: placeholder.default,
    Link: link.default,
}));

function suggestionPopup() {
    let el = null;
    let items = [];
    let selected = 0;
    let command = null;

    const draw = () => {
        if (!el) return;
        el.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'mci-mention-empty';
            empty.textContent = 'Brak wyników';
            el.appendChild(empty);
            return;
        }
        items.forEach((item, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mci-mention-item' + (i === selected ? ' is-selected' : '');
            btn.innerHTML = '<span class="mci-mention-kind"></span><span class="mci-mention-label"></span><span class="mci-mention-hint"></span>';
            btn.querySelector('.mci-mention-kind').textContent = item.kind === 'group' ? '#' : '@';
            btn.querySelector('.mci-mention-label').textContent = item.label;
            btn.querySelector('.mci-mention-hint').textContent = item.hint || '';
            btn.addEventListener('mousedown', (e) => { e.preventDefault(); pick(i); });
            el.appendChild(btn);
        });
    };

    const place = (rect) => {
        if (!el || !rect) return;
        const r = rect();
        if (!r) return;
        el.style.left = Math.min(r.left + window.scrollX, window.scrollX + window.innerWidth - 300) + 'px';
        el.style.top = r.bottom + window.scrollY + 6 + 'px';
    };

    const pick = (i) => {
        const item = items[i];
        if (item && command) command({ id: item.id, label: item.label });
    };

    return {
        onStart: (props) => {
            el = document.createElement('div');
            el.className = 'mci-mention-popup';
            el.setAttribute('role', 'listbox');
            document.body.appendChild(el);
            items = props.items; selected = 0; command = props.command;
            draw(); place(props.clientRect);
        },
        onUpdate: (props) => {
            items = props.items; selected = 0; command = props.command;
            draw(); place(props.clientRect);
        },
        onKeyDown: ({ event }) => {
            if (event.key === 'ArrowDown') { selected = (selected + 1) % Math.max(items.length, 1); draw(); return true; }
            if (event.key === 'ArrowUp') { selected = (selected + items.length - 1) % Math.max(items.length, 1); draw(); return true; }
            if (event.key === 'Enter' || event.key === 'Tab') { pick(selected); return true; }
            if (event.key === 'Escape') { el?.remove(); el = null; return true; }
            return false;
        },
        onExit: () => { el?.remove(); el = null; },
    };
}

export default function mciEditor(config) {
    let editor = null;

    return {
        tick: 0,
        isEmpty: true,
        ready: false,

        async init() {
            const searchUrl = config.searchUrl;
            const { Editor, StarterKit, Mention, Placeholder, Link } = await loadTiptap();

            editor = new Editor({
                element: this.$refs.content,
                content: config.initial || this.$wire.get(config.property) || '',
                extensions: [
                    StarterKit.configure({ heading: { levels: [2, 3] } }),
                    Placeholder.configure({ placeholder: config.placeholder || '' }),
                    Link.configure({ openOnClick: false, autolink: true, protocols: ['https', 'mailto'] }),
                    Mention.configure({
                        HTMLAttributes: { class: 'mention' },
                        renderText: ({ node }) => '@' + (node.attrs.label ?? node.attrs.id),
                        renderHTML: ({ node }) => ['span', { 'data-type': 'mention', 'data-id': node.attrs.id, 'data-label': node.attrs.label, class: 'mention' }, '@' + (node.attrs.label ?? node.attrs.id)],
                        suggestion: {
                            items: async ({ query }) => {
                                try {
                                    const res = await fetch(searchUrl + '?q=' + encodeURIComponent(query), {
                                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                    });
                                    return res.ok ? await res.json() : [];
                                } catch (e) {
                                    return [];
                                }
                            },
                            render: suggestionPopup,
                        },
                    }),
                ],
                editorProps: { attributes: { class: 'mci-prose focus:outline-none min-h-40 px-3 py-2', 'aria-label': config.label || 'Treść' } },
                onUpdate: ({ editor }) => {
                    this.isEmpty = editor.isEmpty;
                    this.$wire.$set(config.property, editor.isEmpty ? '' : editor.getHTML(), false);
                },
                onTransaction: () => { this.tick++; },
            });
            this.isEmpty = editor.isEmpty;
            this.ready = true;

            // Reset after a successful submit (the component clears the property).
            this.$wire.$watch(config.property, (value) => {
                if (!value && !editor.isEmpty) editor.commands.clearContent();
            });
        },

        destroy() {
            editor?.destroy();
        },

        isActive(name, attrs = {}) {
            this.tick;
            return editor ? editor.isActive(name, attrs) : false;
        },

        run(action) {
            if (!editor) return;
            const chain = editor.chain().focus();
            ({
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                strike: () => chain.toggleStrike(),
                h2: () => chain.toggleHeading({ level: 2 }),
                h3: () => chain.toggleHeading({ level: 3 }),
                bullet: () => chain.toggleBulletList(),
                ordered: () => chain.toggleOrderedList(),
                quote: () => chain.toggleBlockquote(),
                mention: () => chain.insertContent(' @'),
            })[action]().run();
        },

        link() {
            if (!editor) return;
            const previous = editor.getAttributes('link').href || 'https://';
            const url = window.prompt('Adres linku', previous);
            if (url === null) return;
            if (url === '' || url === 'https://') {
                editor.chain().focus().unsetLink().run();
            } else {
                editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
            }
        },
    };
}
