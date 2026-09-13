<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-4 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 sm:space-y-6">

            @if(session('error'))
                <div class="px-4 py-3 bg-red-100 border border-red-300 text-red-800 rounded-md text-sm font-medium">
                    ⚠ {{ session('error') }}
                </div>
            @endif

            @if(session('success'))
                <div class="px-4 py-3 bg-green-100 border border-green-300 text-green-800 rounded-md text-sm font-medium">
                    ✓ {{ session('success') }}
                </div>
            @endif

            {{-- Notifikasi Pesanan Baru --}}
            @if($pesananBaru->count() > 0)
                <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-yellow-600 text-lg">🔔</span>
                            <h3 class="text-sm font-bold text-yellow-800">
                                {{ $pesananBaru->count() }} Pesanan Baru!
                            </h3>
                        </div>
                        <a href="{{ route('sales.index') }}"
                           class="text-xs text-yellow-700 hover:underline font-medium">
                            Lihat semua →
                        </a>
                    </div>
                    <div class="space-y-2">
                        @foreach($pesananBaru as $order)
                            <div class="bg-white rounded-md px-3 py-2 border border-yellow-200">
                                <div class="flex items-start justify-between mb-1">
                                    <p class="text-xs font-semibold text-gray-900">
                                        INV-{{ str_pad($order->sale_id, 3, '0', STR_PAD_LEFT) }}
                                        <span class="text-gray-500 font-normal">
                                            — {{ $order->customer->customer_name ?? 'Walk-in' }}
                                        </span>
                                    </p>
                                    <p class="text-xs font-bold text-gray-900 whitespace-nowrap ml-2">
                                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                    </p>
                                </div>
                                <p class="text-xs text-gray-500 mb-1">
                                    {{ \Carbon\Carbon::parse($order->sale_date)->format('d M Y') }} |
                                    {{ $order->payment_method }}
                                </p>
                                @if($order->notes)
                                    <p class="text-xs text-gray-400 mb-2">{{ $order->notes }}</p>
                                @endif
                                <div class="flex gap-2 mt-2">
                                    <a href="{{ route('sales.show', $order->sale_id) }}"
                                       class="flex-1 text-center text-xs px-2 py-1.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">
                                        Detail
                                    </a>
                                    <form action="{{ route('sales.tandai-diproses', $order->sale_id) }}"
                                          method="POST" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="w-full text-xs px-2 py-1.5 bg-green-600 text-white rounded-md hover:bg-green-500">
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
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-5 border-l-4 border-indigo-500">
                    <p class="text-xs font-semibold uppercase text-gray-400 leading-tight">Penjualan Bulan Ini</p>
                    <p class="text-sm sm:text-xl font-bold text-gray-900 mt-1 sm:mt-2 break-all">
                        Rp {{ number_format($totalPenjualanBulanIni, 0, ',', '.') }}
                    </p>
                    <a href="{{ route('sales.index') }}" class="text-xs text-indigo-600 hover:underline mt-1 block">
                        Lihat semua →
                    </a>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-5 border-l-4 border-green-500">
                    <p class="text-xs font-semibold uppercase text-gray-400 leading-tight">Pembelian Bulan Ini</p>
                    <p class="text-sm sm:text-xl font-bold text-gray-900 mt-1 sm:mt-2 break-all">
                        Rp {{ number_format($totalPembelianBulanIni, 0, ',', '.') }}
                    </p>
                    <a href="{{ route('purchases.index') }}" class="text-xs text-green-600 hover:underline mt-1 block">
                        Lihat semua →
                    </a>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-5 border-l-4 border-red-500">
                    <p class="text-xs font-semibold uppercase text-gray-400 leading-tight">Piutang Outstanding</p>
                    <p class="text-sm sm:text-xl font-bold text-gray-900 mt-1 sm:mt-2 break-all">
                        Rp {{ number_format($totalPiutang, 0, ',', '.') }}
                    </p>
                    <a href="{{ route('piutang.index') }}" class="text-xs text-red-600 hover:underline mt-1 block">
                        Lihat semua →
                    </a>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-5 border-l-4 border-yellow-500">
                    <p class="text-xs font-semibold uppercase text-gray-400 leading-tight">Stok Menipis</p>
                    <p class="text-sm sm:text-xl font-bold text-gray-900 mt-1 sm:mt-2">
                        {{ $stokMenipis }} produk
                    </p>
                    <a href="{{ route('products.index') }}" class="text-xs text-yellow-600 hover:underline mt-1 block">
                        Lihat semua →
                    </a>
                </div>
            </div>

            {{-- Grafik + Piutang Jatuh Tempo --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Grafik --}}
                <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Penjualan 6 Bulan Terakhir</h3>
                    @php $maxTotal = collect($grafikPenjualan)->max('total') ?: 1; @endphp
                    <div class="flex items-end gap-1 sm:gap-3" style="height: 160px;">
                        @foreach($grafikPenjualan as $data)
                            @php $heightPx = max(($data['total'] / $maxTotal) * 130, 2); @endphp
                            <div class="flex-1 flex flex-col items-center gap-1 justify-end h-full">
                                <p class="text-gray-500 text-center" style="font-size:9px;">
                                    {{ $data['total'] > 0 ? number_format($data['total'] / 1000, 0) . 'k' : '0' }}
                                </p>
                                <div class="w-full bg-indigo-500 rounded-t"
                                     style="height: {{ $heightPx }}px; min-height: 2px;">
                                </div>
                                <p class="text-gray-500 text-center" style="font-size:8px; line-height:1.2;">
                                    {{ $data['bulan'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Piutang Jatuh Tempo --}}
                <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Piutang Jatuh Tempo</h3>
                    @forelse($piutangJatuhTempo as $item)
                        @php $isOverdue = \Carbon\Carbon::parse($item->due_date)->isPast(); @endphp
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div class="flex-1 min-w-0 mr-2">
                                <p class="text-xs font-semibold text-gray-900 truncate">
                                    {{ $item->customer->customer_name ?? 'Walk-in' }}
                                </p>
                                <p class="text-xs {{ $isOverdue ? 'text-red-500 font-semibold' : 'text-gray-500' }}">
                                    {{ \Carbon\Carbon::parse($item->due_date)->format('d M Y') }}
                                    {{ $isOverdue ? '⚠' : '' }}
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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Transaksi Terakhir --}}
                <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Transaksi Penjualan Terakhir</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="pb-2 text-left text-xs font-medium text-gray-500">Invoice</th>
                                    <th class="pb-2 text-left text-xs font-medium text-gray-500">Customer</th>
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
                                            <p class="text-xs text-gray-400">
                                                {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M') }}
                                            </p>
                                        </td>
                                        <td class="py-2 text-xs text-gray-900">
                                            {{ $sale->customer->customer_name ?? 'Walk-in' }}
                                        </td>
                                        <td class="py-2 text-xs font-semibold text-gray-900 text-right whitespace-nowrap">
                                            Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-2 text-xs text-gray-400 italic">
                                            Belum ada transaksi.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('sales.index') }}"
                       class="block text-center text-xs text-indigo-600 hover:underline mt-4">
                        Lihat semua penjualan →
                    </a>
                </div>

                {{-- Stok Menipis --}}
                <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Produk Stok Menipis</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="pb-2 text-left text-xs font-medium text-gray-500">Produk</th>
                                    <th class="pb-2 text-right text-xs font-medium text-gray-500">Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($produkStokMenipis as $product)
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="py-2 text-xs text-gray-900">
                                            {{ $product->product_name }}
                                        </td>
                                        <td class="py-2 text-right text-xs font-semibold
                                            {{ $product->stock <= 0 ? 'text-red-600' : 'text-yellow-700' }}">
                                            {{ $product->stok_display }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-2 text-xs text-gray-400 italic">
                                            Semua stok aman.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('products.index') }}"
                       class="block text-center text-xs text-indigo-600 hover:underline mt-4">
                        Lihat semua produk →
                    </a>
                </div>

            </div>

            {{-- Produk Terbaru + Input Produk Karyawan --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Produk Terbaru --}}
                <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-sm font-semibold text-gray-800">Produk Terbaru</h3>
                        <div class="flex gap-1 text-xs">
                            @foreach(['all' => 'Semua', 'today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini'] as $key => $label)
                                <a href="{{ route('dashboard', ['produk_filter' => $key]) }}"
                                    class="px-2.5 py-1 rounded-full {{ $produkFilter === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="pb-2 text-left text-xs font-medium text-gray-500">Produk</th>
                                    <th class="pb-2 text-left text-xs font-medium text-gray-500">Ditambahkan Oleh</th>
                                    <th class="pb-2 text-right text-xs font-medium text-gray-500">Tanggal</th>
                                    <th class="pb-2 text-right text-xs font-medium text-gray-500">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($produkTerbaru as $product)
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="py-2 text-xs text-gray-900">
                                            {{ $product->product_name }}
                                        </td>
                                        <td class="py-2 text-xs text-gray-500">
                                            {{ $product->creator->name ?? '-' }}
                                        </td>
                                        <td class="py-2 text-right text-xs text-gray-500 whitespace-nowrap">
                                            {{ $product->created_at?->format('d M Y H:i') ?? '-' }}
                                        </td>
                                        <td class="py-2 text-right text-xs whitespace-nowrap">
                                            <a href="{{ route('products.edit', $product->id) }}"
                                                class="text-blue-600 hover:underline text-sm">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-2 text-xs text-gray-400 italic">
                                            Tidak ada produk pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Input Produk Karyawan --}}
                <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Input Produk Karyawan</h3>
                    <div class="space-y-1">
                        @forelse($inputProdukKaryawan as $item)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 cursor-pointer hover:bg-gray-50 rounded-md px-1 -mx-1"
                                data-user-id="{{ $item['user']->id }}"
                                data-user-name="{{ $item['user']->name }}"
                                onclick="openKaryawanProductsModal({{ $item['user']->id }}, @js($item['user']->name))">
                                <div class="flex-1 min-w-0 mr-2">
                                    <p class="text-xs font-semibold text-gray-900 truncate">
                                        {{ $item['user']->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 truncate">
                                        Terakhir: {{ $item['produk_terakhir']->product_name ?? '-' }}
                                    </p>
                                    @if($item['foto_bulan_ini'] > 0)
                                        <p class="text-xs text-gray-500 truncate">
                                            Foto terakhir: {{ $item['foto_terakhir']->product_name ?? '-' }}
                                        </p>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="block text-xs font-bold text-indigo-600 whitespace-nowrap">
                                        {{ $item['jumlah_bulan_ini'] }} produk
                                    </span>
                                    @if($item['foto_bulan_ini'] > 0)
                                        <span class="block text-[10px] font-semibold text-amber-600 whitespace-nowrap">
                                            📷 {{ $item['foto_bulan_ini'] }} foto
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Belum ada user dengan role Karyawan.</p>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>
    </div>

    {{-- Modal: Produk oleh Karyawan --}}
    <div id="karyawan-products-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-800" id="karyawan-products-modal-title">
                    Aktivitas Produk oleh
                </h3>
                <button type="button" onclick="closeKaryawanProductsModal()"
                    class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto">
                <div id="karyawan-products-modal-loading" class="text-xs text-gray-400 italic">
                    Memuat...
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full hidden" id="karyawan-products-modal-table">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Nama Produk</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Aktivitas</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Stok</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500">Tanggal</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="karyawan-products-modal-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end p-4 sm:p-6 border-t border-gray-200">
                <button type="button" onclick="closeKaryawanProductsModal()"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        function openKaryawanProductsModal(userId, userName) {
            const modal   = document.getElementById('karyawan-products-modal');
            const title   = document.getElementById('karyawan-products-modal-title');
            const loading = document.getElementById('karyawan-products-modal-loading');
            const table   = document.getElementById('karyawan-products-modal-table');
            const body    = document.getElementById('karyawan-products-modal-body');

            title.textContent = `Aktivitas Produk oleh ${userName}`;
            body.innerHTML = '';
            table.classList.add('hidden');
            loading.classList.remove('hidden');
            modal.classList.remove('hidden');

            fetch(`/api/karyawan-products/${userId}`, {
                headers: { 'Accept': 'application/json' },
            })
                .then((res) => res.json())
                .then((data) => {
                    loading.classList.add('hidden');

                    if (!data.products.length) {
                        loading.textContent = 'Belum ada aktivitas produk.';
                        loading.classList.remove('hidden');
                        return;
                    }

                    body.innerHTML = data.products.map((product) => `
                        <tr class="border-b border-gray-100 last:border-0">
                            <td class="py-2 text-xs text-gray-900">${escapeHtml(product.product_name)}</td>
                            <td class="py-2 text-xs whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold ${product.activity === 'Mengubah Foto' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'}">
                                    ${product.activity === 'Mengubah Foto' ? '📷 Mengubah Foto' : '➕ Menambah Produk'}
                                </span>
                            </td>
                            <td class="py-2 text-xs ${product.stock > 0 ? 'text-gray-700' : 'text-red-600'}">
                                ${product.stock > 0 ? escapeHtml(product.stok_display) : 'Habis'}
                            </td>
                            <td class="py-2 text-right text-xs text-gray-500 whitespace-nowrap">${escapeHtml(product.created_at)}</td>
                            <td class="py-2 text-right text-xs whitespace-nowrap">
                                <a href="${product.edit_url}" class="text-blue-600 hover:underline text-sm">Edit</a>
                            </td>
                        </tr>
                    `).join('');

                    table.classList.remove('hidden');
                })
                .catch(() => {
                    loading.textContent = 'Gagal memuat data produk.';
                    loading.classList.remove('hidden');
                });
        }

        function closeKaryawanProductsModal() {
            document.getElementById('karyawan-products-modal').classList.add('hidden');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }
    </script>
</x-app-layout>