<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tambah Pembelian
            </h2>
            <a href="{{ route('purchases.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- DAFTAR PO STATUS ORDERED --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-800">
                        Pilih Purchase Order
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Klik pada PO yang barangnya sudah diterima dari supplier.
                    </p>
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal PO</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($purchaseOrders as $po)
                            <tr class="hover:bg-slate-50 cursor-pointer"
                                onclick="loadPO({{ $po->po_id }}, '{{ $po->supplier->supplier_name }}')">
                                <td class="px-6 py-4 text-sm font-medium text-indigo-600">
                                    PO-{{ str_pad($po->po_id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $po->supplier->supplier_name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $po->po_date }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                        {{ $po->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button type="button"
                                        onclick="event.stopPropagation(); loadPO({{ $po->po_id }}, '{{ $po->supplier->supplier_name }}')"
                                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                        Pilih →
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tidak ada Purchase Order dengan status Ordered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- FORM PENERIMAAN BARANG --}}
            <div id="receive-section" class="hidden">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">

                    <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form">
                        @csrf
                        <input type="hidden" name="po_id" id="selected-po-id">

                        <div class="space-y-6">

                            {{-- Info PO --}}
                            <div class="flex items-center gap-6 p-4 bg-indigo-50 rounded-lg">
                                <div>
                                    <p class="text-xs text-indigo-500 font-medium">Purchase Order</p>
                                    <p class="text-sm font-bold text-indigo-800" id="display-po-number">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-indigo-500 font-medium">Supplier</p>
                                    <p class="text-sm font-bold text-indigo-800" id="display-supplier">-</p>
                                </div>
                            </div>

                            {{-- Tanggal Pembelian --}}
                            <div>
                                <x-input-label for="purchase_date" :value="__('Tanggal Diterima')" />
                                <x-text-input id="purchase_date" name="purchase_date" type="date"
                                    class="mt-1 block w-full"
                                    value="{{ old('purchase_date', date('Y-m-d')) }}"
                                    required />
                                <x-input-error :messages="$errors->get('purchase_date')" class="mt-2" />
                            </div>

                            {{-- Notes --}}
                            <div>
                                <x-input-label for="notes" :value="__('Notes')" />
                                <textarea name="notes" id="notes" rows="2"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >{{ old('notes') }}</textarea>
                            </div>

                            {{-- Tabel Items --}}
                            <div>
                                <h3 class="text-base font-semibold text-gray-800 mb-3">
                                    Detail Barang Diterima
                                </h3>

                                <div id="items-loading" class="hidden text-sm text-gray-400 italic py-4">
                                    Memuat data barang...
                                </div>

                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Qty</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Unit</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Harga / Unit</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount %</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="items-body" class="bg-white divide-y divide-gray-200">
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="7" class="px-4 py-3 text-right text-sm font-bold text-gray-700">
                                                    Grand Total
                                                </td>
                                                <td class="px-4 py-3 text-sm font-bold text-gray-900 whitespace-nowrap" id="grand-total">
                                                    Rp 0
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            {{-- Buttons --}}
                            <div class="flex items-center justify-end gap-4">
                                <button type="button"
                                    onclick="document.getElementById('receive-section').classList.add('hidden')"
                                    class="underline text-sm text-gray-600 hover:text-gray-900">
                                    Batal
                                </button>

                                <button type="submit"
                                    style="background-color: #16a34a; color: white; padding: 8px 24px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer;">
                                    ✓ Terima Barang
                                </button>
                            </div>

                        </div>
                    </form>x
                </div>
            </div>

        </div>
    </div>

    <script>
        function loadPO(poId, supplierName) {
            const section     = document.getElementById('receive-section');
            const loading     = document.getElementById('items-loading');
            const itemsBody   = document.getElementById('items-body');
            const grandTotal  = document.getElementById('grand-total');

            // Set info
            document.getElementById('selected-po-id').value      = poId;
            document.getElementById('display-po-number').textContent =
                'PO-' + String(poId).padStart(3, '0');
            document.getElementById('display-supplier').textContent = supplierName;

            // Tampilkan section
            section.classList.remove('hidden');
            loading.classList.remove('hidden');
            itemsBody.innerHTML = '';
            grandTotal.textContent = 'Rp 0';

            // Scroll ke form
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Fetch items
            fetch(`/purchase-orders/${poId}/items`)
                .then(res => res.json())
                .then(data => {
                    loading.classList.add('hidden');
                    itemsBody.innerHTML = '';

                    data.items.forEach((item) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <input type="hidden" name="product_id[]" value="${item.product_id}">
                            <input type="hidden" name="unit[]" value="${item.unit}">
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">${item.product_name}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">${item.purchase_qty} ${item.purchase_unit}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">${item.purchase_unit}</td>
                            <td class="px-4 py-3">
                                <input type="number" name="quantity[]" value="${item.quantity}" min="0"
                                    class="qty-input w-28 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">${item.unit}</td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.01" name="price[]" value="${item.price}"
                                    class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.01" name="discount[]" value="${item.discount}"
                                    class="discount-input w-20 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-800 whitespace-nowrap subtotal-display">
                                Rp 0
                            </td>
                        `;
                        itemsBody.appendChild(tr);
                        attachRowListeners(tr);
                        updateSubtotal(tr);
                    });

                    updateGrandTotal();
                });
        }

        // ─── Helpers ──────────────────────────────────────────────────────────

        function calcSubtotal(row) {
            const qty      = parseFloat(row.querySelector('.qty-input').value)      || 0;
            const price    = parseFloat(row.querySelector('.price-input').value)    || 0;
            const discount = parseFloat(row.querySelector('.discount-input').value) || 0;
            return qty * price * (1 - discount / 100);
        }

        function updateSubtotal(row) {
            const subtotal = calcSubtotal(row);
            row.querySelector('.subtotal-display').textContent =
                'Rp ' + subtotal.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
            updateGrandTotal();
        }

        function updateGrandTotal() {
            let total = 0;
            document.querySelectorAll('#items-body tr').forEach(row => {
                total += calcSubtotal(row);
            });
            document.getElementById('grand-total').textContent =
                'Rp ' + total.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
        }

        function attachRowListeners(row) {
            row.querySelectorAll('.qty-input, .price-input, .discount-input').forEach(input => {
                input.addEventListener('input', () => updateSubtotal(row));
            });
        }
    </script>

</x-app-layout>