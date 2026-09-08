<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Product') }}</h2>
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="space-y-6">

                        <div>
                            <x-input-label for="product_name" :value="__('Product Name')" />
                            <x-text-input id="product_name" name="product_name" type="text" class="mt-1 block w-full" value="{{ old('product_name') }}" required autofocus autocomplete="off" />
                            <x-input-error :messages="$errors->get('product_name')" class="mt-2" />
                            <div id="product-name-warning" class="hidden mt-2 rounded-md bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-800"></div>
                        </div>

                        <div>
                            <x-input-label for="brand" :value="__('Brand')" />
                            <x-text-input id="brand" name="brand" type="text" class="mt-1 block w-full" value="{{ old('brand') }}" />
                            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                        </div>

                        {{-- Gambar --}}
                        <div>
                            <x-input-label for="image" :value="__('Gambar Produk')" />
                            <input type="file" name="image" id="image"
                                accept="image/jpg,image/jpeg,image/png,image/webp"
                                class="mt-1 block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4 file:rounded-md
                                    file:border-0 file:text-sm file:font-semibold
                                    file:bg-indigo-50 file:text-indigo-700
                                    hover:file:bg-indigo-100"
                                onchange="previewImage(event)"
                                required>
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP. Maks 2MB.</p>
                            <x-input-error :messages="$errors->get('image')" class="mt-2" />

                            {{-- Preview --}}
                            <div id="image-preview" class="hidden mt-3">
                                <img id="preview-img" src="" alt="Preview"
                                    class="h-32 w-32 object-cover rounded-lg border border-gray-200">
                            </div>
                        </div>

                        <div>
                            <x-input-label for="default_buy_price" :value="__('Default Buy Price')" />
                            <x-text-input id="default_buy_price" name="default_buy_price" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('default_buy_price', 0) }}" />
                            <x-input-error :messages="$errors->get('default_buy_price')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="default_buy_discount" :value="__('Default Buy Discount (%)')" />
                            <x-text-input id="default_buy_discount" name="default_buy_discount" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('default_buy_discount', 0) }}" />
                            <x-input-error :messages="$errors->get('default_buy_discount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sale_price" :value="__('Sale Price')" />
                            <x-text-input id="sale_price" name="sale_price" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('sale_price', 0) }}" />
                            <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sale_unit" :value="__('Sale Unit')" />
                            <select id="sale_unit" name="sale_unit"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Pilih Sale Unit --</option>
                                @foreach(['Buah', 'Lusin', 'Kodi', 'Gross', 'Set'] as $option)
                                    <option value="{{ $option }}"
                                        {{ strtolower(old('sale_unit', 'Buah')) === strtolower($option) ? 'selected' : '' }}>
                                        {{ $option }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sale_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="purchase_unit" :value="__('Purchase Unit')" />
                            <select id="purchase_unit" name="purchase_unit"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Pilih Purchase Unit --</option>
                                @foreach(['Dus', 'Goni', 'Karton', 'Ikat'] as $option)
                                    <option value="{{ $option }}"
                                        {{ strtolower(old('purchase_unit', 'Goni')) === strtolower($option) ? 'selected' : '' }}>
                                        {{ $option }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('purchase_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="dus_to_unit" :value="__('Dus to Unit')" />
                            <x-text-input id="dus_to_unit" name="dus_to_unit" type="number" class="mt-1 block w-full" value="{{ old('dus_to_unit', 1) }}" />
                            <x-input-error :messages="$errors->get('dus_to_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label :value="__('Stock')" />
                            <div class="mt-1 grid grid-cols-2 gap-4">
                                <div>
                                    <x-text-input id="stock_qty" name="stock_qty" type="number" step="any" min="0" class="block w-full" value="{{ old('stock_qty', 0) }}" required />
                                    <x-input-error :messages="$errors->get('stock_qty')" class="mt-2" />
                                </div>
                                <div>
                                    <select id="stock_unit" name="stock_unit"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required>
                                        @foreach(['Buah', 'Lusin', 'Kodi', 'Gross'] as $option)
                                            <option value="{{ $option }}"
                                                {{ strtolower(old('stock_unit', 'Buah')) === strtolower($option) ? 'selected' : '' }}>
                                                {{ $option }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('stock_unit')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('products.index') }}" class="underline text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                            <x-primary-button>{{ __('Save Product') }}</x-primary-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        // Real-time duplicate product name check (debounced, non-blocking).
        let productNameCheckTimeout = null;
        const productNameInput   = document.getElementById('product_name');
        const productNameWarning = document.getElementById('product-name-warning');

        productNameInput.addEventListener('input', function () {
            clearTimeout(productNameCheckTimeout);

            const name = this.value.trim();
            if (!name) {
                productNameWarning.classList.add('hidden');
                return;
            }

            productNameCheckTimeout = setTimeout(() => {
                fetch(`/api/check-product-name?name=${encodeURIComponent(name)}`, {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data.found && data.matches.length) {
                            productNameWarning.textContent =
                                `⚠️ Produk dengan nama mirip sudah ada: ${data.matches.join(', ')}. Pastikan ini bukan duplikat sebelum menyimpan.`;
                            productNameWarning.classList.remove('hidden');
                        } else {
                            productNameWarning.classList.add('hidden');
                        }
                    })
                    .catch(() => {
                        productNameWarning.classList.add('hidden');
                    });
            }, 500);
        });
    </script>

</x-app-layout>