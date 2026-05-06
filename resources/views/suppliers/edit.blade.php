<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Supplier') }}</h2>
            <a href="{{ route('suppliers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('suppliers.update', $supplier) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        <div>
                            <x-input-label for="supplier_name" :value="__('Supplier Name')" />
                            <x-text-input id="supplier_name" name="supplier_name" type="text" class="mt-1 block w-full" value="{{ old('supplier_name', $supplier->supplier_name) }}" required autofocus />
                            <x-input-error :messages="$errors->get('supplier_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="phone" :value="__('Phone')" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone', $supplier->phone) }}" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $supplier->email) }}" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="address" :value="__('Address')" />
                            <textarea id="address" name="address" class="mt-1 block w-full rounded-md border-gray-300" rows="3">{{ old('address', $supplier->address) }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('suppliers.index') }}" class="underline text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                            <x-primary-button>{{ __('Update Supplier') }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
