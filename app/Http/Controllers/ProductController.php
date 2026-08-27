<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private SupabaseStorageService $supabaseStorage)
    {
    }

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

        unset($data['image']);

        // Upload gambar ke Supabase Storage
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = 'products/' . uniqid('product_', true) . '.' . $file->getClientOriginalExtension();
            $data['image_path'] = $this->supabaseStorage->upload($file, $path);
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

        unset($data['image']);

        // Upload gambar baru ke Supabase Storage — hapus yang lama
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                $this->supabaseStorage->delete($product->image_path);
            }
            $file = $request->file('image');
            $path = 'products/' . uniqid('product_', true) . '.' . $file->getClientOriginalExtension();
            $data['image_path'] = $this->supabaseStorage->upload($file, $path);
        }

        // Hapus gambar kalau dicentang
        if ($request->boolean('remove_image')) {
            if ($product->image_path) {
                $this->supabaseStorage->delete($product->image_path);
            }
            $data['image_path'] = null;
        }

        $product->update($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Hapus gambar saat produk dihapus
        if ($product->image_path) {
            $this->supabaseStorage->delete($product->image_path);
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }
}