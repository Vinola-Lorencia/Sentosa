<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Pengiriman
            </h2>
            <a href="{{ route('pengiriman.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                Tambah Pengiriman
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 bg-green-100 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penerima</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ekspedisi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($pengiriman as $item)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ str_pad($item->delivery_id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-indigo-600">
                                    <a href="{{ route('sales.show', $item->sale_id) }}">
                                        INV-{{ str_pad($item->sale_id, 3, '0', STR_PAD_LEFT) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $item->recipient_name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($item->delivery_date)->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($item->type === 'Dikirim')
                                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                            Dikirim
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                            Ambil Sendiri
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $item->expedition ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($item->status === 'Dikirim')
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            Dikirim
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('pengiriman.show', $item->delivery_id) }}"
                                           class="text-indigo-600 hover:text-indigo-800">Detail</a>

                                        <a href="{{ route('pengiriman.edit', $item->delivery_id) }}"
                                           class="text-yellow-600 hover:text-yellow-800">Edit</a>

                                        <form action="{{ route('pengiriman.destroy', $item->delivery_id) }}"
                                              method="POST"
                                              onsubmit="return confirm('Hapus data pengiriman ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Belum ada data pengiriman.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $pengiriman->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>