<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Products') }}
            </h2>

            <a href="{{ route('products.create') }}"
               style="background-color:#111;color:#fff;"
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 shadow-sm">
                {{ __('New Product') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                {{-- Success Message --}}
                @if(session('success'))
                    <div class="mb-4 rounded-lg bg-green-100 border border-green-200 p-4 text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- Top Section --}}
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Product List
                    </h3>

                    <a href="{{ route('products.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        {{ __('New Product') }}
                    </a>
                </div>

                {{-- Filter Form --}}
                <form method="GET"
                      action="{{ route('products.index') }}"
                      class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">

                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Search Product
                        </label>

                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search product..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    {{-- Brand --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Brand
                        </label>

                        <input type="text"
                               name="brand"
                               value="{{ request('brand') }}"
                               placeholder="Filter brand..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    {{-- Stock --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Stock
                        </label>

                        <select name="stock"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                            <option value="">All</option>

                            <option value="available"
                                {{ request('stock') == 'available' ? 'selected' : '' }}>
                                Available
                            </option>

                            <option value="empty"
                                {{ request('stock') == 'empty' ? 'selected' : '' }}>
                                Empty
                            </option>
                        </select>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-end gap-2">

                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">
                            Filter
                        </button>

                        <a href="{{ route('products.index') }}"
                           class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-400">
                            Reset
                        </a>

                    </div>

                </form>

                {{-- Table --}}
                <div class="overflow-x-auto shadow-sm sm:rounded-lg">

                    <table class="min-w-full divide-y divide-gray-200">

                        <thead class="bg-gray-50">
                            <tr>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Brand
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Buy Price
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Discount
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sale Price
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sale Unit
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Purchase Unit
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Stock
                                </th>

                                <th class="px-6 py-3"></th>

                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">

                            @forelse($products as $product)

                                <tr>

                                    {{-- Product Name --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $product->product_name }}
                                    </td>

                                    {{-- Brand --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $product->brand ?? '-' }}
                                    </td>

                                    {{-- Buy Price --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        Rp {{ number_format($product->default_buy_price, 0, ',', '.') }}
                                    </td>

                                    {{-- Discount --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $product->default_buy_discount }}%
                                    </td>

                                    {{-- Sale Price --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                                    </td>

                                    {{-- Sale Unit --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $product->sale_unit }}
                                    </td>

                                    {{-- Purchase Unit --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $product->purchase_unit }}
                                    </td>

                                    {{-- Stock --}}
                                    {{-- Stock --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">

                                        @if($product->stock > 0)

                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                {{ $product->stock }} {{ ucfirst($product->sale_unit) }}
                                            </span>

                                        @else

                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                Out of Stock
                                            </span>

                                        @endif

                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">

                                        <a href="{{ route('products.edit', $product) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Edit
                                        </a>

                                        <form action="{{ route('products.destroy', $product) }}"
                                              method="POST"
                                              class="inline-block ms-3"
                                              onsubmit="return confirm('Delete this product?');">

                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="text-red-600 hover:text-red-900">
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="9"
                                        class="px-6 py-4 text-center text-sm text-gray-500">
                                        No products found.
                                    </td>
                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>

                {{-- Pagination --}}
                <div class="mt-4">
                    {{ $products->links() }}
                </div>

            </div>

        </div>
    </div>
</x-app-layout>