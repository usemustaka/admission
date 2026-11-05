<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #ffffff;
            }

            html.dark {
                background-color: oklch(0.175 0 0);
            }
        </style>

        @php($brandFavicon = \App\Support\Brand::faviconUrl())
        @if ($brandFavicon)
            <link rel="icon" href="{{ $brandFavicon }}">
            <link rel="apple-touch-icon" href="{{ $brandFavicon }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif
        <meta name="app-name" content="{{ \App\Support\Brand::name() }}">
        @if ($brandTagline = \App\Support\Brand::get('brand.tagline'))
            <meta name="description" content="{{ $brandTagline }}">
        @endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @if ($brandTheme = \App\Support\Brand::themeVariables())
            <style>
                :root, .dark {
                    @foreach ($brandTheme as $property => $value)
                        {{ $property }}: {{ $value }};
                    @endforeach
                }
            </style>
        @endif
        <x-inertia::head>
            <title>{{ \App\Support\Brand::name() }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
