<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Customer') }}</h2>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('customers.store') }}" method="POST">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <x-input-label for="customer_name" :value="__('Customer Name')" />
                            <x-text-input id="customer_name" name="customer_name" type="text" class="mt-1 block w-full" value="{{ old('customer_name') }}" required autofocus />
                            <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="phone" :value="__('Phone')" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone') }}" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="address" :value="__('Address')" />
                            <textarea id="address" name="address" class="mt-1 block w-full rounded-md border-gray-300" rows="3">{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('customers.index') }}" class="underline text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                            <x-primary-button>{{ __('Save Customer') }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
