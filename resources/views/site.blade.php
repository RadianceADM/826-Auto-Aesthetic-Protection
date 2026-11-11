<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#0f1113" />

    {{-- Light or dark before anything paints: the visitor's own choice, else dark. lib/theme.ts takes over after --}}
    <script>
        (function () {
            var theme = 'dark';
            try {
                var chosen = localStorage.getItem('app826-theme');
                theme = chosen === 'light' || chosen === 'dark' ? chosen : 'dark';
            } catch (error) {}
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        })();
    </script>

    <title>{{ $meta['title'] }}</title>

    <meta name="description" content="{{ $meta['description'] }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @if ($meta['noindex'])
        <meta name="robots" content="noindex" />
    @else
        <link rel="canonical" href="{{ $meta['url'] }}" />
    @endif

    {{-- Link previews (Facebook, Messenger, Viber) --}}
    <meta property="og:site_name" content="{{ $app['name'] }}" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $meta['title'] }}" />
    <meta property="og:description" content="{{ $meta['description'] }}" />
    <meta property="og:url" content="{{ $meta['url'] }}" />
    <meta property="og:image" content="{{ $meta['image'] }}" />
    @if ($meta['imageSize'])
        <meta property="og:image:width" content="{{ $meta['imageSize'][0] }}" />
        <meta property="og:image:height" content="{{ $meta['imageSize'][1] }}" />
    @endif
    @if ($meta['imageAlt'])
        <meta property="og:image:alt" content="{{ $meta['imageAlt'] }}" />
    @endif
    <meta property="og:locale" content="en_PH" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $meta['title'] }}" />
    <meta name="twitter:description" content="{{ $meta['description'] }}" />
    <meta name="twitter:image" content="{{ $meta['image'] }}" />

    {{-- The two fonts the first screen needs (Archivo for the text, its wide italic for the headings), fetched with the page
         instead of after the CSS (blueprints PERFORMANCE_BLUEPRINT.md §4). Latin only: the CSS brings ₱ and other scripts if a page has them. --}}
    @foreach (['node_modules/@fontsource-variable/archivo/files/archivo-latin-wght-normal.woff2', 'node_modules/@fontsource-variable/archivo/files/archivo-latin-wdth-italic.woff2'] as $font)
        @if (! Vite::isRunningHot() && ($fontUrl = rescue(fn () => Vite::asset($font), report: false)))
            <link rel="preload" href="{{ $fontUrl }}" as="font" type="font/woff2" crossorigin />
        @endif
    @endforeach

    {{-- Icons --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32" />
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('site.webmanifest') }}" />

    {{-- For search engines: the brand, or the branch with its address, pin and hours (schema.org) --}}
    @if ($meta['schema'])
        <script type="application/ld+json">@json($meta['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)</script>
    @endif

    <script>
        window.APP826 = @json($app);
    </script>

    @viteReactRefresh

    {{-- app.tsx imports app.css; Vite injects it from the manifest --}}
    @vite('resources/js/app.tsx')
</head>

<body>
    <div id="app"></div>
    {{-- The basics without JavaScript, also read by crawlers that don't run it --}}
    <noscript>
        <main style="max-width: 40rem; margin: 0 auto; padding: 2rem 1.25rem; font-family: system-ui, sans-serif; line-height: 1.6; color: #111; background: #f5f5f4">
            <h1>{{ $app['name'] }}</h1>
            @if ($app['settings']['intro'])
                <p>{{ $app['settings']['intro'] }}</p>
            @endif

            <h2>Services</h2>
            <ul>
                @foreach ($app['catalog']['categories'] as $category)
                    <li>{{ $category['name'] }}: {{ collect($category['services'])->pluck('name')->implode(', ') }}</li>
                @endforeach
            </ul>

            <h2>Branches</h2>
            <ul>
                @foreach ($app['branches'] as $branch)
                    <li>
                        {{ $branch['name'] }}@if ($branch['address']), {{ $branch['address'] }}@endif
                        @if ($branch['phone']) · <a href="tel:{{ $branch['phoneHref'] }}">{{ $branch['phone'] }}</a>@endif
                        @if ($branch['status'] === 'coming_soon') (coming soon)@endif
                    </li>
                @endforeach
            </ul>

            <p>
                @if ($app['settings']['phone'])
                    Call or text <a href="tel:{{ $app['settings']['phoneHref'] }}">{{ $app['settings']['phone'] }}</a>, or turn on JavaScript to <a href="{{ url('book') }}">reserve online</a>.
                @else
                    Turn on JavaScript to <a href="{{ url('book') }}">reserve online</a>.
                @endif
            </p>
            @if ($app['settings']['facebook'])
                <p><a href="{{ $app['settings']['facebook'] }}" rel="noopener">Facebook</a></p>
            @endif

            <p><a href="{{ url('/') }}">Home</a> · <a href="{{ url('services') }}">Services</a> · <a href="{{ url('branches') }}">Branches</a> · <a href="{{ url('gallery') }}">Our work</a> · <a href="{{ url('book') }}">Book</a></p>
        </main>
    </noscript>
</body>
</html>
