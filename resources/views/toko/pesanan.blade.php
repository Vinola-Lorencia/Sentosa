<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesanan Saya — Toko Sentosa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 font-sans">

    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('toko.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    ← Lanjut Belanja
                </a>
                <span class="font-bold text-gray-900">Pesanan Saya</span>
                <div class="flex items-center gap-4">
                    <a href="{{ route('toko.index', ['filter' => 'baru']) }}" class="relative text-sm font-medium text-gray-600 hover:text-indigo-600">
                        🆕 Produk Baru
                        @php $produkBaruCount = \App\Models\Product::newProducts()->unseenBy(auth()->id())->count(); @endphp
                        @if($produkBaruCount > 0)
                            <span class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5">
                                {{ $produkBaruCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('toko.tagihan') }}" class="text-sm font-medium text-gray-600 hover:text-indigo-600">
                        Tagihan Saya
                    </a>
                    <a href="{{ route('toko.keranjang') }}" class="text-sm font-medium text-gray-600 hover:text-indigo-600">
                        Keranjang
                    </a>
                    <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <h1 class="text-xl font-bold text-gray-900 mb-6">Riwayat Pesanan Saya</h1>

        @if(session('success'))
            <div class="mb-6 px-4 py-4 bg-green-100 text-green-800 rounded-lg text-sm font-medium">
                ✓ {{ session('success') }}
            </div>
        @endif

        <div class="space-y-4">
            @forelse($pesanan as $sale)
                <div x-data="{ open: {{ $lastOrderId == $sale->sale_id ? 'true' : 'false' }} }"
                    class="bg-white rounded-lg shadow-sm p-6
                    {{ $lastOrderId == $sale->sale_id ? 'border-2 border-green-400' : '' }}">

                    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
                        <div>
                            <p class="text-sm font-bold text-gray-900">
                                INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ \Carbon\Carbon::parse($sale->sale_date)->format('d F Y') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-base font-bold text-indigo-600">
                                Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                            </p>
                            @php
                                $class = $sale->payment_status === 'Lunas'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-yellow-100 text-yellow-800';
                            @endphp
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $class }}">
                                {{ $sale->payment_status }}
                            </span>
                        </div>
                    </div>

                    <button type="button" @click="open = !open"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                        <span x-show="!open">Lihat Detail</span>
                        <span x-show="open" x-cloak>Sembunyikan Detail</span>
                    </button>

                    <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-400">
                                    <th class="pb-1 font-medium">Produk</th>
                                    <th class="pb-1 font-medium text-right">Qty</th>
                                    <th class="pb-1 font-medium text-right">Harga Satuan</th>
                                    <th class="pb-1 font-medium text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sale->details as $detail)
                                    <tr>
                                        <td class="py-1 text-gray-700">{{ $detail->product->product_name }}</td>
                                        <td class="py-1 text-right text-gray-500 whitespace-nowrap">
                                            {{ $detail->quantity }} {{ $detail->unit }}
                                        </td>
                                        <td class="py-1 text-right text-gray-500 whitespace-nowrap">
                                            Rp {{ number_format($detail->price, 0, ',', '.') }}
                                        </td>
                                        <td class="py-1 text-right text-gray-900 whitespace-nowrap">
                                            Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200">
                                    <td colspan="3" class="pt-2 text-right text-xs font-bold text-gray-700">
                                        Total Keseluruhan
                                    </td>
                                    <td class="pt-2 text-right text-sm font-bold text-indigo-600 whitespace-nowrap">
                                        Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        @if($sale->notes)
                            <p class="text-xs text-gray-500 pt-2">{{ $sale->notes }}</p>
                        @endif
                    </div>

                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <p class="text-4xl mb-4">📋</p>
                    <p class="text-gray-500 mb-4">Belum ada pesanan.</p>
                    <a href="{{ route('toko.index') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                        Mulai Belanja
                    </a>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $pesanan->links() }}
        </div>

    </div>

</body>
</html>