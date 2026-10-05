<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
@if ($description)
        <meta name="description" content="{{ $description }}">
@endif
        <meta name="robots" content="{{ $robots }}">
@if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
@endif
        {{-- Open Graph only from real published values; no image is ever invented. --}}
        <meta property="og:type" content="website">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:title" content="{{ $title }}">
@if ($description)
        <meta property="og:description" content="{{ $description }}">
@endif
@if ($canonical)
        <meta property="og:url" content="{{ $canonical }}">
@endif
@if ($siteName)
        <meta property="og:site_name" content="{{ $siteName }}">
@endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/public-runtime/hydrate-client.tsx'])
    </head>
    <body class="font-sans antialiased">
        {{-- Stored publish-time HTML (ADR-006); hydrated from the payload below. --}}
        <div id="lf-root">{!! $html !!}</div>
        <script type="application/json" id="lf-page-data">{!! $data !!}</script>
    </body>
</html>
