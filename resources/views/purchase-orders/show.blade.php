<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Purchase Order
            </h2>

            {{-- Tombol Send WA --}}
            <a href="{{ route('purchase-orders.send-whatsapp', $purchaseOrder->po_id) }}"
               target="_blank"
               class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                📲 Kirim WA
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <div class="mb-6">
                    <p>
                        <strong>No. PO:</strong>
                        {{ 'PO-' . str_pad($purchaseOrder->po_id, 3, '0', STR_PAD_LEFT) }}
                    </p>
                    <p>
                        <strong>Supplier:</strong>
                        {{ $purchaseOrder->supplier->supplier_name }}
                    </p>
                    <p>
                        <strong>Tanggal:</strong>
                        {{ $purchaseOrder->po_date }}
                    </p>
                    <p>
                        <strong>Status:</strong>
                        {{ $purchaseOrder->status }}
                    </p>
                    <p>
                        <strong>Catatan:</strong>
                        {{ $purchaseOrder->notes }}
                    </p>
                </div>

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Produk
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Qty Beli
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Satuan Beli
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Kuantitas
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Satuan
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Harga
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Diskon
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Subtotal
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">

                        @foreach($purchaseOrder->details as $detail)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $detail->product->product_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $detail->purchase_qty }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $detail->purchase_unit }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $detail->quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $detail->unit }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    Rp {{ number_format($detail->price, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $detail->discount }}%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach

                    </tbody>

                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-right font-semibold text-gray-700">
                                Total Keseluruhan
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-900 whitespace-nowrap">
                                Rp {{ number_format($purchaseOrder->details->sum('subtotal'), 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>

                </table>

            </div>

        </div>
    </div>
</x-app-layout>