<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout — Toko Sentosa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('toko.keranjang') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    ← Kembali ke Keranjang
                </a>
                <span class="font-bold text-gray-900">Checkout</span>
                <div class="flex items-center gap-4">
                    <a href="{{ route('toko.index', ['filter' => 'baru']) }}" class="relative text-sm font-medium text-gray-600 hover:text-indigo-600">
                        🆕 Produk Baru
                        @php $produkBaruCount = \App\Models\Product::available()->newProducts()->unseenBy(auth()->id())->count(); @endphp
                        @if($produkBaruCount > 0)
                            <span class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5">
                                {{ $produkBaruCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('toko.pesanan') }}" class="text-sm font-medium text-gray-600 hover:text-indigo-600">
                        Pesanan Saya
                    </a>
                    <a href="{{ route('toko.tagihan') }}" class="text-sm font-medium text-gray-600 hover:text-indigo-600">
                        Tagihan Saya
                    </a>
                    <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Form --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Info Pengiriman</h3>

                <form action="{{ route('toko.checkout.proses') }}" method="POST">
                    @csrf
                    <div class="space-y-4">

                        <div>
                            <x-input-label for="nama" :value="__('Nama Pemesan')" />
                            <x-text-input id="nama" name="nama" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('nama', Auth::user()->name) }}"
                                required />
                        </div>

                        <div>
                            <x-input-label for="alamat" :value="__('Alamat Pengiriman')" />
                            <textarea name="alamat" id="alamat" rows="3"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Isi jika ingin dikirim, kosongkan jika ambil sendiri">{{ old('alamat') }}</textarea>
                        </div>

                        <div>
                            <x-input-label for="payment_method" :value="__('Metode Pembayaran')" />
                            <select name="payment_method" id="payment_method"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="Cash">Cash</option>
                                <option value="Transfer">Transfer</option>
                                <option value="Utang">Utang</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label for="catatan" :value="__('Catatan')" />
                            <textarea name="catatan" id="catatan" rows="2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Catatan tambahan untuk pemilik toko">{{ old('catatan') }}</textarea>
                        </div>

                        <button type="submit"
                            class="w-full py-3 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-500">
                            ✓ Kirim Pesanan
                        </button>

                    </div>
                </form>
            </div>

            {{-- Ringkasan --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Ringkasan Pesanan</h3>

                <div class="space-y-3 mb-4">
                    @foreach($keranjang as $item)
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $item->product->product_name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $item->quantity }} {{ $item->unit }} ×
                                    Rp {{ number_format($item->price, 0, ',', '.') }}
                                </p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 whitespace-nowrap">
                                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <div class="flex items-center justify-between">
                        <p class="text-base font-bold text-gray-900">Total</p>
                        <p class="text-base font-bold text-indigo-600">
                            Rp {{ number_format($total, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>