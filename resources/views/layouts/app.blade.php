<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- TomSelect -->
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="background-color:#f8f9fa;">
        <div style="display:flex; min-height:100vh;">
            @include('layouts.navigation')

            <div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                @isset($header)
                    <header style="background-color:#fff; border-bottom:1px solid #e0e0e0; padding:1.5rem 0;">
                        <div style="max-width:1280px; margin:0 auto; padding:0 1rem;">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main style="flex:1; overflow-y:auto; padding:1.5rem;">
                    <div style="max-width:1280px; margin:0 auto;">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>