<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Laporan Pembelian
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filter --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('laporan.pembelian') }}"
                      class="flex items-end gap-4 flex-wrap">

                    <div>
                        <x-input-label for="start_date" :value="__('Dari Tanggal')" />
                        <x-text-input id="start_date" name="start_date" type="date"
                            class="mt-1 block"
                            value="{{ $startDate }}" />
                    </div>

                    <div>
                        <x-input-label for="end_date" :value="__('Sampai Tanggal')" />
                        <x-text-input id="end_date" name="end_date" type="date"
                            class="mt-1 block"
                            value="{{ $endDate }}" />
                    </div>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                        Filter
                    </button>

                    <a href="{{ route('laporan.pembelian.excel', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                       class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                        Export Excel
                    </a>

                    <a href="{{ route('laporan.pembelian.pdf', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                       class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        Export PDF
                    </a>

                </form>
            </div>

            {{-- Summary --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-indigo-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Transaksi</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ $purchases->count() }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Pembelian</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        Rp {{ number_format($total, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            {{-- Tabel --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No PO</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($purchases as $purchase)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ str_pad($purchase->purchase_id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $purchase->supplier->supplier_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $purchase->po_id ? 'PO-' . str_pad($purchase->po_id, 3, '0', STR_PAD_LEFT) : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data pembelian pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-right text-sm font-bold text-gray-700">
                                Grand Total
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-indigo-600 whitespace-nowrap">
                                Rp {{ number_format($total, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>