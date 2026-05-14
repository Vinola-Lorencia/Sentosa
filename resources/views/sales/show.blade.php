<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Penjualan
            </h2>
            <div class="flex items-center gap-3 no-print">
                <button onclick="printInvoice()"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    Print Invoice
                </button>
                <a href="{{ route('sales.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-8" id="invoice-content">

                {{-- Header Invoice --}}
                <div class="flex items-start justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">TOKO SENTOSA</h1>
                        <p class="text-sm text-gray-600 mt-1">Jl. Bengkalis II No.91/93, Gang Buntu</p>
                        <p class="text-sm text-gray-600">Medan Timur, Medan, Sumatera Utara 20231</p>
                        <p class="text-sm text-gray-600">082364740128</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-xl font-bold text-indigo-600">INVOICE</h2>
                        <p class="text-sm font-semibold text-gray-900 mt-1">
                            INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ \Carbon\Carbon::parse($sale->sale_date)->format('d F Y') }}
                        </p>
                    </div>
                </div>

                <hr class="border-gray-300 mb-6">

                {{-- Customer Info --}}
                <div class="grid grid-cols-2 gap-6 mb-8">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-400 mb-2">Kepada</p>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $sale->customer->customer_name ?? 'Walk-in Customer' }}
                        </p>
                        @if($sale->customer?->address)
                            <p class="text-sm text-gray-600">{{ $sale->customer->address }}</p>
                        @endif
                        @if($sale->customer?->phone)
                            <p class="text-sm text-gray-600">{{ $sale->customer->phone }}</p>
                        @endif
                    </div>

                    {{-- Info Pembayaran — tidak ikut print --}}
                    <div class="text-right no-print">
                        <p class="text-xs font-semibold uppercase text-gray-400 mb-2">Info Pembayaran</p>
                        
                        <p class="text-sm text-gray-700 mt-1">
                            Status:
                            @php
                                $statusClass = $sale->payment_status === 'Lunas'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-red-100 text-red-800';
                            @endphp
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusClass }}">
                                {{ $sale->payment_status }}
                            </span>
                        </p>
                        @if($sale->payment_status === 'Belum Bayar' && $sale->receivable?->due_date)
                            <p class="text-sm text-gray-700 mt-1">
                                Jatuh Tempo:
                                <span class="font-semibold text-red-600">
                                    {{ \Carbon\Carbon::parse($sale->receivable->due_date)->format('d F Y') }}
                                </span>
                            </p>
                        @endif
                        @if($sale->payment_status === 'Lunas' && $sale->paid_date)
                            <p class="text-sm text-gray-700 mt-1">
                                Dibayar:
                                <span class="font-semibold text-green-600">
                                    {{ \Carbon\Carbon::parse($sale->paid_date)->format('d F Y') }}
                                </span>
                            </p>
                        @endif
                    </div>
                </div>
                {{-- ✅ Tutup grid di sini --}}

                {{-- Detail Barang --}}
                <table class="min-w-full mb-8">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="py-3 text-left text-xs font-semibold uppercase text-gray-600">No</th>
                            <th class="py-3 text-left text-xs font-semibold uppercase text-gray-600">Produk</th>
                            <th class="py-3 text-center text-xs font-semibold uppercase text-gray-600">Qty</th>
                            <th class="py-3 text-center text-xs font-semibold uppercase text-gray-600">Unit</th>
                            <th class="py-3 text-right text-xs font-semibold uppercase text-gray-600">Harga</th>
                            <th class="py-3 text-right text-xs font-semibold uppercase text-gray-600">Disc</th>
                            <th class="py-3 text-right text-xs font-semibold uppercase text-gray-600">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->details as $i => $detail)
                            <tr class="border-b border-gray-200">
                                <td class="py-3 text-sm text-gray-600">{{ $i + 1 }}</td>
                                <td class="py-3 text-sm text-gray-900 font-medium">
                                    {{ $detail->product->product_name }}
                                </td>
                                <td class="py-3 text-sm text-gray-900 text-center">
                                    {{ $detail->quantity }}
                                </td>
                                <td class="py-3 text-sm text-gray-900 text-center">
                                    {{ $detail->unit ?: $detail->product->sale_unit }}
                                </td>
                                <td class="py-3 text-sm text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($detail->price, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-sm text-gray-900 text-right">
                                    {{ $detail->discount }}%
                                </td>
                                <td class="py-3 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300">
                            <td colspan="6" class="py-4 text-right text-sm font-bold text-gray-700">
                                Grand Total
                            </td>
                            <td class="py-4 text-right text-base font-bold text-indigo-600 whitespace-nowrap">
                                Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Notes --}}
                @if($sale->notes)
                    <div class="mb-8 p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs font-semibold uppercase text-gray-400 mb-1">Catatan</p>
                        <p class="text-sm text-gray-700">{{ $sale->notes }}</p>
                    </div>
                @endif

                {{-- Footer --}}
                <div class="mt-12 grid grid-cols-2 gap-8 text-center">
                    <div>
                        <p class="text-xs text-gray-500 mb-16">Penerima</p>
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-xs text-gray-600">
                                {{ $sale->customer->customer_name ?? 'Walk-in Customer' }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-16">Hormat Kami</p>
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-xs text-gray-600">Toko Sentosa</p>
                        </div>
                    </div>
                </div>

                {{-- Watermark Belum Lunas --}}
                @if($sale->payment_status === 'Belum Bayar')
                    <div class="mt-6 text-center">
                        <span class="text-4xl font-black text-red-200 tracking-widest opacity-50 inline-block"
                              style="transform: rotate(-15deg);">
                            BELUM LUNAS
                        </span>
                    </div>
                @endif

            </div>
            {{-- end invoice-content --}}

            {{-- Action Buttons --}}
            <div class="flex items-center justify-end gap-4 mt-6 no-print">
                <a href="{{ route('sales.edit', $sale->sale_id) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-400">
                    Edit
                </a>
                <form action="{{ route('sales.destroy', $sale->sale_id) }}"
                      method="POST"
                      onsubmit="return confirm('Hapus transaksi ini? Stok akan dikembalikan.')">
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

    <style>
        @media print {
            .no-print { display: none !important; }
            header { display: none !important; }
            nav { display: none !important; }
            aside { display: none !important; }
            body { background: white !important; }
            #invoice-content {
                box-shadow: none !important;
                padding: 20px !important;
            }
        }
    </style>

    <script>
        function printInvoice() {
            window.print();
        }
    </script>

</x-app-layout>