<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // Search product name
        if ($request->filled('search')) {
            $query->where('product_name', 'like', '%' . $request->search . '%');
        }

        // Filter brand
        if ($request->filled('brand')) {
            $query->where('brand', 'like', '%' . $request->brand . '%');
        }

        // Filter stock
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('products.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'default_buy_price' => ['required', 'numeric', 'min:0'],
            'default_buy_discount' => ['required', 'numeric', 'min:0'],
            'sale_unit' => ['required', 'string', 'max:50'],
            'purchase_unit' => ['required', 'string', 'max:50'],
            'dus_to_unit' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        Product::create($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'default_buy_price' => ['required', 'numeric', 'min:0'],
            'default_buy_discount' => ['required', 'numeric', 'min:0'],
            'sale_unit' => ['required', 'string', 'max:50'],
            'purchase_unit' => ['required', 'string', 'max:50'],
            'dus_to_unit' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $product->update($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }
}