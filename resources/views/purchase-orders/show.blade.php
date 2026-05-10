<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Purchase Order Detail
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <div class="mb-6">
                    <p>
                        <strong>PO Number:</strong>
                        {{ 'PO-' . str_pad($purchaseOrder->po_id, 3, '0', STR_PAD_LEFT) }}
                    </p>

                    <p>
                        <strong>Supplier:</strong>
                        {{ $purchaseOrder->supplier->supplier_name }}
                    </p>

                    <p>
                        <strong>Date:</strong>
                        {{ $purchaseOrder->po_date }}
                    </p>

                    <p>
                        <strong>Status:</strong>
                        {{ $purchaseOrder->status }}
                    </p>

                    <p>
                        <strong>Notes:</strong>
                        {{ $purchaseOrder->notes }}
                    </p>
                </div>

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>

                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Product
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Qty
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Unit
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Price
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Discount
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

                </table>

            </div>

        </div>
    </div>
</x-app-layout>