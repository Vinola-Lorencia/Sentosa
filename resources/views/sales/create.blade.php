<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tambah Penjualan
            </h2>
            <a href="{{ route('sales.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if(session('error'))
                    <div class="mb-4 px-4 py-3 bg-red-100 border border-red-300 text-red-800 rounded-md text-sm font-medium">
                        ⚠ {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('sales.store') }}" method="POST" id="sale-form">
                    @csrf

                    <div class="space-y-6">

                        {{-- Customer --}}
                        <div>
                            <x-input-label for="customer_id" :value="__('Pelanggan')" />
                            <select name="customer_id" id="customer_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Walk-in Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">
                                        {{ $customer->customer_name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                        </div>

                        {{-- Sale Date --}}
                        <div>
                            <x-input-label for="sale_date" :value="__('Tanggal Penjualan')" />
                            <x-text-input id="sale_date" name="sale_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('sale_date', date('Y-m-d')) }}"
                                required />
                            <x-input-error :messages="$errors->get('sale_date')" class="mt-2" />
                        </div>

                        {{-- Payment Method --}}
                        <div>
                            <x-input-label for="payment_method" :value="__('Metode Pembayaran')" />
                            <select name="payment_method" id="payment_method"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="Cash">Cash</option>
                                <option value="Transfer">Transfer</option>
                                <option value="Utang">Utang</option>
                            </select>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        </div>

                        {{-- Payment Status --}}
                        <div>
                            <x-input-label for="payment_status" :value="__('Status Pembayaran')" />
                            <select name="payment_status" id="payment_status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="Lunas">Lunas</option>
                                <option value="Belum Bayar">Belum Bayar</option>
                            </select>
                            <x-input-error :messages="$errors->get('payment_status')" class="mt-2" />
                        </div>

                        {{-- Due Date --}}
                        <div id="due-date-section" class="hidden">
                            <x-input-label for="due_date" :value="__('Jatuh Tempo')" />
                            <x-text-input id="due_date" name="due_date" type="date"
                                class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="__('Catatan')" />
                            <textarea name="notes" id="notes" rows="2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>

                        {{-- Items --}}
                        <div class="mt-8">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Detail Barang</h3>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Satuan</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Harga / Satuan</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diskon %</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sale-items-body" class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-3 w-72">
                                                <select name="product_id[]" class="product-select w-full border-gray-300 rounded-md shadow-sm">
                                                    <option value="">Pilih Produk</option>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">
                                                            {{ $product->product_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="stock-display text-sm text-gray-500">-</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" name="quantity[]" min="0.25" step="0.25" value="1"
                                                    class="qty-input w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="unit-display text-sm text-gray-700">-</span>
                                                <input type="hidden" name="unit[]" class="unit-input">
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" step="0.01" name="price[]"
                                                    class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" step="0.01" name="discount[]" value="0"
                                                    class="discount-input w-20 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="subtotal-display text-sm font-semibold text-gray-800">Rp 0</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="6" class="px-4 py-3 text-right text-sm font-bold text-gray-700">
                                                Total Keseluruhan
                                            </td>
                                            <td class="px-4 py-3 text-sm font-bold text-green-700 whitespace-nowrap" id="grand-total">
                                                Rp 0
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Tombol Tambah Barang di bawah tabel --}}
                            <div class="mt-3">
                                <button type="button" id="add-row"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                    + Tambah Barang
                                </button>
                            </div>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('sales.index') }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900">Batal</a>
                            <x-primary-button>Simpan Penjualan</x-primary-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Product Data --}}
    <script>
        const productData = {
            @foreach($products as $product)
            "{{ $product->id }}": {
                price: {{ (float) $product->sale_price }},
                unit:  "{{ addslashes($product->sale_unit) }}",
                stock: {{ (float) $product->stock }},
            },
            @endforeach
        };

        const productOptionsHTML = `
            @foreach($products as $product)
            <option value="{{ $product->id }}">{{ addslashes($product->product_name) }}</option>
            @endforeach
        `;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ─── Payment method & status logic ───────────────────────────────

            const paymentMethod  = document.getElementById('payment_method');
            const paymentStatus  = document.getElementById('payment_status');
            const dueDateSection = document.getElementById('due-date-section');

            function toggleDueDate() {
                if (paymentStatus.value === 'Belum Bayar') {
                    dueDateSection.classList.remove('hidden');
                } else {
                    dueDateSection.classList.add('hidden');
                }
            }

            paymentStatus.addEventListener('change', toggleDueDate);

            paymentMethod.addEventListener('change', function () {
                if (this.value === 'Utang') {
                    paymentStatus.value = 'Belum Bayar';
                    dueDateSection.classList.remove('hidden');
                }
            });

            // ─── Helpers ──────────────────────────────────────────────────────

            function calcSubtotal(row) {
                const qty      = parseFloat(row.querySelector('.qty-input').value)      || 0;
                const price    = parseFloat(row.querySelector('.price-input').value)    || 0;
                const discount = parseFloat(row.querySelector('.discount-input').value) || 0;
                return qty * price * (1 - discount / 100);
            }

            function updateSubtotal(row) {
                const subtotal = calcSubtotal(row);
                row.querySelector('.subtotal-display').textContent =
                    'Rp ' + subtotal.toLocaleString('id-ID', { minimumFractionDigits: 0 });
                updateGrandTotal();
            }

            function updateGrandTotal() {
                let total = 0;
                document.querySelectorAll('#sale-items-body tr').forEach(row => {
                    total += calcSubtotal(row);
                });
                document.getElementById('grand-total').textContent =
                    'Rp ' + total.toLocaleString('id-ID', { minimumFractionDigits: 0 });
            }

            function attachRowListeners(row) {
                row.querySelectorAll('.qty-input, .price-input, .discount-input').forEach(input => {
                    input.addEventListener('input', () => updateSubtotal(row));
                });
            }

            // ─── TomSelect ────────────────────────────────────────────────────

            function initializeTomSelect(element) {
                const row = element.closest('tr');

                new TomSelect(element, {
                    create: false,
                    sortField: { field: 'text', direction: 'asc' },
                    onChange(value) {
                        if (!value) return;
                        const data = productData[value];
                        if (!data) return;

                        row.querySelector('.price-input').value         = data.price;
                        row.querySelector('.unit-input').value          = data.unit;
                        row.querySelector('.unit-display').textContent  = data.unit;
                        row.querySelector('.stock-display').textContent = data.stock;

                        requestAnimationFrame(() => updateSubtotal(row));
                    }
                });
            }

            // Init baris pertama
            document.querySelectorAll('#sale-items-body tr').forEach(row => {
                initializeTomSelect(row.querySelector('.product-select'));
                attachRowListeners(row);
            });

            // ─── Add row ──────────────────────────────────────────────────────

            document.getElementById('add-row').addEventListener('click', function () {
                const tbody = document.getElementById('sale-items-body');
                const tr    = document.createElement('tr');

                tr.innerHTML = `
                    <td class="px-4 py-3 w-72">
                        <select name="product_id[]" class="product-select w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Pilih Produk</option>
                            ${productOptionsHTML}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <span class="stock-display text-sm text-gray-500">-</span>
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" name="quantity[]" min="0.25" step="0.25" value="1"
                            class="qty-input w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3">
                        <span class="unit-display text-sm text-gray-700">-</span>
                        <input type="hidden" name="unit[]" class="unit-input">
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" step="0.01" name="price[]"
                            class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" step="0.01" name="discount[]" value="0"
                            class="discount-input w-20 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="subtotal-display text-sm font-semibold text-gray-800">Rp 0</span>
                    </td>
                    <td class="px-4 py-3">
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Hapus</button>
                    </td>
                `;

                tbody.appendChild(tr);
                initializeTomSelect(tr.querySelector('.product-select'));
                attachRowListeners(tr);

                // ✅ Scroll ke baris baru
                tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            // ─── Remove row ───────────────────────────────────────────────────

            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-row')) {
                    const rows = document.querySelectorAll('#sale-items-body tr');
                    if (rows.length > 1) {
                        const row = e.target.closest('tr');
                        const sel = row.querySelector('.product-select');
                        if (sel?.tomselect) sel.tomselect.destroy();
                        row.remove();
                        updateGrandTotal();
                    }
                }
            });

            // ─── Validasi submit ──────────────────────────────────────────────

            document.getElementById('sale-form').addEventListener('submit', function (e) {
                const emptyProduct = Array.from(
                    document.querySelectorAll('#sale-items-body .product-select.ts-hidden-accessible')
                ).some(sel => !sel.value);

                if (emptyProduct) {
                    e.preventDefault();
                    alert('Pilih produk untuk semua baris sebelum menyimpan.');
                }
            });

        });
    </script>

</x-app-layout>