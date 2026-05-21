<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Laporan Penjualan
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filter --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('laporan.penjualan') }}"
                      class="flex items-end gap-4 flex-wrap">
                    <div>
                        <x-input-label for="start_date" :value="__('Dari Tanggal')" />
                        <x-text-input id="start_date" name="start_date" type="date"
                            class="mt-1 block" value="{{ $startDate }}" />
                    </div>
                    <div>
                        <x-input-label for="end_date" :value="__('Sampai Tanggal')" />
                        <x-text-input id="end_date" name="end_date" type="date"
                            class="mt-1 block" value="{{ $endDate }}" />
                    </div>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                        Filter
                    </button>
                    <a href="{{ route('laporan.penjualan.excel', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                       class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                        Export Excel
                    </a>
                    <a href="{{ route('laporan.penjualan.pdf', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                       class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        Export PDF
                    </a>
                </form>
            </div>

            {{-- Summary --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-indigo-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Transaksi</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ $sales->count() }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Penjualan</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        Rp {{ number_format($total, 0, ',', '.') }}
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-yellow-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Rata-rata per Transaksi</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        Rp {{ $sales->count() > 0 ? number_format($total / $sales->count(), 0, ',', '.') : 0 }}
                    </p>
                </div>
            </div>

            {{-- Omset Per Customer --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-800">Penjualan per Customer</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d
                        {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                    </p>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Jumlah Transaksi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Penjualan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">

                        {{-- Customer terdaftar --}}
                        @foreach($omsetPerCustomer as $customer)
                            @if($customer->total_penjualan > 0)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $customer->customer_name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-center">
                                        {{ $customer->jumlah_transaksi }} transaksi
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                                        Rp {{ number_format($customer->total_penjualan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 text-right" style="min-width: 120px;">
                                        @if($total > 0)
                                            {{ number_format(($customer->total_penjualan / $total) * 100, 1) }}%
                                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                                <div class="bg-indigo-500 h-1.5 rounded-full"
                                                     style="width: {{ ($customer->total_penjualan / $total) * 100 }}%">
                                                </div>
                                            </div>
                                        @else
                                            0%
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach

                        {{-- Walk-in --}}
                        @if($walkInTotal > 0)
                            <tr class="bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-500 italic">
                                    Walk-in Customer
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 text-center">
                                    {{ $walkInCount }} transaksi
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-500 text-right whitespace-nowrap">
                                    Rp {{ number_format($walkInTotal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 text-right" style="min-width: 120px;">
                                    @if($total > 0)
                                        {{ number_format(($walkInTotal / $total) * 100, 1) }}%
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                            <div class="bg-gray-400 h-1.5 rounded-full"
                                                 style="width: {{ ($walkInTotal / $total) * 100 }}%">
                                            </div>
                                        </div>
                                    @else
                                        0%
                                    @endif
                                </td>
                            </tr>
                        @endif

                        @if($omsetPerCustomer->where('total_penjualan', '>', 0)->isEmpty() && $walkInTotal == 0)
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data penjualan pada periode ini.
                                </td>
                            </tr>
                        @endif

                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="2" class="px-6 py-4 text-right text-sm font-bold text-gray-700">
                                Grand Total
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-indigo-600 whitespace-nowrap">
                                Rp {{ number_format($total, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-gray-700">
                                100%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Detail Transaksi Penjualan --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-800">Detail Transaksi Penjualan</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($sales as $sale)
                            <tr>
                                <td class="px-6 py-4 text-sm text-indigo-600">
                                    <a href="{{ route('sales.show', $sale->sale_id) }}">
                                        INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $sale->customer->customer_name ?? 'Walk-in' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $sale->payment_method }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @php
                                        $class = $sale->payment_status === 'Lunas'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800';
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $class }}">
                                        {{ $sale->payment_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data penjualan pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-right text-sm font-bold text-gray-700">
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