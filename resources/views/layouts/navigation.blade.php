<div x-data="{ sidebarOpen: false }">

    <!-- STYLE UNTUK ALPINE CLOAK -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- MOBILE TOP BAR -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 lg:hidden bg-white sticky top-0 z-20">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3">
            <img src="{{ asset('images/logo.jpeg') }}" alt="Toko Sentosa"
                class="h-10 w-10 rounded-xl object-cover">
            <span class="text-lg font-semibold text-slate-900">Sentosa</span>
        </a>

        <button @click="sidebarOpen = !sidebarOpen"
            class="inline-flex items-center justify-center p-2 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100 focus:outline-none">

            <!-- ICON MENU -->
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :class="{ 'hidden': sidebarOpen, 'inline-flex': !sidebarOpen }" class="inline-flex"
                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"></path>

                <path :class="{ 'hidden': !sidebarOpen, 'inline-flex': sidebarOpen }" class="hidden"
                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- OVERLAY (mobile only, shown while sidebar is open) -->
    <div x-show="sidebarOpen" x-cloak
        @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-30 bg-black/50 lg:hidden">
    </div>

    <!-- SIDEBAR — fixed at every breakpoint: off-canvas + slide-in on mobile
         (toggled by sidebarOpen), always visible on desktop (lg:translate-x-0).
         The main content column reserves space for it via lg:ml-64 instead of
         relying on normal document flow, so this never needs to leave
         `fixed` positioning between breakpoints. -->
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-white border-r border-gray-200
            transform transition-transform duration-200 ease-in-out
            lg:translate-x-0">

        <!-- LOGO -->
        <div class="flex h-20 items-center justify-between px-6 border-b border-gray-200">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo.jpeg') }}"
                    class="h-10 w-10 rounded-xl object-cover">

                <div>
                    <p class="text-sm font-semibold text-slate-900">Toko Sentosa</p>
                    <p class="text-xs text-slate-500">Sistem Inventaris</p>
                </div>
            </a>

            <!-- Close button (mobile only) -->
            <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-slate-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- MENU -->
        <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6" @click="sidebarOpen = false">

            <!-- STOCK -->
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Manajemen Stok</p>

                <div class="mt-3 space-y-1">
                    <a href="{{ route('products.index') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                        {{ request()->routeIs('products.*') && !request()->routeIs('products.my') ? 'bg-slate-100' : '' }}">
                        Produk
                    </a>
                    @if(auth()->user()->role?->role_name === 'Karyawan')
                        <a href="{{ route('products.my') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('products.my') ? 'bg-slate-100' : '' }}">
                            Produk Saya
                        </a>
                    @endif
                </div>
            </div>

            <!-- MASTER -->
            @if(auth()->user()->role?->role_name === 'Pemilik')
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400">Master Data</p>

                    <div class="mt-3 space-y-1">
                        <a href="{{ route('suppliers.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('suppliers.*') ? 'bg-slate-100' : '' }}">
                            Suppliers
                        </a>

                        <a href="{{ route('customers.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('customers.*') ? 'bg-slate-100' : '' }}">
                            Pelanggan
                        </a>
                    </div>
                </div>
            @endif

            @if(auth()->user()->role?->role_name === 'Pemilik')
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400">Pembelian</p>

                    <div class="mt-3 space-y-1">
                        <a href="{{ route('purchase-orders.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('purchase-orders.*') ? 'bg-slate-100' : '' }}">
                            Purchase Orders
                        </a>
                        <a href="{{ route('purchases.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('purchases.*') ? 'bg-slate-100' : '' }}">
                            Pembelian
                        </a>
                    </div>
                </div>
            @endif

            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Penjualan</p>
                <div class="mt-3 space-y-1">
                    <a href="{{ route('sales.index') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                        {{ request()->routeIs('sales.*') ? 'bg-slate-100' : '' }}">
                        Penjualan
                        @php $pesananBaru = \App\Models\Sale::where('is_new', true)->count(); @endphp
                        @if($pesananBaru > 0)
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">
                                {{ $pesananBaru }}
                            </span>
                        @endif
                    </a>
                    @if(auth()->user()->role?->role_name === 'Pemilik')
                        <a href="{{ route('pengiriman.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('pengiriman.*') ? 'bg-slate-100' : '' }}">
                            Pengiriman
                        </a>
                    @endif
                </div>
            </div>
            @if(auth()->user()->role?->role_name === 'Pemilik')
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400">Keuangan</p>
                    <div class="mt-3 space-y-1">
                        <a href="{{ route('piutang.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('piutang.*') ? 'bg-slate-100' : '' }}">
                            Piutang
                        </a>

                        <a href="{{ route('pengiriman.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('pengiriman.*') ? 'bg-slate-100' : '' }}">
                            Pengiriman
                        </a>
                    </div>
                </div>
            @endif

            @if(auth()->user()->role?->role_name === 'Pemilik')
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400">Laporan</p>
                    <div class="mt-3 space-y-1">
                        <a href="{{ route('laporan.penjualan') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('laporan.penjualan*') ? 'bg-slate-100' : '' }}">
                            Lap. Penjualan
                        </a>
                        <a href="{{ route('laporan.pembelian') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('laporan.pembelian*') ? 'bg-slate-100' : '' }}">
                            Lap. Pembelian
                        </a>
                        <a href="{{ route('laporan.piutang') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('laporan.piutang*') ? 'bg-slate-100' : '' }}">
                            Lap. Piutang
                        </a>
                    </div>
                </div>
            @endif

            <!-- SETTINGS -->
            @if(auth()->user()->role?->role_name === 'Pemilik')
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400">Settings</p>

                    <div class="mt-3 space-y-1">
                        <a href="{{ route('users.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('users.*') ? 'bg-slate-100' : '' }}">
                            Users
                        </a>

                        <a href="{{ route('roles.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                            {{ request()->routeIs('roles.*') ? 'bg-slate-100' : '' }}">
                            Role
                        </a>
                    </div>
                </div>
            @endif

        </div>

        <!-- USER -->
        <div class="px-4 py-5 border-t border-gray-200">

            <p class="text-sm font-semibold text-slate-900">
                {{ Auth::user()->name ?? 'User' }}
            </p>

            <p class="text-xs text-slate-500">
                {{ Auth::user()->email ?? '' }}
            </p>

            <div class="mt-3 space-y-2">

                <a href="{{ route('profile.edit') }}"
                    class="block px-3 py-2 rounded-xl text-sm hover:bg-slate-100">
                    Profile
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full text-left px-3 py-2 rounded-xl text-sm text-red-600 hover:bg-red-50">
                        Keluar
                    </button>
                </form>

            </div>

        </div>

    </aside>

</div>
