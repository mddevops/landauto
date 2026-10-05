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
@if ($metricaCounter)
        {{-- Official Yandex Metrica loader (P6-012). Counter ID is digits only, validated server-side. --}}
        <script type="text/javascript">
            (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
            (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
            ym({{ $metricaCounter }}, "init", {!! $metricaOptions !!});
        </script>
@endif
    </head>
    <body class="font-sans antialiased">
@if ($metricaCounter)
        <noscript><div><img src="https://mc.yandex.ru/watch/{{ $metricaCounter }}" style="position:absolute; left:-9999px;" alt=""></div></noscript>
@endif
        {{-- Stored publish-time HTML (ADR-006); hydrated from the payload below. --}}
        <div id="lf-root">{!! $html !!}</div>
        <script type="application/json" id="lf-page-data">{!! $data !!}</script>
    </body>
</html>
