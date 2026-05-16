<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Laporan Piutang
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filter --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('laporan.piutang') }}"
                      class="flex items-end gap-4 flex-wrap">

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select name="status" id="status"
                            class="mt-1 block border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Semua</option>
                            <option value="Unpaid" {{ $status === 'Unpaid' ? 'selected' : '' }}>Belum Lunas</option>
                            <option value="Paid" {{ $status === 'Paid' ? 'selected' : '' }}>Lunas</option>
                        </select>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                        Filter
                    </button>

                    <a href="{{ route('laporan.piutang.excel', ['status' => $status]) }}"
                       class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                        Export Excel
                    </a>

                    <a href="{{ route('laporan.piutang.pdf', ['status' => $status]) }}"
                       class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        Export PDF
                    </a>

                </form>
            </div>

            {{-- Summary --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-gray-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Piutang</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        Rp {{ number_format($totalPiutang, 0, ',', '.') }}
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Sudah Dibayar</p>
                    <p class="text-xl font-bold text-green-600 mt-1">
                        Rp {{ number_format($totalDibayar, 0, ',', '.') }}
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-red-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Sisa Piutang</p>
                    <p class="text-xl font-bold text-red-600 mt-1">
                        Rp {{ number_format($totalSisa, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            {{-- Tabel --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dibayar</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($piutang as $item)
                            <tr>
                                <td class="px-6 py-4 text-sm text-indigo-600">
                                    <a href="{{ route('piutang.show', $item->receivable_id) }}">
                                        INV-{{ str_pad($item->sale_id, 3, '0', STR_PAD_LEFT) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $item->customer->customer_name ?? 'Walk-in' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($item->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-green-600 text-right whitespace-nowrap">
                                    Rp {{ number_format($item->paid_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-red-600 font-semibold text-right whitespace-nowrap">
                                    Rp {{ number_format($item->remaining_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    @if($item->due_date)
                                        @php $isOverdue = $item->status === 'Unpaid' && \Carbon\Carbon::parse($item->due_date)->isPast(); @endphp
                                        <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : '' }}">
                                            {{ \Carbon\Carbon::parse($item->due_date)->format('d M Y') }}
                                            {{ $isOverdue ? '⚠' : '' }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($item->status === 'Paid')
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Lunas</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Belum Lunas</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data piutang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="2" class="px-6 py-4 text-right text-sm font-bold text-gray-700">Grand Total</td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-gray-900 whitespace-nowrap">Rp {{ number_format($totalPiutang, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-green-600 whitespace-nowrap">Rp {{ number_format($totalDibayar, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-red-600 whitespace-nowrap">Rp {{ number_format($totalSisa, 0, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>