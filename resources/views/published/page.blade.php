<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>

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
