<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    @include('partials.head')
</head>
<body class="h-full overflow-hidden bg-zinc-950 text-white antialiased">
    {{ $slot }}
    @fluxScripts
</body>
</html>
