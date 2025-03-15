<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Stock Tracker</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="flex flex-wrap md:flex-nowrap max-w-6xl w-full mx-auto py-10 gap-8">
            {{ $slot }}
            <x-docs-sidebar />
        </main>


        
        @stack('scripts')
        
        <footer class="mt-36 py-4 border-gray-200">
            <div class="max-w-6xl mx-auto px-4 flex justify-start items-center gap-x-4 text-sm text-gray-500">
                <div class="font-medium">{{ date('Y') }} Stock Tracker</div>
                -
                <div>Built with Laravel</div>
            </div>
        </footer>
    </body>
</html>
