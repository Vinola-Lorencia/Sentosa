<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ✅ Notifikasi Pesanan Baru --}}
            @if($pesananBaru->count() > 0)
                <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-yellow-600 text-lg">🔔</span>
                            <h3 class="text-sm font-bold text-yellow-800">
                                {{ $pesananBaru->count() }} Pesanan Baru Masuk!
                            </h3>
                        </div>
                        <a href="{{ route('sales.index') }}"
                           class="text-xs text-yellow-700 hover:underline font-medium">
                            Lihat semua →
                        </a>
                    </div>

                    <div class="space-y-2">
                        @foreach($pesananBaru as $order)
                            <div class="flex items-center justify-between bg-white rounded-md px-3 py-2 border border-yellow-200">
                                <div>
                                    <p class="text-xs font-semibold text-gray-900">
                                        INV-{{ str_pad($order->sale_id, 3, '0', STR_PAD_LEFT) }}
                                        <span class="text-gray-500 font-normal">—
                                            {{ $order->customer->customer_name ?? 'Walk-in' }}
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($order->sale_date)->format('d M Y') }} |
                                        {{ $order->payment_method }}
                                    </p>
                                    @if($order->notes)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $order->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <p class="text-xs font-bold text-gray-900 whitespace-nowrap">
                                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                    </p>
                                    <a href="{{ route('sales.show', $order->sale_id) }}"
                                       class="text-xs px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">
                                        Detail
                                    </a>
                                    <form action="{{ route('sales.tandai-diproses', $order->sale_id) }}"
                                          method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="text-xs px-3 py-1 bg-green-600 text-white rounded-md hover:bg-green-500">
                                            ✓ Proses
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-indigo-500">
                    <p class="text-xs font-semibold uppercase text-gray-400">Penjualan Bulan Ini</p>
                    <p class="text-xl font-bold text-gray-900 mt-2">
                        Rp {{ number_format($totalPenjualanBulanIni, 0, ',', '.') }}
                    </p>
                    <a href="{{ route('sales.index') }}" class="text-xs text-indigo-600 hover:underline mt-2 block">
                        Lihat semua →
                    </a>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-green-500">
                    <p class="text-xs font-semibold uppercase text-gray-400">Pembelian Bulan Ini</p>
                    <p class="text-xl font-bold text-gray-900 mt-2">
                        Rp {{ number_format($totalPembelianBulanIni, 0, ',', '.') }}
                    </p>
                    <a href="{{ route('purchases.index') }}" class="text-xs text-green-600 hover:underline mt-2 block">
                        Lihat semua →
                    </a>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-red-500">
                    <p class="text-xs font-semibold uppercase text-gray-400">Piutang Outstanding</p>
                    <p class="text-xl font-bold text-gray-900 mt-2">
                        Rp {{ number_format($totalPiutang, 0, ',', '.') }}
                    </p>
                    <a href="{{ route('piutang.index') }}" class="text-xs text-red-600 hover:underline mt-2 block">
                        Lihat semua →
                    </a>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-yellow-500">
                    <p class="text-xs font-semibold uppercase text-gray-400">Produk Stok Menipis</p>
                    <p class="text-xl font-bold text-gray-900 mt-2">
                        {{ $stokMenipis }} produk
                    </p>
                    <a href="{{ route('products.index') }}" class="text-xs text-yellow-600 hover:underline mt-2 block">
                        Lihat semua →
                    </a>
                </div>
            </div>

            {{-- Grafik + Piutang Jatuh Tempo --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Penjualan 6 Bulan Terakhir</h3>
                    <div class="flex items-end gap-2 h-40">
                        @php $maxTotal = collect($grafikPenjualan)->max('total') ?: 1; @endphp
                        @foreach($grafikPenjualan as $data)
                            @php $height = ($data['total'] / $maxTotal) * 100; @endphp
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <p class="text-xs text-gray-500 whitespace-nowrap">
                                    {{ number_format($data['total'] / 1000, 0) }}k
                                </p>
                                <div class="w-full bg-indigo-500 rounded-t"
                                     style="height: {{ max($height, 2) }}%"></div>
                                <p class="text-xs text-gray-500 whitespace-nowrap">
                                    {{ $data['bulan'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Piutang Jatuh Tempo</h3>
                    @forelse($piutangJatuhTempo as $item)
                        @php $isOverdue = \Carbon\Carbon::parse($item->due_date)->isPast(); @endphp
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <p class="text-xs font-semibold text-gray-900">
                                    {{ $item->customer->customer_name ?? 'Walk-in' }}
                                </p>
                                <p class="text-xs {{ $isOverdue ? 'text-red-500 font-semibold' : 'text-gray-500' }}">
                                    {{ \Carbon\Carbon::parse($item->due_date)->format('d M Y') }}
                                    {{ $isOverdue ? '⚠ Jatuh Tempo' : '' }}
                                </p>
                            </div>
                            <p class="text-xs font-bold text-red-600 whitespace-nowrap">
                                Rp {{ number_format($item->remaining_amount, 0, ',', '.') }}
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Tidak ada piutang jatuh tempo.</p>
                    @endforelse
                    <a href="{{ route('piutang.index') }}"
                       class="block text-center text-xs text-indigo-600 hover:underline mt-4">
                        Lihat semua piutang →
                    </a>
                </div>
            </div>

            {{-- Transaksi Terakhir + Stok Menipis --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Transaksi Penjualan Terakhir</h3>
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Invoice</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Customer</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Tanggal</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transaksiTerakhir as $sale)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-2">
                                        <a href="{{ route('sales.show', $sale->sale_id) }}"
                                           class="text-xs text-indigo-600 hover:underline">
                                            INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}
                                        </a>
                                    </td>
                                    <td class="py-2 text-xs text-gray-900">
                                        {{ $sale->customer->customer_name ?? 'Walk-in' }}
                                    </td>
                                    <td class="py-2 text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}
                                    </td>
                                    <td class="py-2 text-xs font-semibold text-gray-900 text-right whitespace-nowrap">
                                        Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-2 text-xs text-gray-400 italic">
                                        Belum ada transaksi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <a href="{{ route('sales.index') }}"
                       class="block text-center text-xs text-indigo-600 hover:underline mt-4">
                        Lihat semua penjualan →
                    </a>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Produk Stok Menipis</h3>
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Produk</th>
                                <th class="pb-2 text-center text-xs font-medium text-gray-500">Stok</th>
                                <th class="pb-2 text-center text-xs font-medium text-gray-500">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($produkStokMenipis as $product)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-2 text-xs text-gray-900">
                                        {{ $product->product_name }}
                                    </td>
                                    <td class="py-2 text-center">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                            {{ $product->stock <= 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ $product->stock }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-xs text-gray-500 text-center">
                                        {{ $product->sale_unit }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-2 text-xs text-gray-400 italic">
                                        Semua stok aman.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <a href="{{ route('products.index') }}"
                       class="block text-center text-xs text-indigo-600 hover:underline mt-4">
                        Lihat semua produk →
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>