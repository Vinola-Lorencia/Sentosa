<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Product') }}</h2>
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">

                        <div>
                            <x-input-label for="product_name" :value="__('Product Name')" />
                            <x-text-input id="product_name" name="product_name" type="text" class="mt-1 block w-full" value="{{ old('product_name', $product->product_name) }}" required autofocus />
                            <x-input-error :messages="$errors->get('product_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="brand" :value="__('Brand')" />
                            <x-text-input id="brand" name="brand" type="text" class="mt-1 block w-full" value="{{ old('brand', $product->brand) }}" />
                            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                        </div>

                        {{-- Gambar --}}
                        <div>
                            <x-input-label for="image" :value="__('Gambar Produk')" />

                            {{-- Gambar saat ini --}}
                            @if($product->image)
                                <div class="mt-2 mb-3">
                                    <img src="{{ Storage::url($product->image) }}" alt="{{ $product->product_name }}"
                                        class="h-32 w-32 object-cover rounded-lg border border-gray-200">
                                    <div class="mt-2 flex items-center gap-2">
                                        <input type="checkbox" name="remove_image" id="remove_image" value="1">
                                        <label for="remove_image" class="text-xs text-red-600 cursor-pointer">
                                            Hapus gambar ini
                                        </label>
                                    </div>
                                </div>
                            @endif

                            <input type="file" name="image" id="image"
                                accept="image/jpg,image/jpeg,image/png,image/webp"
                                class="mt-1 block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4 file:rounded-md
                                    file:border-0 file:text-sm file:font-semibold
                                    file:bg-indigo-50 file:text-indigo-700
                                    hover:file:bg-indigo-100"
                                onchange="previewImage(event)">
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP. Maks 2MB. Kosongkan jika tidak ingin mengubah gambar.</p>
                            <x-input-error :messages="$errors->get('image')" class="mt-2" />

                            {{-- Preview gambar baru --}}
                            <div id="image-preview" class="hidden mt-3">
                                <p class="text-xs text-gray-500 mb-1">Preview gambar baru:</p>
                                <img id="preview-img" src="" alt="Preview"
                                    class="h-32 w-32 object-cover rounded-lg border border-gray-200">
                            </div>
                        </div>

                        <div>
                            <x-input-label for="default_buy_price" :value="__('Default Buy Price')" />
                            <x-text-input id="default_buy_price" name="default_buy_price" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('default_buy_price', $product->default_buy_price) }}" required />
                            <x-input-error :messages="$errors->get('default_buy_price')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="default_buy_discount" :value="__('Default Buy Discount (%)')" />
                            <x-text-input id="default_buy_discount" name="default_buy_discount" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('default_buy_discount', $product->default_buy_discount) }}" required />
                            <x-input-error :messages="$errors->get('default_buy_discount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sale_price" :value="__('Sale Price')" />
                            <x-text-input id="sale_price" name="sale_price" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('sale_price', $product->sale_price) }}" required />
                            <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sale_unit" :value="__('Sale Unit')" />
                            <x-text-input id="sale_unit" name="sale_unit" type="text" class="mt-1 block w-full" value="{{ old('sale_unit', $product->sale_unit) }}" required />
                            <x-input-error :messages="$errors->get('sale_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="units_per_sale_unit" :value="__('Jumlah per Unit Jual')" />
                            <x-text-input id="units_per_sale_unit" name="units_per_sale_unit" type="number" min="1" class="mt-1 block w-full" value="{{ old('units_per_sale_unit', $product->units_per_sale_unit) }}" required />
                            <p class="text-xs text-gray-500 mt-1">Contoh: jika satuan jual = lusin, isi 12. Jika satuan jual = buah, isi 1.</p>
                            <x-input-error :messages="$errors->get('units_per_sale_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="purchase_unit" :value="__('Purchase Unit')" />
                            <x-text-input id="purchase_unit" name="purchase_unit" type="text" class="mt-1 block w-full" value="{{ old('purchase_unit', $product->purchase_unit) }}" required />
                            <x-input-error :messages="$errors->get('purchase_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="dus_to_unit" :value="__('Dus to Unit')" />
                            <x-text-input id="dus_to_unit" name="dus_to_unit" type="number" class="mt-1 block w-full" value="{{ old('dus_to_unit', $product->dus_to_unit) }}" required />
                            <x-input-error :messages="$errors->get('dus_to_unit')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="stock" :value="__('Stock')" />
                            <x-text-input id="stock" name="stock" type="number" class="mt-1 block w-full" value="{{ old('stock', $product->stock) }}" required />
                            <x-input-error :messages="$errors->get('stock')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('products.index') }}" class="underline text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                            <x-primary-button>{{ __('Update Product') }}</x-primary-button>
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
    </script>

</x-app-layout>