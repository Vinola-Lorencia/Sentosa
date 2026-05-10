<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create Purchase Order
            </h2>

            <a href="{{ route('purchase-orders.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('purchase-orders.store') }}" method="POST" id="po-form">
                    @csrf

                    <div class="space-y-6">

                        {{-- Supplier --}}
                        <div>
                            <x-input-label for="supplier_id" :value="__('Supplier')" />
                            <select name="supplier_id" id="supplier_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                        </div>

                        {{-- PO Date --}}
                        <div>
                            <x-input-label for="po_date" :value="__('PO Date')" />
                            <x-text-input id="po_date" name="po_date" type="date" class="mt-1 block w-full"
                                value="{{ old('po_date', date('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('po_date')" class="mt-2" />
                        </div>

                        {{-- Status --}}
                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select name="status" id="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="Pending">Pending</option>
                                <option value="Ordered">Ordered</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="__('Notes')" />
                            <textarea name="notes" id="notes" rows="4"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        {{-- PO Items --}}
                        <div class="mt-8">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">PO Items</h3>
                                <button type="button" id="add-row"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                    Add Item
                                </button>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount (%)</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="po-items-body" class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-3 w-72">
                                                <select name="product_id[]" class="product-select w-full border-gray-300 rounded-md shadow-sm">
                                                    <option value="">Select Product</option>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">{{ $product->product_name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" name="quantity[]" min="1" value="1"
                                                    class="qty-input w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="text" name="unit[]"
                                                    class="unit-input w-28 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" step="0.01" name="price[]"
                                                    class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" step="0.01" name="discount[]" value="0"
                                                    class="discount-input w-28 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="subtotal-display text-sm text-gray-700 font-medium">0.00</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button type="button" class="remove-row text-red-600 hover:text-red-800">Remove</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Grand Total</td>
                                            <td class="px-4 py-3 text-sm font-bold text-gray-900" id="grand-total">0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div id="items-error" class="hidden mt-2 text-sm text-red-600">
                                Pilih produk untuk semua baris sebelum menyimpan.
                            </div>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('purchase-orders.index') }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                            <x-primary-button>Save Purchase Order</x-primary-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const productData = {
            @foreach($products as $product)
            "{{ $product->id }}": {
                price:    {{ (float) $product->default_buy_price }},
                discount: {{ (float) $product->default_buy_discount }},
                unit:     "{{ addslashes($product->sale_unit ?? '') }}"
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
        // ✅ Semua logic dibungkus DOMContentLoaded — tunggu TomSelect selesai load
        document.addEventListener('DOMContentLoaded', function () {

            // ─── Helpers ─────────────────────────────────────────────────────

            function calcSubtotal(row) {
                const qty      = parseFloat(row.querySelector('.qty-input').value)      || 0;
                const price    = parseFloat(row.querySelector('.price-input').value)    || 0;
                const discount = parseFloat(row.querySelector('.discount-input').value) || 0;
                return qty * price * (1 - discount / 100);
            }

            function updateSubtotal(row) {
                const subtotal = calcSubtotal(row);
                row.querySelector('.subtotal-display').textContent = subtotal.toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                updateGrandTotal();
            }

            function updateGrandTotal() {
                const rows  = document.querySelectorAll('#po-items-body tr');
                const total = Array.from(rows).reduce((sum, row) => sum + calcSubtotal(row), 0);
                document.getElementById('grand-total').textContent = total.toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function attachRowListeners(row) {
                row.querySelectorAll('.qty-input, .price-input, .discount-input').forEach(input => {
                    input.addEventListener('input', () => updateSubtotal(row));
                });
            }

            // ─── TomSelect ───────────────────────────────────────────────────

            function initializeTomSelect(element) {
                const row = element.closest('tr');

                new TomSelect(element, {
                    create: false,
                    sortField: { field: 'text', direction: 'asc' },
                    onChange(value) {
                        if (!value) return;
                        const data = productData[value];
                        if (!data) return;

                        row.querySelector('.price-input').value    = data.price;
                        row.querySelector('.discount-input').value = data.discount;
                        row.querySelector('.unit-input').value     = data.unit;

                        requestAnimationFrame(() => updateSubtotal(row));
                    }
                });
            }

            // ─── Init baris pertama ──────────────────────────────────────────

            document.querySelectorAll('#po-items-body tr').forEach(row => {
                initializeTomSelect(row.querySelector('.product-select'));
                attachRowListeners(row);
            });

            // ─── Add Item ────────────────────────────────────────────────────

            document.getElementById('add-row').addEventListener('click', function () {
                const tbody = document.getElementById('po-items-body');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-4 py-3 w-72">
                        <select name="product_id[]" class="product-select w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Select Product</option>
                            ${productOptionsHTML}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" name="quantity[]" min="1" value="1"
                            class="qty-input w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3">
                        <input type="text" name="unit[]"
                            class="unit-input w-28 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" step="0.01" name="price[]"
                            class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" step="0.01" name="discount[]" value="0"
                            class="discount-input w-28 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3">
                        <span class="subtotal-display text-sm text-gray-700 font-medium">0.00</span>
                    </td>
                    <td class="px-4 py-3">
                        <button type="button" class="remove-row text-red-600 hover:text-red-800">Remove</button>
                    </td>
                `;
                tbody.appendChild(tr);
                initializeTomSelect(tr.querySelector('.product-select'));
                attachRowListeners(tr);
            });

            // ─── Remove row ──────────────────────────────────────────────────

            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-row')) {
                    const rows = document.querySelectorAll('#po-items-body tr');
                    if (rows.length > 1) {
                        const row = e.target.closest('tr');
                        const sel = row.querySelector('.product-select');
                        if (sel?.tomselect) sel.tomselect.destroy();
                        row.remove();
                        updateGrandTotal();
                    }
                }
            });

            // ─── Validasi submit ─────────────────────────────────────────────

            document.getElementById('po-form').addEventListener('submit', function (e) {
                const emptyProduct = Array.from(
                    document.querySelectorAll('#po-items-body tr .product-select.ts-hidden-accessible')
                ).some(sel => !sel.value);

                if (emptyProduct) {
                    e.preventDefault();
                    const errEl = document.getElementById('items-error');
                    errEl.classList.remove('hidden');
                    errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    document.getElementById('items-error').classList.add('hidden');
                }
            });

        }); // end DOMContentLoaded
    </script>

</x-app-layout>