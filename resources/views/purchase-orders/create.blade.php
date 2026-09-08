<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Buat Purchase Order
            </h2>

            <a href="{{ route('purchase-orders.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('purchase-orders.store') }}"
                      method="POST"
                      id="po-form">

                    @csrf

                    <div class="space-y-6">

                        {{-- Supplier --}}
                        <div>
                            <x-input-label for="supplier_id" :value="'Supplier'" />

                            <select
                                name="supplier_id"
                                id="supplier_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Pilih Supplier</option>

                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">
                                        {{ $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>

                            <x-input-error
                                :messages="$errors->get('supplier_id')"
                                class="mt-2"
                            />
                        </div>

                        {{-- PO Date --}}
                        <div>
                            <x-input-label for="po_date" :value="'Tanggal PO'" />

                            <x-text-input
                                id="po_date"
                                name="po_date"
                                type="date"
                                class="mt-1 block w-full"
                                value="{{ old('po_date', date('Y-m-d')) }}"
                                required
                            />

                            <x-input-error
                                :messages="$errors->get('po_date')"
                                class="mt-2"
                            />
                        </div>

                        {{-- Status --}}
                        <div>
                            <x-input-label for="status" :value="'Status'" />

                            <select
                                name="status"
                                id="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="Pending">Pending</option>
                                <option value="Ordered">Ordered</option>
                                <option value="Completed">Completed</option>
                            </select>

                            <x-input-error
                                :messages="$errors->get('status')"
                                class="mt-2"
                            />
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="'Catatan'" />

                            <textarea
                                name="notes"
                                id="notes"
                                rows="4"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >{{ old('notes') }}</textarea>

                            <x-input-error
                                :messages="$errors->get('notes')"
                                class="mt-2"
                            />
                        </div>

                        {{-- PO ITEMS --}}
                        <div class="mt-8">

                            <div class="flex items-center justify-between mb-4">

                                <h3 class="text-lg font-semibold text-gray-800">
                                    Item PO
                                </h3>

                                <button
                                    type="button"
                                    id="add-row"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500"
                                >
                                    Tambah Item
                                </button>

                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">

                                <table class="min-w-full divide-y divide-gray-200">

                                    <thead class="bg-gray-50">

                                        <tr>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Produk
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Qty Beli
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Satuan Beli
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Kuantitas
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Satuan
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Harga / Satuan
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Diskon %
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Subtotal
                                            </th>

                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Aksi
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody id="po-items-body"
                                           class="bg-white divide-y divide-gray-200">

                                        <tr>

                                            {{-- Product --}}
                                            <td class="px-4 py-3 w-72">

                                                <select
                                                    name="product_id[]"
                                                    class="product-select w-full border-gray-300 rounded-md shadow-sm"
                                                >
                                                    <option value="">
                                                        Pilih Produk
                                                    </option>

                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">
                                                            {{ $product->product_name }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                            </td>

                                            {{-- Purchase Qty --}}
                                            <td class="px-4 py-3">

                                                <input
                                                    type="number"
                                                    name="purchase_qty[]"
                                                    min="1"
                                                    value="1"
                                                    class="qty-input w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >

                                            </td>

                                            {{-- Purchase Unit --}}
                                            <td class="px-4 py-3">

                                                <div class="purchase-unit text-sm text-gray-700">
                                                    -
                                                </div>

                                            </td>

                                            {{-- Quantity Result --}}
                                            <td class="px-4 py-3">

                                                <div class="converted-qty text-sm font-semibold text-indigo-700">
                                                    0
                                                </div>

                                            </td>

                                            {{-- Sale Unit --}}
                                            <td class="px-4 py-3">

                                                <div class="sale-unit text-sm text-gray-700">
                                                    -
                                                </div>

                                            </td>

                                            {{-- Price --}}
                                            <td class="px-4 py-3">

                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="price[]"
                                                    class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >

                                            </td>

                                            {{-- Discount --}}
                                            <td class="px-4 py-3">

                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="discount[]"
                                                    value="0"
                                                    class="discount-input w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >

                                            </td>

                                            {{-- Subtotal --}}
                                            <td class="px-4 py-3">

                                                <span class="subtotal-display text-sm font-semibold text-gray-800">
                                                    0.00
                                                </span>

                                            </td>

                                            {{-- Action --}}
                                            <td class="px-4 py-3">

                                                <button
                                                    type="button"
                                                    class="remove-row text-red-600 hover:text-red-800"
                                                >
                                                    Hapus
                                                </button>

                                            </td>

                                        </tr>

                                    </tbody>

                                    <tfoot class="bg-gray-50">

                                        <tr>

                                            <td colspan="7"
                                                class="px-4 py-3 text-right text-sm font-bold text-gray-700">
                                                Total Keseluruhan
                                            </td>

                                            <td class="px-4 py-3 text-sm font-bold text-gray-900"
                                                id="grand-total">
                                                0.00
                                            </td>

                                            <td></td>

                                        </tr>

                                    </tfoot>

                                </table>

                            </div>

                        </div>

                        {{-- BUTTONS --}}
                        <div class="flex items-center justify-end gap-4">

                            <a href="{{ route('purchase-orders.index') }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900">
                                Batal
                            </a>

                            <x-primary-button>
                                Simpan Purchase Order
                            </x-primary-button>

                        </div>

                    </div>

                </form>

            </div>

        </div>
    </div>

    {{-- PRODUCT DATA --}}
    <script>

        const productData = {

            @foreach($products as $product)

            "{{ $product->id }}": {

                price: {{ (float) $product->default_buy_price }},

                discount: {{ (float) $product->default_buy_discount }},

                purchase_unit: "{{ addslashes($product->purchase_unit) }}",

                sale_unit: "{{ addslashes($product->sale_unit) }}",

                conversion: {{ (float) $product->dus_to_unit }}

            },

            @endforeach

        };

        const productOptionsHTML = `
            @foreach($products as $product)
                <option value="{{ $product->id }}">
                    {{ addslashes($product->product_name) }}
                </option>
            @endforeach
        `;

    </script>

    {{-- SCRIPT --}}
    <script>

        document.addEventListener('DOMContentLoaded', function () {

            // ─────────────────────────────
            // CALCULATE
            // ─────────────────────────────

            function calculateRow(row) {

                const productId =
                    row.querySelector('.product-select').value;

                if (!productId || !productData[productId]) {
                    return;
                }

                const data = productData[productId];

                const purchaseQty =
                    parseFloat(row.querySelector('.qty-input').value) || 0;

                const convertedQty =
                    purchaseQty * data.conversion;

                row.querySelector('.converted-qty').textContent =
                    convertedQty;

                const price =
                    parseFloat(row.querySelector('.price-input').value) || 0;

                const discount =
                    parseFloat(row.querySelector('.discount-input').value) || 0;

                // HARGA PER LUSIN / SALE UNIT
                const subtotal =
                    convertedQty * price * (1 - discount / 100);

                row.querySelector('.subtotal-display').textContent =
                    subtotal.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                updateGrandTotal();
            }

            // ─────────────────────────────
            // GRAND TOTAL
            // ─────────────────────────────

            function updateGrandTotal() {

                let total = 0;

                document.querySelectorAll('#po-items-body tr')
                    .forEach(row => {

                        const subtotal =
                            parseFloat(
                                row.querySelector('.subtotal-display')
                                    .textContent
                                    .replace(/\./g, '')
                                    .replace(',', '.')
                            ) || 0;

                        total += subtotal;
                    });

                document.getElementById('grand-total').textContent =
                    total.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
            }

            // ─────────────────────────────
            // ROW LISTENER
            // ─────────────────────────────

            function attachRowListeners(row) {

                row.querySelectorAll(
                    '.qty-input, .price-input, .discount-input'
                ).forEach(input => {

                    input.addEventListener('input', () => {
                        calculateRow(row);
                    });

                });
            }

            // ─────────────────────────────
            // TOMSELECT
            // ─────────────────────────────

            function initializeTomSelect(element) {

                const row = element.closest('tr');

                new TomSelect(element, {

                    create: false,

                    sortField: {
                        field: 'text',
                        direction: 'asc'
                    },

                    onChange(value) {

                        if (!value) return;

                        const data = productData[value];

                        if (!data) return;

                        row.querySelector('.price-input').value =
                            data.price;

                        row.querySelector('.discount-input').value =
                            data.discount;

                        row.querySelector('.purchase-unit').textContent =
                            data.purchase_unit;

                        row.querySelector('.sale-unit').textContent =
                            data.sale_unit;

                        calculateRow(row);
                    }

                });
            }

            // ─────────────────────────────
            // INIT FIRST ROW
            // ─────────────────────────────

            document.querySelectorAll('#po-items-body tr')
                .forEach(row => {

                    initializeTomSelect(
                        row.querySelector('.product-select')
                    );

                    attachRowListeners(row);

                });

            // ─────────────────────────────
            // ADD ROW
            // ─────────────────────────────

            document.getElementById('add-row')
                .addEventListener('click', function () {

                    const tbody =
                        document.getElementById('po-items-body');

                    const tr = document.createElement('tr');

                    tr.innerHTML = `
                        <td class="px-4 py-3 w-72">
                            <select
                                name="product_id[]"
                                class="product-select w-full border-gray-300 rounded-md shadow-sm"
                            >
                                <option value="">Pilih Produk</option>
                                ${productOptionsHTML}
                            </select>
                        </td>

                        <td class="px-4 py-3">
                            <input
                                type="number"
                                name="purchase_qty[]"
                                min="1"
                                value="1"
                                class="qty-input w-24 border-gray-300 rounded-md shadow-sm"
                            >
                        </td>

                        <td class="px-4 py-3">
                            <div class="purchase-unit text-sm text-gray-700">-</div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="converted-qty text-sm font-semibold text-indigo-700">
                                0
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="sale-unit text-sm text-gray-700">-</div>
                        </td>

                        <td class="px-4 py-3">
                            <input
                                type="number"
                                step="0.01"
                                name="price[]"
                                class="price-input w-36 border-gray-300 rounded-md shadow-sm"
                            >
                        </td>

                        <td class="px-4 py-3">
                            <input
                                type="number"
                                step="0.01"
                                name="discount[]"
                                value="0"
                                class="discount-input w-24 border-gray-300 rounded-md shadow-sm"
                            >
                        </td>

                        <td class="px-4 py-3">
                            <span class="subtotal-display text-sm font-semibold text-gray-800">
                                0.00
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="remove-row text-red-600 hover:text-red-800"
                            >
                                Hapus
                            </button>
                        </td>
                    `;

                    tbody.appendChild(tr);

                    initializeTomSelect(
                        tr.querySelector('.product-select')
                    );

                    attachRowListeners(tr);

                });

            // ─────────────────────────────
            // REMOVE ROW
            // ─────────────────────────────

            document.addEventListener('click', function (e) {

                if (e.target.classList.contains('remove-row')) {

                    const rows =
                        document.querySelectorAll('#po-items-body tr');

                    if (rows.length > 1) {

                        e.target.closest('tr').remove();

                        updateGrandTotal();
                    }
                }
            });

        });

    </script>

</x-app-layout>
