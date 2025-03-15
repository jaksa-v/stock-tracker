<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Stock Tracker</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                /*! tailwindcss v4.0.7 | MIT License | https://tailwindcss.com */
                /* Tailwind CSS content here - removed for brevity */
            </style>
        @endif
    </head>
    <body>
        <main class="flex flex-wrap md:flex-nowrap max-w-6xl w-full mx-auto py-10 gap-8">
            {{ $slot }}
            <x-docs-sidebar />
        </main>
        
        @stack('scripts')
    </body>
</html>
