<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Shree Axar ERP') }}</title>
    @php
        $manifestPath = public_path('build/manifest.json');
        $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        $entry = $manifest['resources/js/app.ts'] ?? null;
    @endphp
    @if ($entry)
        @foreach ($entry['css'] ?? [] as $css)
            <link rel="stylesheet" href="{{ asset('build/'.$css) }}">
        @endforeach
        <script type="module" src="{{ asset('build/'.$entry['file']) }}"></script>
    @else
        <script type="module" src="/resources/js/app.ts"></script>
    @endif
</head>
<body>
    <div id="app"></div>
</body>
</html>
