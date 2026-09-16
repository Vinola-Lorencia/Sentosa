{{-- Shared storefront navbar — included by every resources/views/toko/*.blade.php
     page instead of each duplicating its own <nav>. Responsive the same way
     as the admin layout (resources/views/layouts/navigation.blade.php):
     full horizontal link row from lg: up, collapsed into a hamburger-toggled
     dropdown below it on smaller screens. --}}
<nav x-data="{ open: false }" class="bg-white border-b border-gray-200 sticky top-0 z-50">

    <style>[x-cloak] { display: none !important; }</style>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <a href="{{ route('toko.index') }}" class="flex items-center gap-3 shrink-0">
                <img src="{{ asset('images/logo.jpeg') }}" alt="Toko Sentosa" class="h-8 w-8 rounded-lg object-cover">
                <span class="font-bold text-gray-900">Toko Sentosa</span>
            </a>

            {{-- Desktop links --}}
            <div class="hidden lg:flex items-center gap-4">
                @php
                    $produkBaruCount = \App\Models\Product::available()->newProducts()->unseenBy(auth()->id())->count();
                    $jumlahKeranjang = \App\Models\CartItem::where('user_id', auth()->id())->count();
                @endphp

                <a href="{{ route('toko.index') }}"
                    class="text-sm font-medium px-2 py-1 rounded-md {{ request()->routeIs('toko.index') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600' }}">
                    Katalog
                </a>

                <a href="{{ route('toko.index', ['filter' => 'baru']) }}"
                    class="relative text-sm font-medium px-2 py-1 rounded-md {{ request()->routeIs('toko.index') && request('filter') === 'baru' ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600' }}">
                    🆕 Produk Baru
                    <span id="produk-baru-badge"
                        class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 {{ $produkBaruCount > 0 ? '' : 'hidden' }}">
                        {{ $produkBaruCount }}
                    </span>
                </a>

                <a href="{{ route('toko.pesanan') }}"
                    class="text-sm font-medium px-2 py-1 rounded-md {{ request()->routeIs('toko.pesanan') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600' }}">
                    Pesanan Saya
                </a>

                <a href="{{ route('toko.tagihan') }}"
                    class="text-sm font-medium px-2 py-1 rounded-md {{ request()->routeIs('toko.tagihan') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:text-indigo-600' }}">
                    Tagihan Saya
                </a>

                <a href="{{ route('toko.keranjang') }}"
                    class="relative inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                    🛒 Keranjang
                    @if($jumlahKeranjang > 0)
                        <span class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">
                            {{ $jumlahKeranjang }}
                        </span>
                    @endif
                </a>

                <span class="text-sm text-gray-600 whitespace-nowrap">{{ Auth::user()->name }}</span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-red-600 hover:text-red-800">Logout</button>
                </form>
            </div>

            {{-- Hamburger (mobile) --}}
            <button @click="open = !open"
                class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 focus:outline-none">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden"
                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

        </div>
    </div>

    {{-- Mobile dropdown --}}
    <div x-show="open" x-cloak class="lg:hidden border-t border-gray-200 bg-white">
        <div class="px-4 py-3 space-y-1">
            @php
                $produkBaruCount ??= \App\Models\Product::available()->newProducts()->unseenBy(auth()->id())->count();
                $jumlahKeranjang ??= \App\Models\CartItem::where('user_id', auth()->id())->count();
            @endphp

            <a href="{{ route('toko.index') }}"
                class="flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('toko.index') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:bg-gray-50' }}">
                Katalog
            </a>

            <a href="{{ route('toko.index', ['filter' => 'baru']) }}"
                class="flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('toko.index') && request('filter') === 'baru' ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:bg-gray-50' }}">
                <span>🆕 Produk Baru</span>
                @if($produkBaruCount > 0)
                    <span class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5">{{ $produkBaruCount }}</span>
                @endif
            </a>

            <a href="{{ route('toko.pesanan') }}"
                class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('toko.pesanan') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:bg-gray-50' }}">
                Pesanan Saya
            </a>

            <a href="{{ route('toko.tagihan') }}"
                class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('toko.tagihan') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:bg-gray-50' }}">
                Tagihan Saya
            </a>

            <a href="{{ route('toko.keranjang') }}"
                class="flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('toko.keranjang') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600 hover:bg-gray-50' }}">
                <span>🛒 Keranjang</span>
                @if($jumlahKeranjang > 0)
                    <span class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5">{{ $jumlahKeranjang }}</span>
                @endif
            </a>

            <div class="pt-2 mt-2 border-t border-gray-100 flex items-center justify-between px-3">
                <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-red-600 hover:text-red-800">Logout</button>
                </form>
            </div>
        </div>
    </div>

</nav>
