<nav x-data="{ open: false }" class="h-full">

    <!-- STYLE UNTUK ALPINE CLOAK -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- MOBILE TOP BAR -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 lg:hidden bg-white">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3">
            <img src="{{ asset('images/logo.jpeg') }}" alt="Toko Sentosa"
                class="h-10 w-10 rounded-xl object-cover">
            <span class="text-lg font-semibold text-slate-900">Sentosa</span>
        </a>

        <button @click="open = !open"
            class="inline-flex items-center justify-center p-2 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100 focus:outline-none">

            <!-- ICON MENU -->
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"></path>

                <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden"
                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- DESKTOP SIDEBAR -->
    <aside class="hidden lg:flex lg:flex-col h-full bg-white border-r border-gray-200">

        <!-- LOGO -->
        <div class="flex h-20 items-center px-6 border-b border-gray-200">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo.jpeg') }}"
                    class="h-10 w-10 rounded-xl object-cover">

                <div>
                    <p class="text-sm font-semibold text-slate-900">Toko Sentosa</p>
                    <p class="text-xs text-slate-500">Inventory</p>
                </div>
            </a>
        </div>

        <!-- MENU -->
        <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6">

            <!-- STOCK -->
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Manajemen Stok</p>

                <div class="mt-3 space-y-1">
                    <a href="{{ route('products.index') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                        {{ request()->routeIs('products.*') ? 'bg-slate-100' : '' }}">
                        Products
                    </a>
                </div>
            </div>

            <!-- MASTER -->
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
                        Customers
                    </a>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Pembelian</p>

                <div class="mt-3 space-y-1">
                    <a href="{{ route('purchase-orders.index') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-2xl text-sm font-medium hover:bg-slate-100
                        {{ request()->routeIs('purchase-orders.*') ? 'bg-slate-100' : '' }}">
                        Purchase Orders
                    </a>
                </div>
            </div>

            <!-- SETTINGS -->
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
                        Roles
                    </a>
                </div>
            </div>

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
                        Logout
                    </button>
                </form>

            </div>

        </div>

    </aside>

    <!-- MOBILE MENU -->
    <div x-show="open" x-cloak class="lg:hidden bg-white border-b border-gray-200">

        <div class="px-4 py-4 space-y-2">

            <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-xl hover:bg-slate-100">Dashboard</a>
            <a href="{{ route('products.index') }}" class="block px-3 py-2 rounded-xl hover:bg-slate-100">Products</a>
            <a href="{{ route('suppliers.index') }}" class="block px-3 py-2 rounded-xl hover:bg-slate-100">Suppliers</a>
            <a href="{{ route('customers.index') }}" class="block px-3 py-2 rounded-xl hover:bg-slate-100">Customers</a>
            <a href="{{ route('roles.index') }}" class="block px-3 py-2 rounded-xl hover:bg-slate-100">Roles</a>
            <a href="{{ route('users.index') }}" class="block px-3 py-2 rounded-xl hover:bg-slate-100">Users</a>

        </div>

    </div>

</nav>