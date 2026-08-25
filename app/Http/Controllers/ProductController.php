<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(product_name) LIKE ?', ['%' . strtolower($request->search) . '%']);
        }

        if ($request->filled('brand')) {
            $query->whereRaw('LOWER(brand) LIKE ?', ['%' . strtolower($request->brand) . '%']);
        }

        if ($request->stock == 'available') {
            $query->where('stock', '>', 0);
        }

        if ($request->stock == 'empty') {
            $query->where('stock', '<=', 0);
        }

        $products = $query->latest()
            ->paginate(10)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_name'         => ['required', 'string', 'max:255'],
            'sale_price'           => ['required', 'numeric', 'min:0'],
            'brand'                => ['nullable', 'string', 'max:255'],
            'image'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'default_buy_price'    => ['required', 'numeric', 'min:0'],
            'default_buy_discount' => ['required', 'numeric', 'min:0'],
            'sale_unit'            => ['required', 'string', 'max:50'],
            'purchase_unit'        => ['required', 'string', 'max:50'],
            'dus_to_unit'          => ['required', 'numeric', 'min:0.01'],
            'stock'                => ['required', 'integer', 'min:0'],
        ]);

        // Upload gambar
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'product_name'         => ['required', 'string', 'max:255'],
            'sale_price'           => ['required', 'numeric', 'min:0'],
            'brand'                => ['nullable', 'string', 'max:255'],
            'image'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'default_buy_price'    => ['required', 'numeric', 'min:0'],
            'default_buy_discount' => ['required', 'numeric', 'min:0'],
            'sale_unit'            => ['required', 'string', 'max:50'],
            'purchase_unit'        => ['required', 'string', 'max:50'],
            'dus_to_unit'          => ['required', 'numeric', 'min:0.01'],
            'stock'                => ['required', 'integer', 'min:0'],
        ]);

        // Upload gambar baru — hapus yang lama
        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Hapus gambar kalau dicentang
        if ($request->boolean('remove_image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = null;
        }

        $product->update($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Hapus gambar saat produk dihapus
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }
}