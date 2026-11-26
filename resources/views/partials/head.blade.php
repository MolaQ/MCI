<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="theme-color" content="{{ \App\Support\Appearance::sanitizeHex($appearance->get('primary_color'), '#1d4ed8') }}" />
<link rel="manifest" href="/manifest.webmanifest" />
@php($iconVersion = \App\Http\Controllers\PwaController::version($appearance))
<link rel="apple-touch-icon" href="/favicon-180.png?v={{ $iconVersion }}" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="{{ $appearance->shortName() }}" />

<title>{{ filled($title ?? null) ? $title.' · '.$appearance->shortName() : $appearance->schoolName() }}</title>

<link rel="icon" href="/favicon.ico?v={{ $iconVersion }}" sizes="48x48" />
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v={{ $iconVersion }}" />
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png?v={{ $iconVersion }}" />

<style id="mci-theme">{!! $appearance->cssVariables() !!}</style>

<script>
    // Apply the school's default theme until the user picks their own.
    try {
        if (! localStorage.getItem('flux.appearance') && @js($appearance->defaultTheme()) !== 'system') {
            localStorage.setItem('flux.appearance', @js($appearance->defaultTheme()));
        }
    } catch (e) {}
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
