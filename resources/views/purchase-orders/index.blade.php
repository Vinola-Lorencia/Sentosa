```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Purchase Orders
            </h2>

            <a href="{{ route('purchase-orders.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                Create PO
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- SUCCESS MESSAGE --}}
            @if(session('success'))
                <div class="mb-4 p-4 rounded-lg bg-green-100 border border-green-300 text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <div class="overflow-x-auto rounded-lg border border-gray-200">

                    <table class="min-w-full divide-y divide-gray-200">

                        {{-- TABLE HEADER --}}
                        <thead class="bg-gray-50">
                            <tr>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    PO Number
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Supplier
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>

                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>

                            </tr>
                        </thead>

                        {{-- TABLE BODY --}}
                        <tbody class="bg-white divide-y divide-gray-200">

                            @forelse($purchaseOrders as $po)

                                <tr class="hover:bg-gray-50 transition">

                                    {{-- PO NUMBER --}}
                                    <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-900">
                                        {{ 'PO-' . str_pad($po->po_id, 3, '0', STR_PAD_LEFT) }}
                                    </td>

                                    {{-- SUPPLIER --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        {{ $po->supplier->supplier_name ?? '-' }}
                                    </td>

                                    {{-- DATE --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        {{ $po->po_date }}
                                    </td>

                                    {{-- STATUS --}}
                                    <td class="px-6 py-4 whitespace-nowrap">

                                        @if($po->status == 'Pending')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                                Pending
                                            </span>

                                        @elseif($po->status == 'Ordered')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                                                Ordered
                                            </span>

                                        @elseif($po->status == 'Completed')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                                Completed
                                            </span>

                                        @else
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                {{ $po->status }}
                                            </span>
                                        @endif

                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-4 whitespace-nowrap">

                                        <div class="flex items-center justify-center gap-2">

                                            {{-- DETAIL --}}
                                            <a href="{{ route('purchase-orders.show', $po->po_id) }}"
                                               class="inline-flex items-center px-3 py-1 bg-sky-500 text-white text-xs font-semibold rounded hover:bg-sky-400">
                                                Detail
                                            </a>

                                            {{-- EDIT --}}
                                            <a href="{{ route('purchase-orders.edit', $po->po_id) }}"
                                               class="inline-flex items-center px-3 py-1 bg-amber-500 text-white text-xs font-semibold rounded hover:bg-amber-400">
                                                Edit
                                            </a>

                                            {{-- DELETE --}}
                                            <form action="{{ route('purchase-orders.destroy', $po->po_id) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Delete this purchase order?')">

                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-500">
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-6 text-center text-gray-500">
                                        No purchase orders found.
                                    </td>
                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>

                {{-- PAGINATION --}}
                <div class="mt-6">
                    {{ $purchaseOrders->links() }}
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
```
