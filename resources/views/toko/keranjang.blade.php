<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Keranjang — Toko Sentosa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

    {{-- Navbar --}}
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('toko.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        ← Lanjut Belanja
                    </a>
                </div>
                <span class="font-bold text-gray-900">Keranjang Belanja</span>
                <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-100 text-green-800 rounded-md text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(empty($keranjang))
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                <p class="text-4xl mb-4">🛒</p>
                <p class="text-gray-500 mb-4">Keranjang kamu kosong.</p>
                <a href="{{ route('toko.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                    Mulai Belanja
                </a>
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($keranjang as $key => $item)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ $item['product_name'] }}
                                    <p class="text-xs text-gray-500">{{ $item['unit'] }}</p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <form action="{{ route('toko.keranjang.update', $item['product_id']) }}"
                                          method="POST" class="inline-flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="number" name="quantity"
                                            value="{{ $item['quantity'] }}"
                                            min="0.25" step="0.25"
                                            class="w-20 text-sm border-gray-300 rounded-md shadow-sm text-center">
                                        <button type="submit"
                                            class="text-xs text-indigo-600 hover:text-indigo-800">
                                            Update
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($item['price'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                                    Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <form action="{{ route('toko.keranjang.hapus', $item['product_id']) }}"
                                          method="POST"
                                          onsubmit="return confirm('Hapus item ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right text-sm font-bold text-gray-700">
                                Grand Total
                            </td>
                            <td class="px-6 py-4 text-right text-base font-bold text-indigo-600 whitespace-nowrap">
                                Rp {{ number_format($total, 0, ',', '.') }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('toko.checkout') }}"
                   class="inline-flex items-center px-6 py-3 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-500">
                    Lanjut Checkout →
                </a>
            </div>
        @endif

    </div>

</body>
</html>