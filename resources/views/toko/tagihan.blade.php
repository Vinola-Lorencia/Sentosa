<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tagihan Saya — Toko Sentosa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

    @include('toko.partials.navbar')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <h1 class="text-xl font-bold text-gray-900 mb-6">Tagihan Saya</h1>

        @if(session('success'))
            <div class="mb-6 px-4 py-4 bg-green-100 text-green-800 rounded-lg text-sm font-medium">
                ✓ {{ session('success') }}
            </div>
        @endif

        @if(!$customer)
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                <p class="text-4xl mb-4">🔗</p>
                <p class="text-gray-500 mb-4">
                    Akun Anda belum terhubung dengan data pelanggan, sehingga riwayat tagihan
                    belum bisa ditampilkan. Hubungi pemilik toko untuk menghubungkan akun Anda.
                </p>
                <a href="{{ route('toko.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                    Kembali ke Katalog
                </a>
            </div>
        @else
            <div class="space-y-4">
                @forelse($sales as $sale)
                    @php
                        $isLunas = $sale->payment_status === 'Lunas';
                        $receivable = $sale->receivable;
                    @endphp
                    <div class="bg-white rounded-lg shadow-sm p-6">

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
                                <p class="text-base font-bold text-gray-900">
                                    Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                </p>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full
                                    {{ $isLunas ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $isLunas ? 'Lunas' : 'Belum Lunas' }}
                                </span>
                            </div>
                        </div>

                        @if($receivable)
                            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-end">
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Sisa Tagihan</p>
                                    <p class="text-sm font-bold
                                        {{ $receivable->status === 'Paid' ? 'text-green-600' : 'text-orange-600' }}">
                                        Rp {{ number_format($receivable->remaining_amount, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>

                            @if($receivable->payments->isNotEmpty())
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs font-semibold text-gray-700 mb-2">Riwayat Pembayaran</p>
                                    <div class="space-y-1">
                                        @foreach($receivable->payments as $payment)
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500">
                                                    {{ \Carbon\Carbon::parse($payment->payment_date)->format('d F Y') }}
                                                </span>
                                                <span class="font-semibold text-green-600">
                                                    Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif

                    </div>
                @empty
                    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                        <p class="text-4xl mb-4">🧾</p>
                        <p class="text-gray-500 mb-4">Belum ada tagihan.</p>
                        <a href="{{ route('toko.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                            Mulai Belanja
                        </a>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $sales->links() }}
            </div>
        @endif

    </div>

</body>
</html>
