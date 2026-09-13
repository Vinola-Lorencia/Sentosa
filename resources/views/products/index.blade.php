<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Produk
            </h2>
            <a href="{{ route('products.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm">
                Tambah Produk
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                {{-- Pesan Sukses --}}
                @if(session('success'))
                    <div class="mb-4 rounded-lg bg-green-100 border border-green-200 p-4 text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- Pesan Error --}}
                @if(session('error'))
                    <div class="mb-4 rounded-lg bg-red-100 border border-red-200 p-4 text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- Form Filter --}}
                <form method="GET" action="{{ route('products.index') }}"
                      class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Cari Produk
                        </label>
                        <input type="text" name="search"
                               value="{{ request('search') }}"
                               placeholder="Cari produk..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Merek
                        </label>
                        <input type="text" name="brand"
                               value="{{ request('brand') }}"
                               placeholder="Filter merek..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Stok
                        </label>
                        <select name="stock"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Semua</option>
                            <option value="available" {{ request('stock') == 'available' ? 'selected' : '' }}>
                                Tersedia
                            </option>
                            <option value="empty" {{ request('stock') == 'empty' ? 'selected' : '' }}>
                                Habis
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">
                            Filter
                        </button>
                        <a href="{{ route('products.index') }}"
                           class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-400">
                            Atur Ulang
                        </a>
                    </div>

                </form>

                {{-- Grid Katalog --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @forelse($products as $product)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex flex-col">

                            {{-- Foto produk (klik untuk upload/ganti) --}}
                            <button type="button"
                                onclick="openPhotoModal({{ $product->id }}, @js($product->product_name))"
                                class="group relative block w-full h-36 bg-gray-100 overflow-hidden">
                                <div data-product-photo="{{ $product->id }}" class="h-full w-full flex items-center justify-center">
                                    @if($product->image_path)
                                        <img src="{{ app(\App\Services\SupabaseStorageService::class)->getPublicUrl($product->image_path) }}"
                                            alt="{{ $product->product_name }}"
                                            class="h-full w-full object-cover">
                                    @else
                                        <div class="h-full w-full bg-gray-300"></div>
                                    @endif
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/40 transition">
                                    <span class="text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition">
                                        📷 Ubah Foto
                                    </span>
                                </div>
                            </button>

                            <div class="p-4 flex-1 flex flex-col">
                                <h3 class="text-sm font-semibold text-gray-900 mb-1 line-clamp-2">
                                    {{ $product->product_name }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-3">{{ $product->brand ?? '-' }}</p>

                                <div class="text-xs text-gray-600 space-y-1.5 mb-3">
                                    <div class="flex justify-between">
                                        <span>Harga Beli</span>
                                        <span class="font-medium text-gray-800">
                                            Rp {{ number_format($product->default_buy_price, 0, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Harga Jual</span>
                                        <span class="font-semibold text-indigo-600">
                                            Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span class="shrink-0">Stok</span>
                                        <span class="font-medium text-right {{ $product->stock > 0 ? 'text-gray-800' : 'text-red-600' }}">
                                            {{ $product->stock > 0 ? $product->stok_display : 'Habis' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-auto flex items-center justify-between pt-3 border-t border-gray-100">
                                    <a href="{{ route('products.edit', $product) }}"
                                       class="text-xs font-medium text-indigo-600 hover:text-indigo-900">
                                        Ubah
                                    </a>
                                    <form action="{{ route('products.destroy', $product) }}"
                                          method="POST"
                                          onsubmit="return confirm('Hapus produk ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-900">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12 text-sm text-gray-500">
                            Tidak ada produk ditemukan.
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                <div class="mt-4">
                    {{ $products->links() }}
                </div>

            </div>
        </div>
    </div>

    {{-- Modal Upload Foto Produk --}}
    <div id="photo-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4" id="photo-modal-title">
                Upload Foto Produk
            </h3>

            <form id="photo-modal-form" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <input type="file" name="image" id="photo-modal-input"
                    accept="image/jpg,image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 mb-2
                        file:mr-4 file:py-2 file:px-4 file:rounded-md
                        file:border-0 file:text-sm file:font-semibold
                        file:bg-indigo-50 file:text-indigo-700
                        hover:file:bg-indigo-100">
                <p class="text-xs text-gray-400 mb-1">JPG, PNG, WEBP. Maks 2MB.</p>
                <p id="photo-modal-error" class="hidden text-xs text-red-600 mb-2"></p>

                <div class="flex items-center justify-end gap-2 mt-4">
                    <button type="button" onclick="closePhotoModal()"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">
                        Batal
                    </button>
                    <button type="submit" id="photo-modal-submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-500">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPhotoModal(productId, productName) {
            document.getElementById('photo-modal-title').textContent = 'Upload Foto: ' + productName;
            document.getElementById('photo-modal-form').dataset.productId = productId;
            document.getElementById('photo-modal-input').value = '';
            document.getElementById('photo-modal-error').classList.add('hidden');
            document.getElementById('photo-modal').classList.remove('hidden');
        }

        function closePhotoModal() {
            document.getElementById('photo-modal').classList.add('hidden');
        }

        document.getElementById('photo-modal-form').addEventListener('submit', function (e) {
            e.preventDefault();

            const productId = this.dataset.productId;
            const fileInput = document.getElementById('photo-modal-input');
            const errorEl   = document.getElementById('photo-modal-error');
            const submitBtn = document.getElementById('photo-modal-submit');

            errorEl.classList.add('hidden');

            if (!fileInput.files.length) {
                errorEl.textContent = 'Pilih file foto terlebih dahulu.';
                errorEl.classList.remove('hidden');
                return;
            }

            // Built from the form itself so it picks up the file input, the
            // CSRF token, and the @method('PATCH') spoof field automatically.
            const formData = new FormData(this);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Mengupload...';

            // Real verb must stay POST — PHP only parses multipart/form-data
            // (and therefore $_FILES) on POST requests. PATCH is spoofed via
            // the _method field above instead of a native PATCH verb.
            fetch(`/products/${productId}/photo`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        throw new Error(data.errors?.image?.[0] || data.message || 'Upload gagal.');
                    }
                    return data;
                })
                .then((data) => {
                    const target = document.querySelector(`[data-product-photo="${productId}"]`);
                    if (target) {
                        target.innerHTML = `<img src="${data.image_url}" class="h-full w-full object-cover" alt="">`;
                    }
                    closePhotoModal();
                })
                .catch((err) => {
                    errorEl.textContent = err.message;
                    errorEl.classList.remove('hidden');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload';
                });
        });
    </script>
</x-app-layout>