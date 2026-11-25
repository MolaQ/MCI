<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="{{ \App\Support\Appearance::sanitizeHex($appearance->get('primary_color'), '#1d4ed8') }}" />
    <title>{{ __('Brak połączenia') }} · {{ $appearance->shortName() }}</title>
    {{-- Self-contained: this page is cached by the service worker and shown without network. --}}
    <style>
        :root { color-scheme: light dark; --p: {{ \App\Support\Appearance::sanitizeHex($appearance->get('primary_color'), '#1d4ed8') }}; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: Canvas; color: CanvasText; }
        main { max-width: 26rem; padding: 2rem 1.5rem; text-align: center; }
        .icon { width: 3.5rem; height: 3.5rem; margin: 0 auto 1rem; color: var(--p); }
        h1 { font-size: 1.4rem; margin: 0 0 .5rem; }
        p { margin: 0 0 1.5rem; opacity: .75; line-height: 1.5; }
        button { font: inherit; font-weight: 600; border: 0; border-radius: .6rem; padding: .7rem 1.4rem; background: var(--p); color: #fff; cursor: pointer; }
    </style>
</head>
<body>
    <main>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M8.5 16.5a5 5 0 017 0M5 12.9a10 10 0 015.2-2.8m3.6 0A10 10 0 0119 12.9M2 9.3a14.9 14.9 0 014.2-2.6m11.6 0A14.9 14.9 0 0122 9.3M12 20h.01"/></svg>
        <h1>{{ __('Brak połączenia z internetem') }}</h1>
        <p>{{ __('Ta strona wymaga połączenia. Sprawdź Wi-Fi lub dane komórkowe — powiadomienia push dotrą, gdy telefon znów będzie online.') }}</p>
        <button type="button" onclick="location.reload()">{{ __('Spróbuj ponownie') }}</button>
    </main>
</body>
</html>
