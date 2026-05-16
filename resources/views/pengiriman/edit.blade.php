<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Pengiriman
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

                <form action="{{ route('pengiriman.update', $pengiriman->delivery_id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">

                        {{-- Info Sale (readonly) --}}
                        <div class="p-4 bg-indigo-50 rounded-lg">
                            <p class="text-xs text-indigo-500 font-medium">Transaksi Penjualan</p>
                            <p class="text-sm font-bold text-indigo-800 mt-1">
                                INV-{{ str_pad($pengiriman->sale_id, 3, '0', STR_PAD_LEFT) }} —
                                {{ $pengiriman->sale->customer->customer_name ?? 'Walk-in' }} —
                                {{ \Carbon\Carbon::parse($pengiriman->sale->sale_date)->format('d M Y') }}
                            </p>
                        </div>

                        {{-- Tanggal Pengiriman --}}
                        <div>
                            <x-input-label for="delivery_date" :value="__('Tanggal Pengiriman')" />
                            <x-text-input id="delivery_date" name="delivery_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('delivery_date', $pengiriman->delivery_date) }}"
                                required />
                            <x-input-error :messages="$errors->get('delivery_date')" class="mt-2" />
                        </div>

                        {{-- Tipe --}}
                        <div>
                            <x-input-label for="type" :value="__('Tipe Pengiriman')" />
                            <select name="type" id="type"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                @foreach(['Ambil Sendiri', 'Dikirim'] as $t)
                                    <option value="{{ $t }}" {{ $pengiriman->type == $t ? 'selected' : '' }}>
                                        {{ $t }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        {{-- Status --}}
                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select name="status" id="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                @foreach(['Pending', 'Dikirim'] as $s)
                                    <option value="{{ $s }}" {{ $pengiriman->status == $s ? 'selected' : '' }}>
                                        {{ $s }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        {{-- Nama Penerima --}}
                        <div>
                            <x-input-label for="recipient_name" :value="__('Nama Penerima')" />
                            <x-text-input id="recipient_name" name="recipient_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('recipient_name', $pengiriman->recipient_name) }}"
                                required />
                            <x-input-error :messages="$errors->get('recipient_name')" class="mt-2" />
                        </div>

                        {{-- No HP --}}
                        <div>
                            <x-input-label for="recipient_phone" :value="__('No. HP Penerima')" />
                            <x-text-input id="recipient_phone" name="recipient_phone" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('recipient_phone', $pengiriman->recipient_phone) }}" />
                            <x-input-error :messages="$errors->get('recipient_phone')" class="mt-2" />
                        </div>

                        {{-- Alamat & Ekspedisi --}}
                        <div id="kirim-section" class="{{ $pengiriman->type === 'Ambil Sendiri' ? 'hidden' : '' }}">
                            <div class="space-y-6">
                                <div>
                                    <x-input-label for="address" :value="__('Alamat Pengiriman')" />
                                    <textarea name="address" id="address" rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address', $pengiriman->address) }}</textarea>
                                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="expedition" :value="__('Ekspedisi')" />
                                    <x-text-input id="expedition" name="expedition" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('expedition', $pengiriman->expedition) }}"
                                        placeholder="JNE, J&T, SiCepat, dll" />
                                    <x-input-error :messages="$errors->get('expedition')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="shipping_cost" :value="__('Ongkos Kirim')" />
                                    <x-text-input id="shipping_cost" name="shipping_cost" type="number"
                                        step="0.01" min="0"
                                        class="mt-1 block w-full"
                                        value="{{ old('shipping_cost', $pengiriman->shipping_cost) }}" />
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
                                value="{{ old('colly', $pengiriman->colly) }}"
                                required />
                            <p class="text-xs text-gray-500 mt-1">Jumlah kardus/karung/koli yang dikirim</p>
                            <x-input-error :messages="$errors->get('colly')" class="mt-2" />
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="__('Catatan')" />
                            <textarea name="notes" id="notes" rows="2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $pengiriman->notes) }}</textarea>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('pengiriman.show', $pengiriman->delivery_id) }}"
                               class="underline text-sm text-gray-600 hover:text-gray-900">Batal</a>
                            <x-primary-button>Update Pengiriman</x-primary-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect   = document.getElementById('type');
            const kirimSection = document.getElementById('kirim-section');

            typeSelect.addEventListener('change', function () {
                if (this.value === 'Ambil Sendiri') {
                    kirimSection.classList.add('hidden');
                } else {
                    kirimSection.classList.remove('hidden');
                }
            });
        });
    </script>

</x-app-layout>