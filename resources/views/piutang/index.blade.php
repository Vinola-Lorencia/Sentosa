<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Daftar Piutang
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Summary Cards --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-red-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Piutang</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        Rp {{ number_format($piutang->where('status', 'Unpaid')->sum('amount'), 0, ',', '.') }}
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-yellow-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Belum Lunas</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        {{ $piutang->where('status', 'Unpaid')->count() }} transaksi
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500 uppercase font-medium">Sudah Lunas</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        {{ $piutang->where('status', 'Paid')->count() }} transaksi
                    </p>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($piutang as $item)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-indigo-600">
                                    <a href="{{ route('sales.show', $item->sale_id) }}">
                                        INV-{{ str_pad($item->sale_id, 3, '0', STR_PAD_LEFT) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $item->customer->customer_name ?? 'Walk-in' }}
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    Rp {{ number_format($item->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($item->due_date)
                                        @php
                                            $isOverdue = $item->status === 'Unpaid' &&
                                                \Carbon\Carbon::parse($item->due_date)->isPast();
                                        @endphp
                                        <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                            {{ \Carbon\Carbon::parse($item->due_date)->format('d M Y') }}
                                            @if($isOverdue)
                                                <span class="text-xs">(Jatuh Tempo)</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($item->status === 'Paid')
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            Lunas
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                            Belum Lunas
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('piutang.show', $item->receivable_id) }}"
                                        class="text-indigo-600 hover:text-indigo-800">Detail</a>

                                        @if($item->status === 'Unpaid')
                                            <button
                                                onclick="openBayarModal({{ $item->receivable_id }}, '{{ $item->customer->customer_name ?? 'Walk-in' }}', '{{ number_format($item->amount, 0, ',', '.') }}')"
                                                class="text-green-600 hover:text-green-800 font-medium">
                                                Tandai Lunas
                                            </button>
                                        @endif

                                        <form action="{{ route('piutang.destroy', $item->receivable_id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Hapus data piutang ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data piutang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $piutang->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Bayar --}}
    <div id="bayar-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Tandai Lunas</h3>
            <p class="text-sm text-gray-600 mb-1">Customer: <span id="modal-customer" class="font-semibold"></span></p>
            <p class="text-sm text-gray-600 mb-4">Jumlah: <span id="modal-amount" class="font-semibold text-indigo-600"></span></p>

            <form id="bayar-form" method="POST">
                @csrf
                <div class="mb-4">
                    <x-input-label for="paid_date" :value="__('Tanggal Pembayaran')" />
                    <x-text-input id="paid_date" name="paid_date" type="date"
                        class="mt-1 block w-full"
                        value="{{ date('Y-m-d') }}" required />
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" onclick="closeBayarModal()"
                        class="text-sm text-gray-600 hover:text-gray-900 underline">
                        Batal
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                        Konfirmasi Lunas
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>
        function openBayarModal(id, customer, amount) {
            document.getElementById('modal-customer').textContent = customer;
            document.getElementById('modal-amount').textContent   = 'Rp ' + amount;
            document.getElementById('bayar-form').action          = `/piutang/${id}/bayar`;
            document.getElementById('bayar-modal').classList.remove('hidden');
        }

        function closeBayarModal() {
            document.getElementById('bayar-modal').classList.add('hidden');
        }
    </script>

</x-app-layout>