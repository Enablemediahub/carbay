<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['title'] }} · Carbay+</title>
    <link rel="icon" href="{{ \App\Models\PlatformSetting::appearance()->assetUrl('icon-192') }}">
    <style>body { margin: 0; background: #edf6ff; color: #173653; font-family: ui-sans-serif, system-ui, sans-serif; } main { max-width: 900px; margin: 30px auto; padding: clamp(20px, 5vw, 46px); background: #fff; border-radius: 24px; } .legal-back { color: #07518e; } @media print { body, main { background: #fff; margin: 0; padding: 0; } .legal-back { display: none; } }</style>
</head><body><main>
    <a class="legal-back" href="{{ route('home') }}">← Carbay+</a>
    @include('legal.document')
    @include('shared.developer-footer')
</main></body></html>
