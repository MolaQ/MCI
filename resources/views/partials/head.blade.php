<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="theme-color" content="{{ $appearance->get('primary_color') }}" />

<title>{{ filled($title ?? null) ? $title.' · '.$appearance->shortName() : $appearance->schoolName() }}</title>

<link rel="icon" href="/favicon.ico" sizes="any" />

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
