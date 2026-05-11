<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::latest()->paginate(10);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

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

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

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

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}
