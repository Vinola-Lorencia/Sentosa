<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Produk yang Saya Tambahkan
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                {{-- Stats Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="bg-indigo-50 rounded-lg p-4">
                        <p class="text-xs font-medium text-indigo-600 uppercase tracking-wide">Total Bulan Ini</p>
                        <p class="text-2xl font-bold text-indigo-900 mt-1">{{ $totalBulanIni }} produk</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Keseluruhan</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalKeseluruhan }} produk</p>
                    </div>
                </div>

                {{-- Filter --}}
                <div class="flex gap-1 text-xs mb-4">
                    @foreach(['all' => 'Semua', 'today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini'] as $key => $label)
                        <a href="{{ route('products.my', ['filter' => $key]) }}"
                            class="px-2.5 py-1 rounded-full {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Nama Produk</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500">Stok</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500">Tanggal Ditambahkan</th>
                                <th class="pb-2 text-right text-xs font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-2 text-xs text-gray-900">
                                        {{ $product->product_name }}
                                    </td>
                                    <td class="py-2 text-xs {{ $product->stock > 0 ? 'text-gray-700' : 'text-red-600' }}">
                                        {{ $product->stock > 0 ? $product->stok_display : 'Habis' }}
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

                <div class="mt-4">
                    {{ $products->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
