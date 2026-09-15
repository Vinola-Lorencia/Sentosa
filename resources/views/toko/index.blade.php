<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
                    <a href="{{ route('toko.index', ['filter' => 'baru']) }}"
                       class="relative text-sm font-medium text-gray-600 hover:text-indigo-600">
                        🆕 Produk Baru
                        @php
                            $produkBaruCount = \App\Models\Product::available()->newProducts()->unseenBy(auth()->id())->count();
                        @endphp
                        <span id="produk-baru-badge"
                            class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 {{ $produkBaruCount > 0 ? '' : 'hidden' }}">
                            {{ $produkBaruCount }}
                        </span>
                    </a>
                    <a href="{{ route('toko.pesanan') }}"
                       class="text-sm font-medium text-gray-600 hover:text-indigo-600">
                        Pesanan Saya
                    </a>
                    <a href="{{ route('toko.tagihan') }}"
                       class="text-sm font-medium text-gray-600 hover:text-indigo-600">
                        Tagihan Saya
                    </a>
                    <a href="{{ route('toko.keranjang') }}"
                       class="relative inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                        🛒 Keranjang
                        @php $jumlahKeranjang = \App\Models\CartItem::where('user_id', auth()->id())->count(); @endphp
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
                @if($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <div class="flex gap-3">
                    <input type="text" name="search" value="{{ $search }}"
                        placeholder="Cari produk..."
                        class="flex-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-500">
                        Cari
                    </button>
                    @if($search || $filter)
                        <a href="{{ route('toko.index') }}"
                           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Info filter aktif --}}
        @if($filter === 'baru')
            <div class="mb-6 flex items-center justify-between px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-md text-sm text-indigo-800">
                <span>🆕 Menampilkan {{ $products->total() }} produk baru yang belum Anda lihat.</span>
                <a href="{{ route('toko.index') }}" class="font-medium underline hover:text-indigo-900">
                    Lihat Semua Produk
                </a>
            </div>
        @endif

        {{-- Grid Produk --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @forelse($products as $product)
                @php
                    // "Baru" = created within the window AND not yet marked
                    // viewed by this specific user — opening the product's
                    // own detail (not just browsing this list) is what clears it.
                    $isBaru = $product->created_at
                        && $product->created_at->greaterThanOrEqualTo(now()->subDays(\App\Models\Product::NEW_PRODUCT_DAYS))
                        && $product->views->isEmpty();
                @endphp
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" data-product-card="{{ $product->id }}">

                    {{-- Gambar produk (klik untuk lihat detail) --}}
                    <button type="button"
                        onclick="openProductDetailModal({{ $product->id }})"
                        class="relative block w-full h-36 bg-gray-100 flex items-center justify-center overflow-hidden">
                        @if($isBaru)
                            <span data-baru-badge="{{ $product->id }}"
                                class="absolute top-1.5 left-1.5 z-10 bg-red-500 text-white text-[10px] font-bold rounded-full px-2 py-0.5">
                                Baru
                            </span>
                        @endif
                        @if($product->image_path)
                            <img src="{{ app(\App\Services\SupabaseStorageService::class)->getPublicUrl($product->image_path) }}"
                                alt="{{ $product->product_name }}"
                                class="h-full w-full object-cover">
                        @else
                            <span class="text-4xl">📦</span>
                        @endif
                    </button>

                    <div class="p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1 line-clamp-2">
                            {{ $product->product_name }}
                        </h3>
                        <p class="text-xs text-gray-500 mb-2">{{ $product->brand }}</p>
                        <p class="text-base font-bold text-indigo-600 mb-1">
                            Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-gray-500 mb-3">
                            Stok: {{ $product->stok_display }}
                        </p>

                        <form action="{{ route('toko.keranjang.tambah') }}" method="POST" class="unit-price-form">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="flex items-center gap-2 mb-1">
                                <input type="number" name="quantity"
                                    min="0.25" step="0.25" value="1"
                                    class="qty-input w-20 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <select name="unit"
                                    data-price="{{ (float) $product->sale_price }}"
                                    data-sale-unit="{{ strtolower($product->sale_unit) }}"
                                    class="unit-select text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach(['Buah', 'Lusin', 'Kodi', 'Gross'] as $option)
                                        <option value="{{ $option }}"
                                            {{ strtolower($product->sale_unit) === strtolower($option) ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="unit-price-preview text-xs text-gray-500 mb-2"></p>
                            <button type="submit"
                                class="w-full py-2 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-500">
                                + Keranjang
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-4 text-center py-12 text-gray-500">
                    {{ $filter === 'baru' ? 'Tidak ada produk baru saat ini.' : 'Tidak ada produk tersedia.' }}
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $products->links() }}
        </div>

    </div>

    {{-- Modal Detail Produk --}}
    <div id="product-detail-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-sm overflow-hidden">
            <div class="h-48 bg-gray-100 flex items-center justify-center overflow-hidden">
                <img id="product-detail-image" src="" alt="" class="hidden h-full w-full object-cover">
                <span id="product-detail-placeholder" class="text-5xl">📦</span>
            </div>
            <div class="p-6">
                <h3 id="product-detail-name" class="text-base font-semibold text-gray-900 mb-1"></h3>
                <p id="product-detail-brand" class="text-xs text-gray-500 mb-3"></p>
                <p id="product-detail-price" class="text-lg font-bold text-indigo-600 mb-1"></p>
                <p id="product-detail-stock" class="text-xs text-gray-500"></p>

                <div class="flex items-center justify-end mt-4">
                    <button type="button" onclick="closeProductDetailModal()"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-500">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Live price-per-unit preview: mirrors sales/create.blade.php's
         applyUnitConversion() exactly — choosing the product's own
         sale_unit never divides the price at all; a smaller unit divides
         it down, a larger unit multiplies it up. The server (TokoController
         ::tambahKeranjang()) computes the authoritative price the same way;
         this is only a preview so the customer sees it before submitting. --}}
    <script>
        const UNIT_TO_BASE = {
            buah: 1, pcs: 1, biji: 1, pak: 10, set: 6, lusin: 12, kodi: 20, gross: 144,
        };

        // Mirrors App\Services\UnitConverter::priceForUnit() on the backend —
        // this is only a live preview; the server (TokoController::
        // tambahKeranjang()) recomputes/validates the same way and is what
        // actually gets persisted. No rounding when the two factors are
        // equal (choosing the product's own sale_unit) — only when a real
        // conversion happens.
        function priceForUnit(basePrice, chosenFactor, saleFactor) {
            if (chosenFactor === saleFactor) return basePrice;
            return Math.ceil((basePrice * (chosenFactor / saleFactor)) / 1000) * 1000;
        }

        function updateUnitPricePreview(form) {
            const select = form.querySelector('.unit-select');
            const qtyInput = form.querySelector('.qty-input');
            const preview = form.querySelector('.unit-price-preview');
            if (!select || !preview) return;

            const chosenUnit = select.value.trim().toLowerCase();
            const saleUnit   = (select.dataset.saleUnit || '').trim().toLowerCase();
            const basePrice  = parseFloat(select.dataset.price) || 0;

            const chosenFactor = UNIT_TO_BASE[chosenUnit];
            const saleFactor   = UNIT_TO_BASE[saleUnit];

            if (!chosenFactor || !saleFactor) {
                preview.textContent = '';
                return;
            }

            const pricePerUnit = priceForUnit(basePrice, chosenFactor, saleFactor);
            const qty = parseFloat(qtyInput?.value) || 0;
            const subtotal = pricePerUnit * qty;

            preview.textContent = `Rp ${pricePerUnit.toLocaleString('id-ID', { minimumFractionDigits: 0 })} / ${select.value}`
                + (qty > 0 ? ` — Subtotal: Rp ${subtotal.toLocaleString('id-ID', { minimumFractionDigits: 0 })}` : '');
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.unit-price-form').forEach(form => {
                updateUnitPricePreview(form);
                form.querySelector('.unit-select')?.addEventListener('change', () => updateUnitPricePreview(form));
                form.querySelector('.qty-input')?.addEventListener('input', () => updateUnitPricePreview(form));
            });
        });
    </script>

    {{-- Product Data --}}
    <script>
        const productDetailData = {
            @foreach($products as $product)
            "{{ $product->id }}": {
                name:  "{{ addslashes($product->product_name) }}",
                brand: "{{ addslashes($product->brand ?? '-') }}",
                price: {{ (float) $product->sale_price }},
                stockDisplay: "{{ addslashes($product->stok_display) }}",
                image: {!! $product->image_path ? '"' . addslashes(app(\App\Services\SupabaseStorageService::class)->getPublicUrl($product->image_path)) . '"' : 'null' !!},
            },
            @endforeach
        };
    </script>

    <script>
        function openProductDetailModal(productId) {
            const data = productDetailData[productId];
            if (!data) return;

            document.getElementById('product-detail-name').textContent  = data.name;
            document.getElementById('product-detail-brand').textContent = data.brand;
            document.getElementById('product-detail-price').textContent =
                'Rp ' + data.price.toLocaleString('id-ID', { minimumFractionDigits: 0 });
            document.getElementById('product-detail-stock').textContent = 'Stok: ' + data.stockDisplay;

            const img         = document.getElementById('product-detail-image');
            const placeholder = document.getElementById('product-detail-placeholder');
            if (data.image) {
                img.src = data.image;
                img.classList.remove('hidden');
                placeholder.classList.add('hidden');
            } else {
                img.classList.add('hidden');
                placeholder.classList.remove('hidden');
            }

            document.getElementById('product-detail-modal').classList.remove('hidden');

            markProductViewed(productId);
        }

        function closeProductDetailModal() {
            document.getElementById('product-detail-modal').classList.add('hidden');
        }

        // Marks the product seen for this user (idempotent server-side via
        // firstOrCreate), then clears just that product's own "Baru" ribbon
        // and decrements the navbar badge — never touches any other
        // product's unseen state.
        function markProductViewed(productId) {
            const badge = document.querySelector(`[data-baru-badge="${productId}"]`);
            if (!badge) return; // already seen (or never was "Baru") — nothing to clear

            fetch(`/toko/products/${productId}/view`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
                .then((res) => {
                    if (!res.ok) return;

                    badge.remove();

                    const navBadge = document.getElementById('produk-baru-badge');
                    const current  = parseInt(navBadge.textContent.trim(), 10) || 0;
                    const next     = Math.max(0, current - 1);
                    navBadge.textContent = next;
                    navBadge.classList.toggle('hidden', next === 0);
                })
                .catch(() => {
                    // Network hiccup — leave the ribbon/badge as-is rather than
                    // clearing local state the server never actually recorded.
                });
        }
    </script>

</body>
</html>