<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Piutang
            </h2>
            <a href="{{ route('piutang.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Info Piutang --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Informasi Piutang</h3>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">No Invoice</p>
                        <a href="{{ route('sales.show', $piutang->sale_id) }}"
                           class="text-sm font-bold text-indigo-600 hover:underline mt-1 block">
                            INV-{{ str_pad($piutang->sale_id, 3, '0', STR_PAD_LEFT) }}
                        </a>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Customer</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            {{ $piutang->customer->customer_name ?? 'Walk-in' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Tanggal Penjualan</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            {{ \Carbon\Carbon::parse($piutang->sale->sale_date)->format('d F Y') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Jatuh Tempo</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">
                            {{ $piutang->due_date ? \Carbon\Carbon::parse($piutang->due_date)->format('d F Y') : '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-medium">Status</p>
                        <p class="mt-1">
                            @if($piutang->status === 'Paid')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Lunas</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Belum Lunas</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Progress Pembayaran --}}
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-3 gap-4 mb-3">
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-medium">Total Piutang</p>
                            <p class="text-base font-bold text-gray-900 mt-1">
                                Rp {{ number_format($piutang->amount, 0, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-medium">Sudah Dibayar</p>
                            <p class="text-base font-bold text-green-600 mt-1">
                                Rp {{ number_format($piutang->paid_amount, 0, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-medium">Sisa</p>
                            <p class="text-base font-bold text-red-600 mt-1">
                                Rp {{ number_format($piutang->remaining_amount, 0, ',', '.') }}
                            </p>
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    @php
                        $progress = $piutang->amount > 0
                            ? ($piutang->paid_amount / $piutang->amount) * 100
                            : 0;
                    @endphp
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-green-500 h-2.5 rounded-full"
                             style="width: {{ $progress }}%">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ number_format($progress, 0) }}% terbayar</p>
                </div>
            </div>

            {{-- Form Bayar --}}
            @if($piutang->status === 'Unpaid')
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-800 mb-4">Catat Pembayaran</h3>

                    <form action="{{ route('piutang.bayar', $piutang->receivable_id) }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="amount" :value="__('Jumlah Bayar')" />
                                <x-text-input id="amount" name="amount" type="number"
                                    step="0.01" min="1"
                                    max="{{ $piutang->remaining_amount }}"
                                    class="mt-1 block w-full"
                                    placeholder="Rp"
                                    required />
                                <p class="text-xs text-gray-500 mt-1">
                                    Maks: Rp {{ number_format($piutang->remaining_amount, 0, ',', '.') }}
                                </p>
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="payment_date" :value="__('Tanggal Bayar')" />
                                <x-text-input id="payment_date" name="payment_date" type="date"
                                    class="mt-1 block w-full"
                                    value="{{ date('Y-m-d') }}"
                                    required />
                                <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                            </div>
                            <div class="col-span-2">
                                <x-input-label for="notes" :value="__('Catatan')" />
                                <x-text-input id="notes" name="notes" type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Opsional" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <button type="submit"
                                class="inline-flex items-center px-6 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                                Simpan Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Histori Pembayaran --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Histori Pembayaran</h3>

                @if($piutang->payments->count() > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($piutang->payments as $i => $payment)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('d F Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-green-600 whitespace-nowrap">
                                        Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $payment->notes ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right text-sm font-bold text-gray-700">
                                    Total Dibayar
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-green-600 whitespace-nowrap">
                                    Rp {{ number_format($piutang->paid_amount, 0, ',', '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <p class="text-sm text-gray-400 italic">Belum ada pembayaran.</p>
                @endif
            </div>

            {{-- Detail Barang --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Detail Barang</h3>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Disc</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($piutang->sale->details as $detail)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $detail->product->product_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $detail->quantity }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $detail->unit ?: $detail->product->sale_unit }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">Rp {{ number_format($detail->price, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ $detail->discount }}%</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-right text-sm font-bold text-gray-700">Grand Total</td>
                                <td class="px-6 py-4 text-right text-sm font-bold text-indigo-600 whitespace-nowrap">
                                    Rp {{ number_format($piutang->sale->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>