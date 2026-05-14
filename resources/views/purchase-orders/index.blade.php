<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Purchase Orders
            </h2>

            <a href="{{ route('purchase-orders.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                New PO
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                PO Number
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Supplier
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Status
                            </th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">

                        @forelse($purchaseOrders as $po)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ 'PO-' . str_pad($po->po_id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $po->supplier->supplier_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $po->po_date }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $po->status }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">

                                    {{-- Tombol Send WA --}}
                                    <a href="{{ route('purchase-orders.send-whatsapp', $po->po_id) }}"
                                       target="_blank"
                                       class="inline-flex items-center px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-500">
                                        📲 Send WA
                                    </a>

                                    <a href="{{ route('purchase-orders.show', $po->po_id) }}"
                                       class="text-sky-600 hover:text-sky-900 ms-3">
                                        Detail
                                    </a>

                                    <a href="{{ route('purchase-orders.edit', $po->po_id) }}"
                                       class="text-indigo-600 hover:text-indigo-900 ms-3">
                                        Edit
                                    </a>

                                    <form action="{{ route('purchase-orders.destroy', $po->po_id) }}"
                                          method="POST"
                                          class="inline-block ms-3"
                                          onsubmit="return confirm('Delete this purchase order?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-900">
                                            Delete
                                        </button>
                                    </form>

                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No purchase orders found.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

                <div class="mt-4">
                    {{ $purchaseOrders->links() }}
                </div>

            </div>

        </div>
    </div>
</x-app-layout>