<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Pengiriman
            </h2>
            <div class="flex items-center gap-3 no-print">
                <button onclick="window.print()"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    Print Surat Jalan
                </button>
                <a href="{{ route('pengiriman.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            {{-- Surat Jalan --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-8" id="surat-jalan">

                {{-- Header --}}
                <div class="flex items-start justify-between mb-6 pb-4 border-b-2 border-gray-800">
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">TOKO SENTOSA</h1>
                        <p class="text-xs text-gray-600">Jl. Bengkalis II No.91/93, Gang Buntu</p>
                        <p class="text-xs text-gray-600">Medan Timur, Medan 20231</p>
                        <p class="text-xs text-gray-600">082364740128</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-lg font-bold text-gray-900">SURAT JALAN</h2>
                        <p class="text-xs text-gray-600 mt-1">
                            No: SJ-{{ str_pad($pengiriman->delivery_id, 3, '0', STR_PAD_LEFT) }}
                        </p>
                        <p class="text-xs text-gray-600">
                            Tanggal: {{ \Carbon\Carbon::parse($pengiriman->delivery_date)->format('d F Y') }}
                        </p>
                        <p class="text-xs text-gray-600">
                            Ref: INV-{{ str_pad($pengiriman->sale_id, 3, '0', STR_PAD_LEFT) }}
                        </p>
                    </div>
                </div>

                {{-- Info Pengiriman --}}
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-400 mb-2">Kepada</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $pengiriman->recipient_name }}</p>
                        @if($pengiriman->recipient_phone)
                            <p class="text-xs text-gray-600">{{ $pengiriman->recipient_phone }}</p>
                        @endif
                        @if($pengiriman->address)
                            <p class="text-xs text-gray-600 mt-1">{{ $pengiriman->address }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase text-gray-400 mb-2">Info Pengiriman</p>
                        <p class="text-xs text-gray-700">
                            Tipe: <span class="font-semibold">{{ $pengiriman->type }}</span>
                        </p>
                        @if($pengiriman->expedition)
                            <p class="text-xs text-gray-700 mt-1">
                                Ekspedisi: <span class="font-semibold">{{ $pengiriman->expedition }}</span>
                            </p>
                        @endif
                        @if($pengiriman->shipping_cost > 0)
                            <p class="text-xs text-gray-700 mt-1">
                                Ongkir: <span class="font-semibold">Rp {{ number_format($pengiriman->shipping_cost, 0, ',', '.') }}</span>
                            </p>
                        @else
                            <p class="text-xs text-gray-700 mt-1">
                                Ongkir: <span class="font-semibold text-green-600">Gratis</span>
                            </p>
                        @endif
                        <p class="text-xs text-gray-700 mt-1 no-print">
                            Status:
                            @if($pengiriman->status === 'Dikirim')
                                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Dikirim</span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            @endif
                        </p>

                        {{-- tambah di bagian info pengiriman --}}
                        <p class="text-xs text-gray-700 mt-1">
                            Jumlah Colly: <span class="font-semibold">{{ $pengiriman->colly }} colly</span>
                        </p>
                    </div>

                    
                </div>

                {{-- Detail Barang --}}
                <table class="min-w-full mb-6">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="py-2 text-left text-xs font-semibold uppercase text-gray-600">No</th>
                            <th class="py-2 text-left text-xs font-semibold uppercase text-gray-600">Produk</th>
                            <th class="py-2 text-center text-xs font-semibold uppercase text-gray-600">Qty</th>
                            <th class="py-2 text-center text-xs font-semibold uppercase text-gray-600">Unit</th>
                            <th class="py-2 text-right text-xs font-semibold uppercase text-gray-600">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pengiriman->sale->details as $i => $detail)
                            <tr class="border-b border-gray-200">
                                <td class="py-2 text-xs text-gray-600">{{ $i + 1 }}</td>
                                <td class="py-2 text-xs text-gray-900 font-medium">
                                    {{ $detail->product->product_name }}
                                </td>
                                <td class="py-2 text-xs text-gray-900 text-center">
                                    {{ $detail->quantity }}
                                </td>
                                <td class="py-2 text-xs text-gray-900 text-center">
                                    {{ $detail->unit ?: $detail->product->sale_unit }}
                                </td>
                                <td class="py-2 text-xs text-gray-900 text-right">
                                    -
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Notes --}}
                @if($pengiriman->notes)
                    <div class="mb-6 p-3 bg-gray-50 rounded text-xs text-gray-600">
                        <span class="font-semibold">Catatan:</span> {{ $pengiriman->notes }}
                    </div>
                @endif

                {{-- TTD --}}
                <div class="mt-10 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-xs text-gray-500 mb-14">Pengirim</p>
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-xs text-gray-600">Toko Sentosa</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-14">Kurir</p>
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-xs text-gray-600">{{ $pengiriman->expedition ?? '-' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-14">Penerima</p>
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-xs text-gray-600">{{ $pengiriman->recipient_name }}</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center justify-end gap-4 mt-6 no-print">
                <a href="{{ route('pengiriman.edit', $pengiriman->delivery_id) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-400">
                    Edit
                </a>
                <form action="{{ route('pengiriman.destroy', $pengiriman->delivery_id) }}"
                      method="POST"
                      onsubmit="return confirm('Hapus data pengiriman ini?')">
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
            .no-print { display: none !important; }
            header { display: none !important; }
            nav { display: none !important; }
            aside { display: none !important; }
            body { background: white !important; }
            #surat-jalan {
                box-shadow: none !important;
                padding: 10px !important;
            }
        }
    </style>

</x-app-layout>