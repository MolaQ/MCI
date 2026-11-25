<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Plakat zaproszenia') }} · {{ $invite->school->short_name }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111827; background: #f4f4f5; }
        .sheet { max-width: 182mm; margin: 24px auto; background: #fff; padding: 18mm 16mm; text-align: center; border-radius: 8px; }
        .school { font-size: 14pt; letter-spacing: .04em; text-transform: uppercase; color: #52525b; }
        h1 { font-size: 30pt; margin: 6mm 0 2mm; line-height: 1.1; }
        .groups { font-size: 15pt; margin: 0 0 8mm; }
        .qr svg { width: 120mm; height: 120mm; }
        .how { font-size: 12pt; color: #3f3f46; margin-top: 6mm; }
        .url { font-family: ui-monospace, Menlo, monospace; font-size: 9pt; color: #71717a; word-break: break-all; margin-top: 4mm; }
        .meta { font-size: 10pt; color: #71717a; margin-top: 6mm; }
        .actions { text-align: center; margin: 16px; }
        .actions button { font: inherit; padding: 8px 16px; border-radius: 6px; border: 1px solid #d4d4d8; background: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">{{ __('Drukuj') }}</button></div>
    <main class="sheet">
        <div class="school">{{ $invite->school->name }}</div>
        <h1>{{ $invite->label ?: __('Dołącz do MCI') }}</h1>
        @if ($invite->groups->isNotEmpty())
            <p class="groups">{{ $invite->groups->pluck('name')->join(' · ') }}</p>
        @endif
        <div class="qr">{!! $qr !!}</div>
        <p class="how">{{ __('Zeskanuj kod aparatem telefonu, zaloguj się lub utwórz konto i potwierdź dołączenie.') }}</p>
        <p class="url">{{ $invite->url() }}</p>
        <p class="meta">
            @if ($invite->expires_at){{ __('Ważne do :date', ['date' => $invite->expires_at->format('d.m.Y')]) }}@endif
        </p>
    </main>
</body>
</html>
