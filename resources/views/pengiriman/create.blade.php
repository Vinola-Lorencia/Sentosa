<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tambah Pengiriman
            </h2>
            <a href="{{ route('pengiriman.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if($errors->any())
                    <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('pengiriman.store') }}" method="POST">
                    @csrf

                    <div class="space-y-6">

                        {{-- Pilih Sale --}}
                        <div>
                            <x-input-label for="sale_id" :value="__('Transaksi Penjualan')" />
                            <select name="sale_id" id="sale_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="">Pilih Transaksi</option>
                                @foreach($sales as $sale)
                                    <option value="{{ $sale->sale_id }}"
                                        data-customer="{{ $sale->customer->customer_name ?? 'Walk-in' }}"
                                        data-phone="{{ $sale->customer->phone ?? '' }}"
                                        data-address="{{ $sale->customer->address ?? '' }}">
                                        INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }} —
                                        {{ $sale->customer->customer_name ?? 'Walk-in' }} —
                                        {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sale_id')" class="mt-2" />
                        </div>

                        {{-- Tanggal Pengiriman --}}
                        <div>
                            <x-input-label for="delivery_date" :value="__('Tanggal Pengiriman')" />
                            <x-text-input id="delivery_date" name="delivery_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('delivery_date', date('Y-m-d')) }}"
                                required />
                            <x-input-error :messages="$errors->get('delivery_date')" class="mt-2" />
                        </div>

                        {{-- Tipe Pengiriman --}}
                        <div>
                            <x-input-label for="type" :value="__('Tipe Pengiriman')" />
                            <select name="type" id="type"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="Ambil Sendiri">Ambil Sendiri</option>
                                <option value="Dikirim">Dikirim</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        {{-- Status --}}
                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select name="status" id="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="Pending">Pending</option>
                                <option value="Dikirim">Dikirim</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        {{-- Nama Penerima --}}
                        <div>
                            <x-input-label for="recipient_name" :value="__('Nama Penerima')" />
                            <x-text-input id="recipient_name" name="recipient_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('recipient_name') }}"
                                required />
                            <x-input-error :messages="$errors->get('recipient_name')" class="mt-2" />
                        </div>

                        {{-- No HP --}}
                        <div>
                            <x-input-label for="recipient_phone" :value="__('No. HP Penerima')" />
                            <x-text-input id="recipient_phone" name="recipient_phone" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('recipient_phone') }}" />
                            <x-input-error :messages="$errors->get('recipient_phone')" class="mt-2" />
                        </div>

                        {{-- Alamat & Ekspedisi (muncul kalau Dikirim) --}}
                        <div id="kirim-section">
                            <div class="space-y-6">
                                <div>
                                    <x-input-label for="address" :value="__('Alamat Pengiriman')" />
                                    <textarea name="address" id="address" rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address') }}</textarea>
                                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="expedition" :value="__('Ekspedisi')" />
                                    <x-text-input id="expedition" name="expedition" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('expedition') }}"
                                        placeholder="JNE, J&T, SiCepat, dll" />
                                    <x-input-error :messages="$errors->get('expedition')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="shipping_cost" :value="__('Ongkos Kirim')" />
                                    <x-text-input id="shipping_cost" name="shipping_cost" type="number"
                                        step="0.01" min="0"
                                        class="mt-1 block w-full"
                                        value="{{ old('shipping_cost', 0) }}" />
                                    <x-input-error :messages="$errors->get('shipping_cost')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        {{-- Colly --}}
                        <div>
                            <x-input-label for="colly" :value="__('Jumlah Colly')" />
                            <x-text-input id="colly" name="colly" type="number"
                                min="1" step="1"
                                class="mt-1 block w-full"
                                value="{{ old('colly', 1) }}"
                                required />
                            <p class="text-xs text-gray-500 mt-1">Jumlah kardus/karung/koli yang dikirim</p>
                            <x-input-error :messages="$errors->get('colly')" class="mt-2" />
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="__('Catatan')" />
                            <textarea name="notes" id="notes" rows="2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('pengiriman.index') }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900">Batal</a>
                            <x-primary-button>Simpan Pengiriman</x-primary-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const saleSelect     = document.getElementById('sale_id');
            const typeSelect     = document.getElementById('type');
            const kirimSection   = document.getElementById('kirim-section');
            const recipientName  = document.getElementById('recipient_name');
            const recipientPhone = document.getElementById('recipient_phone');
            const address        = document.getElementById('address');

            saleSelect.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                recipientName.value  = opt.dataset.customer || '';
                recipientPhone.value = opt.dataset.phone    || '';
                address.value        = opt.dataset.address  || '';
            });

            function toggleKirimSection() {
                if (typeSelect.value === 'Ambil Sendiri') {
                    kirimSection.classList.add('hidden');
                } else {
                    kirimSection.classList.remove('hidden');
                }
            }

            typeSelect.addEventListener('change', toggleKirimSection);
            toggleKirimSection();
        });
    </script>

</x-app-layout>