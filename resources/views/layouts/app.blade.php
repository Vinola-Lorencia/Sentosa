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

        <!-- Sidebar admin: buka/tutup di desktop (diingat lewat localStorage).
             Kelas `sidebar-closed` dipasang di <html> sebelum halaman digambar,
             supaya sidebar yang disembunyikan tidak sempat muncul lalu bergeser. -->
        <script>
            (function () {
                try {
                    if (localStorage.getItem('sentosa:sidebar') === 'closed') {
                        document.documentElement.classList.add('sidebar-closed');
                    }
                } catch (e) {}
            })();

            function adminSidebar() {
                return {
                    // Panel geser di HP/tablet (di bawah lg), selalu mulai tertutup.
                    sidebarOpen: false,
                    // Sidebar desktop (lg ke atas), mulai terbuka kecuali disimpan tertutup.
                    desktopOpen: ! document.documentElement.classList.contains('sidebar-closed'),
                    toggleDesktop() {
                        this.desktopOpen = ! this.desktopOpen;
                        document.documentElement.classList.toggle('sidebar-closed', ! this.desktopOpen);
                        try {
                            localStorage.setItem('sentosa:sidebar', this.desktopOpen ? 'open' : 'closed');
                        } catch (e) {}
                    },
                };
            }
        </script>
        <style>
            #admin-content { transition: margin-left 200ms ease-in-out; }
            .sidebar-toggle-desktop { display: none; }
            @media (min-width: 1024px) {
                .sidebar-toggle-desktop { display: inline-flex; }
                #admin-sidebar { transition: transform 200ms ease-in-out, visibility 0s linear 0s; }
                /* visibility menyusul setelah animasi, agar menu yang tersembunyi tidak bisa difokus dengan Tab */
                .sidebar-closed #admin-sidebar { transform: translateX(-100%); visibility: hidden; transition: transform 200ms ease-in-out, visibility 0s linear 200ms; }
                .sidebar-closed #admin-content { margin-left: 0; }
            }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="min-h-screen" x-data="adminSidebar()" @keydown.escape.window="sidebarOpen = false">
            @include('layouts.navigation')

            {{-- Main content: full-width on mobile (sidebar is off-canvas), offset
                 by the sidebar's fixed width (w-64 = ml-64) from lg upward, or no
                 offset while the sidebar is hidden (html.sidebar-closed). No
                 w-full here: width 100% plus ml-64 overflowed the page by 16rem. --}}
            <div id="admin-content" class="flex flex-col min-h-screen lg:ml-64">
                @isset($header)
                    <header class="bg-white border-b border-gray-200 py-6">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-3">
                            {{-- Desktop toggle; below lg the top bar's hamburger is used. --}}
                            <button type="button" @click="toggleDesktop()"
                                class="sidebar-toggle-desktop shrink-0 items-center justify-center p-2 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100 focus:outline-none"
                                aria-label="Tampilkan atau sembunyikan menu" aria-controls="admin-sidebar"
                                aria-expanded="true" :aria-expanded="desktopOpen.toString()">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>

                            <div class="flex-1 min-w-0">
                                {{ $header }}
                            </div>
                        </div>
                    </header>
                @endisset

                <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                    <div class="max-w-7xl mx-auto">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>