<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Pembelian
            </h2>
            <a href="{{ route('purchases.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if($errors->any())
                    <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('purchases.update', $purchase->purchase_id) }}"
                      method="POST" id="purchase-form">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">

                        {{-- Info PO (readonly) --}}
                        <div class="p-4 bg-indigo-50 rounded-lg">
                            <p class="text-xs text-indigo-500 font-medium">Purchase Order</p>
                            <p class="text-sm font-bold text-indigo-800 mt-1">
                                PO-{{ str_pad($purchase->po_id, 3, '0', STR_PAD_LEFT) }} —
                                {{ $purchase->supplier->supplier_name }}
                            </p>
                        </div>

                        {{-- Tanggal Pembelian --}}
                        <div>
                            <x-input-label for="purchase_date" :value="__('Tanggal Pembelian')" />
                            <x-text-input id="purchase_date" name="purchase_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('purchase_date', $purchase->purchase_date) }}"
                                required />
                            <x-input-error :messages="$errors->get('purchase_date')" class="mt-2" />
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="__('Notes')" />
                            <textarea name="notes" id="notes" rows="2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $purchase->notes) }}</textarea>
                        </div>

                        {{-- Items --}}
                        <div class="mt-8">
                            <h3 class="text-base font-semibold text-gray-800 mb-4">Detail Barang</h3>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purchase Qty</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purchase Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Harga / Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount %</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-body" class="bg-white divide-y divide-gray-200">
                                        @foreach($purchase->details as $detail)
                                            <tr>
                                                <input type="hidden" name="product_id[]" value="{{ $detail->product_id }}">
                                                <input type="hidden" name="unit[]" value="{{ $detail->unit }}">

                                                <td class="px-4 py-3 text-sm text-gray-900 font-medium">
                                                    {{ $detail->product->product_name }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500">
                                                    {{ $detail->purchase_qty ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500">
                                                    {{ $detail->purchase_unit ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number" name="quantity[]"
                                                        value="{{ $detail->quantity }}"
                                                        min="0" step="0.01"
                                                        class="qty-input w-28 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ $detail->unit }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number" step="0.01" name="price[]"
                                                        value="{{ $detail->price }}"
                                                        class="price-input w-36 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number" step="0.01" name="discount[]"
                                                        value="{{ $detail->discount }}"
                                                        class="discount-input w-20 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-800 whitespace-nowrap subtotal-display">
                                                    Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="7" class="px-4 py-3 text-right text-sm font-bold text-gray-700">
                                                Grand Total
                                            </td>
                                            <td class="px-4 py-3 text-sm font-bold text-gray-900 whitespace-nowrap" id="grand-total">
                                                Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('purchases.show', $purchase->purchase_id) }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900">Batal</a>
                            <x-primary-button>Update Pembelian</x-primary-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

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

            document.querySelectorAll('#items-body tr').forEach(row => {
                row.querySelectorAll('.qty-input, .price-input, .discount-input').forEach(input => {
                    input.addEventListener('input', () => updateSubtotal(row));
                });
            });

        });
    </script>

</x-app-layout>