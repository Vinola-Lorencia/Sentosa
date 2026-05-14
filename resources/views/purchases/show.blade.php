<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Pembelian
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

                {{-- Header Info --}}
                <div class="grid grid-cols-2 gap-6 mb-8 p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">No. Pembelian</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            INV-{{ str_pad($purchase->purchase_id, 3, '0', STR_PAD_LEFT) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Purchase Order</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            @if($purchase->purchaseOrder)
                                <a href="{{ route('purchase-orders.show', $purchase->po_id) }}"
                                   class="text-indigo-600 hover:underline">
                                    PO-{{ str_pad($purchase->po_id, 3, '0', STR_PAD_LEFT) }}
                                </a>
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Supplier</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            {{ $purchase->supplier->supplier_name ?? '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Tanggal Diterima</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            {{ $purchase->purchase_date }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Notes</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            {{ $purchase->notes ?? '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Total Pembelian</p>
                        <p class="text-sm font-bold text-green-700 mt-1">
                            Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                {{-- Detail Barang --}}
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Harga / Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($purchase->details as $detail)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $detail->product->product_name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $detail->quantity }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $detail->unit }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        Rp {{ number_format($detail->price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $detail->discount }}%
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 whitespace-nowrap">
                                        Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-right text-sm font-bold text-gray-700">
                                    Grand Total
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-green-700 whitespace-nowrap">
                                    Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-end gap-4 mt-6">
                    <a href="{{ route('purchases.edit', $purchase->purchase_id) }}"
                       class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-400">
                        Edit
                    </a>

                    <form action="{{ route('purchases.destroy', $purchase->purchase_id) }}"
                          method="POST"
                          onsubmit="return confirm('Hapus transaksi ini? Stok akan dikembalikan secara otomatis.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                            Delete
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>