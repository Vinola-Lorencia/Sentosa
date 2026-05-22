<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between no-print">

            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Penjualan
            </h2>

            <div class="flex items-center gap-3">

                <button onclick="printInvoice()"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">

                    Cetak Invoice

                </button>

                <a href="{{ route('sales.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">

                    Kembali

                </a>

            </div>

        </div>
    </x-slot>

    <div>

        <div>

            {{-- Invoice --}}
            <div id="invoice-content"
                 class="bg-white p-2 text-[11px] leading-tight">

                {{-- Header --}}
                <div class="flex justify-between items-start mb-2">

                    <div>

                        <h1 class="text-sm font-bold text-gray-900">
                            TOKO SENTOSA
                        </h1>

                        <p class="text-[10px] text-gray-600">
                            Jl. Bengkalis II No.91/93, Gang Buntu
                        </p>

                        <p class="text-[10px] text-gray-600">
                            Medan Timur, Medan 20231
                        </p>

                        <p class="text-[10px] text-gray-600">
                            082364740128
                        </p>

                    </div>

                    <div class="text-right">

                        <h2 class="text-sm font-bold text-indigo-600">
                            INVOICE
                        </h2>

                        <p class="text-[10px] font-semibold text-gray-900">
                            INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}
                        </p>

                        <p class="text-[10px] text-gray-600">
                            {{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') }}
                        </p>

                        <p class="text-[10px] text-gray-600">
                            {{ $sale->payment_method }}
                        </p>

                    </div>

                </div>

                <hr class="border-gray-300 mb-2">

                {{-- Customer --}}
                <div class="mb-2">

                    <p class="font-semibold text-gray-900 text-[10px]">
                        {{ $sale->customer->customer_name ?? 'Walk-in Customer' }}
                    </p>

                    @if($sale->customer?->address)
                        <p class="text-[10px] text-gray-600">
                            {{ $sale->customer->address }}
                        </p>
                    @endif

                    @if($sale->customer?->phone)
                        <p class="text-[10px] text-gray-600">
                            {{ $sale->customer->phone }}
                        </p>
                    @endif

                </div>

                {{-- Payment Info --}}
                <div class="no-print mb-2 p-2 bg-gray-50 rounded text-[10px]">

                    <span class="font-semibold">
                        Status:
                    </span>

                    @php
                        $statusClass = $sale->payment_status === 'Lunas'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800';
                    @endphp

                    <span class="px-2 py-0.5 rounded-full font-semibold {{ $statusClass }}">
                        {{ $sale->payment_status }}
                    </span>

                    @if($sale->payment_status === 'Belum Bayar' && $sale->receivable?->due_date)

                        | Jatuh Tempo:

                        <span class="font-semibold text-red-600">
                            {{ \Carbon\Carbon::parse($sale->receivable->due_date)->format('d/m/Y') }}
                        </span>

                    @endif

                </div>

                {{-- Table --}}
                <table class="w-full border-collapse mb-2">

                    <thead>

                        <tr class="border-b border-gray-400">

                            <th class="py-1 text-left text-[10px] font-bold text-gray-700">
                                Produk
                            </th>

                            <th class="py-1 text-center text-[10px] font-bold text-gray-700 w-10">
                                Qty
                            </th>

                            <th class="py-1 text-center text-[10px] font-bold text-gray-700 w-12">
                                Unit
                            </th>

                            <th class="py-1 text-right text-[10px] font-bold text-gray-700 w-20">
                                Harga
                            </th>

                            <th class="py-1 text-right text-[10px] font-bold text-gray-700 w-12">
                                Disc
                            </th>

                            <th class="py-1 text-right text-[10px] font-bold text-gray-700 w-24">
                                Subtotal
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($sale->details as $detail)

                            <tr class="border-b border-gray-100">

                                <td class="py-1 text-[10px] text-gray-900">
                                    {{ $detail->product->product_name }}
                                </td>

                                <td class="py-1 text-[10px] text-center">
                                    {{ $detail->quantity }}
                                </td>

                                <td class="py-1 text-[10px] text-center">
                                    {{ $detail->unit ?: $detail->product->sale_unit }}
                                </td>

                                <td class="py-1 text-[10px] text-right whitespace-nowrap">
                                    {{ number_format($detail->price, 0, ',', '.') }}
                                </td>

                                <td class="py-1 text-[10px] text-right">
                                    {{ $detail->discount }}%
                                </td>

                                <td class="py-1 text-[10px] font-semibold text-right whitespace-nowrap">
                                    {{ number_format($detail->subtotal, 0, ',', '.') }}
                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                    <tfoot>

                        <tr class="border-t border-gray-400">

                            <td colspan="5"
                                class="py-1 text-right text-[10px] font-bold text-gray-700">

                                Total Keseluruhan

                            </td>

                            <td class="py-1 text-right text-[11px] font-bold text-indigo-600 whitespace-nowrap">

                                Rp {{ number_format($sale->total_amount, 0, ',', '.') }}

                            </td>

                        </tr>

                    </tfoot>

                </table>

                {{-- Notes --}}
                @if($sale->notes)

                    <div class="mb-2 text-[10px] text-gray-600">

                        <span class="font-semibold">
                            Catatan:
                        </span>

                        {{ $sale->notes }}

                    </div>

                @endif

                {{-- Signature --}}
                <div class="grid grid-cols-2 gap-6 mt-3 text-center">

                    <div>

                        <p class="text-[10px] text-gray-500 mb-4">
                            Penerima
                        </p>

                        <div class="border-t border-gray-400 pt-1">

                            <p class="text-[10px] text-gray-700">
                                {{ $sale->customer->customer_name ?? 'Walk-in Customer' }}
                            </p>

                        </div>

                    </div>

                    <div>

                        <p class="text-[10px] text-gray-500 mb-4">
                            Hormat Kami
                        </p>

                        <div class="border-t border-gray-400 pt-1">

                            <p class="text-[10px] text-gray-700">
                                Toko Sentosa
                            </p>

                        </div>

                    </div>

                </div>

                {{-- Watermark --}}
                @if($sale->payment_status === 'Belum Bayar')

                    <div class="mt-2 text-center">

                        <span class="text-xl font-black text-red-200 opacity-40 tracking-widest inline-block"
                              style="transform: rotate(-10deg);">

                            BELUM LUNAS

                        </span>

                    </div>

                @endif

            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center justify-end gap-4 mt-6 no-print">

                <a href="{{ route('sales.edit', $sale->sale_id) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-400">

                    Ubah

                </a>

                <form action="{{ route('sales.destroy', $sale->sale_id) }}"
                      method="POST"
                      onsubmit="return confirm('Hapus transaksi ini? Stok akan dikembalikan.')">

                    @csrf
                    @method('DELETE')

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">

                        Hapus

                    </button>

                </form>

            </div>

        </div>

    </div>

    <style>

        @media print {

            @page {
                size: 9.5in 5.5in;
                margin: 0;
            }

            html,
            body {

                width: 9.5in;
                height: 5.5in;

                margin: 0 !important;
                padding: 0 !important;

                background: white !important;

                font-size: 9px !important;
                line-height: 1.2 !important;
            }

            header,
            nav,
            aside,
            .no-print {
                display: none !important;
            }

            #invoice-content {

                width: 9.5in !important;
                min-height: 5.5in !important;

                margin: 0 !important;

                padding:
                    0.15in
                    0.2in
                    !important;

                box-sizing: border-box !important;

                box-shadow: none !important;
                border-radius: 0 !important;

                position: absolute;
                top: 0;
                left: 0;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            td,
            th {

                padding-top: 2px !important;
                padding-bottom: 2px !important;

            }

        }

    </style>

    <script>

        function printInvoice() {
            window.print();
        }

    </script>

</x-app-layout>