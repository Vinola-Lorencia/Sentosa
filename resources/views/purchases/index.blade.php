<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Pembelian
            </h2>

            <a href="{{ route('purchases.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                Create
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($purchases as $purchase)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ str_pad($purchase->purchase_id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $purchase->purchase_date }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $purchase->purchaseOrder ? 'PO-' . str_pad($purchase->po_id, 3, '0', STR_PAD_LEFT) : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $purchase->supplier->supplier_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('purchases.show', $purchase->purchase_id) }}"
                                           class="text-indigo-600 hover:text-indigo-800">View</a>

                                        <a href="{{ route('purchases.edit', $purchase->purchase_id) }}"
                                           class="text-yellow-600 hover:text-yellow-800">Edit</a>

                                        <form action="{{ route('purchases.destroy', $purchase->purchase_id) }}"
                                              method="POST"
                                              onsubmit="return confirm('Hapus transaksi ini? Stok akan dikembalikan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Belum ada transaksi pembelian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $purchases->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>