<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Toko Sentosa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

    {{-- Navbar --}}
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.jpeg') }}" class="h-8 w-8 rounded-lg object-cover">
                    <span class="font-bold text-gray-900">Toko Sentosa</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('toko.keranjang') }}"
                       class="relative inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                        🛒 Keranjang
                        @php $jumlahKeranjang = count(session('keranjang', [])); @endphp
                        @if($jumlahKeranjang > 0)
                            <span class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">
                                {{ $jumlahKeranjang }}
                            </span>
                        @endif
                    </a>
                    <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-sm text-red-600 hover:text-red-800">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Alert --}}
        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-100 text-green-800 rounded-md text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Search --}}
        <div class="mb-6">
            <form method="GET" action="{{ route('toko.index') }}">
                <div class="flex gap-3">
                    <input type="text" name="search" value="{{ $search }}"
                        placeholder="Cari produk..."
                        class="flex-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-500">
                        Cari
                    </button>
                    @if($search)
                        <a href="{{ route('toko.index') }}"
                           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Grid Produk --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @forelse($products as $product)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                    {{-- Placeholder foto --}}
                                        {{-- Gambar produk --}}
                    <div class="h-36 bg-gray-100 flex items-center justify-center overflow-hidden">
                        @if($product->image_path)
                            <img src="{{ app(\App\Services\SupabaseStorageService::class)->getPublicUrl($product->image_path) }}"
                                alt="{{ $product->product_name }}"
                                class="h-full w-full object-cover">
                        @else
                            <span class="text-4xl">📦</span>
                        @endif
                    </div>

                    <div class="p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1 line-clamp-2">
                            {{ $product->product_name }}
                        </h3>
                        <p class="text-xs text-gray-500 mb-2">{{ $product->brand }}</p>
                        <p class="text-base font-bold text-indigo-600 mb-1">
                            Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-gray-500 mb-3">
                            per {{ $product->sale_unit }} | Stok: {{ $product->stock }}
                        </p>

                        <form action="{{ route('toko.keranjang.tambah') }}" method="POST">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="flex items-center gap-2 mb-2">
                                <input type="number" name="quantity"
                                    min="0.25" step="0.25" value="1"
                                    class="w-20 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span class="text-xs text-gray-500">{{ $product->sale_unit }}</span>
                            </div>
                            <button type="submit"
                                class="w-full py-2 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-500">
                                + Keranjang
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-4 text-center py-12 text-gray-500">
                    Tidak ada produk tersedia.
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $products->links() }}
        </div>

    </div>

</body>
</html>