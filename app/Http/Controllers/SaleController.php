<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleDetail;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with('customer')
            ->latest('sale_id')
            ->paginate(10);

        return view('sales.index', compact('sales'));
    }

    public function create()
    {
        $customers = Customer::all();
        $products  = Product::where('stock', '>', 0)->get();

        return view('sales.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_date'      => 'required|date',
            'payment_method' => 'required|in:Cash,Transfer,Utang',
            'payment_status' => 'required|in:Lunas,Belum Bayar',
            'notes'          => 'nullable|string',
            'product_id'     => 'required|array|min:1',
            'product_id.*'   => 'required|exists:products,id',
            'quantity'       => 'required|array',
            'quantity.*'     => 'required|numeric|min:0.25',
            'price'          => 'required|array',
            'price.*'        => 'required|numeric|min:0',
            'discount'       => 'required|array',
            'discount.*'     => 'required|numeric|min:0|max:100',
            'due_date'       => 'nullable|date',
        ]);

        DB::transaction(function () use ($request) {

            $productIds = $request->product_id;
            $quantities = $request->quantity;
            $prices     = $request->price;
            $discounts  = $request->discount;
            $units      = $request->unit;

            // Cek stok dulu
            foreach ($productIds as $i => $productId) {
                $product = Product::findOrFail($productId);
                if ($product->stock < $quantities[$i]) {
                    throw new \Exception("Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stock}");
                }
            }

            // Hitung total
            $totalAmount = 0;
            foreach ($productIds as $i => $productId) {
                $qty      = $quantities[$i];
                $price    = $prices[$i];
                $discount = $discounts[$i] ?? 0;
                $totalAmount += $qty * $price * (1 - $discount / 100);
            }

            // Simpan header sale
            $sale = new Sale();
            $sale->customer_id     = $request->customer_id ?: null;
            $sale->user_id         = Auth::id();
            $sale->sale_date       = $request->sale_date;
            $sale->payment_method  = $request->payment_method;
            $sale->payment_status  = $request->payment_status;
            $sale->paid_date       = $request->payment_status === 'Lunas' ? $request->sale_date : null;
            $sale->total_amount    = $totalAmount;
            $sale->notes           = $request->notes;
            $sale->save();

            // Simpan detail + kurangi stok
            foreach ($productIds as $i => $productId) {
                $qty      = $quantities[$i];
                $price    = $prices[$i];
                $discount = $discounts[$i] ?? 0;
                $subtotal = $qty * $price * (1 - $discount / 100);

                $detail = new SaleDetail();
                $detail->sale_id    = $sale->sale_id;
                $detail->product_id = $productId;
                $detail->quantity   = $qty;
                $detail->unit       = $units[$i] ?? '';
                $detail->price      = $price;
                $detail->discount   = $discount;
                $detail->subtotal   = $subtotal;
                $detail->save();

                // Kurangi stok
                $product = Product::findOrFail($productId);
                $product->stock -= $qty;
                $product->save();
            }

            // Buat receivable kalau Utang atau Belum Bayar
            if ($request->payment_status === 'Belum Bayar') {
                $receivable = new Receivable();
                $receivable->sale_id          = $sale->sale_id;
                $receivable->customer_id      = $sale->customer_id;
                $receivable->due_date         = $request->due_date;
                $receivable->amount           = $totalAmount;
                $receivable->paid_amount      = 0;
                $receivable->remaining_amount = $totalAmount;
                $receivable->status           = 'Unpaid';
                $receivable->save();
            }
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Transaksi penjualan berhasil disimpan.');
    }

    public function show(string $id)
    {
        $sale = Sale::with([
            'customer',
            'details.product',
            'receivable',
        ])->findOrFail($id);

        return view('sales.show', compact('sale'));
    }

    public function edit(string $id)
    {
        $sale      = Sale::with('details.product', 'receivable')->findOrFail($id);
        $customers = Customer::all();
        $products  = Product::all();

        return view('sales.edit', compact('sale', 'customers', 'products'));
    }

    public function update(Request $request, string $id)
    {
        $sale = Sale::with('details', 'receivable')->findOrFail($id);

        $request->validate([
            'sale_date'      => 'required|date',
            'payment_method' => 'required|in:Cash,Transfer,Utang',
            'payment_status' => 'required|in:Lunas,Belum Bayar',
            'notes'          => 'nullable|string',
            'product_id'     => 'required|array|min:1',
            'product_id.*'   => 'required|exists:products,id',
            'quantity'       => 'required|array',
            'quantity.*'     => 'required|numeric|min:0.25',
            'price'          => 'required|array',
            'price.*'        => 'required|numeric|min:0',
            'discount'       => 'required|array',
            'discount.*'     => 'required|numeric|min:0|max:100',
            'due_date'       => 'nullable|date',
        ]);

        DB::transaction(function () use ($request, $sale) {

            // Kembalikan stok lama
            foreach ($sale->details as $oldDetail) {
                $product = Product::findOrFail($oldDetail->product_id);
                $product->stock += $oldDetail->quantity;
                $product->save();
            }

            $sale->details()->delete();

            $productIds = $request->product_id;
            $quantities = $request->quantity;
            $prices     = $request->price;
            $discounts  = $request->discount;
            $units      = $request->unit;

            // Cek stok baru
            foreach ($productIds as $i => $productId) {
                $product = Product::findOrFail($productId);
                if ($product->stock < $quantities[$i]) {
                    throw new \Exception("Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stock}");
                }
            }

            $totalAmount = 0;

            foreach ($productIds as $i => $productId) {
                $qty      = $quantities[$i];
                $price    = $prices[$i];
                $discount = $discounts[$i] ?? 0;
                $subtotal = $qty * $price * (1 - $discount / 100);
                $totalAmount += $subtotal;

                $detail = new SaleDetail();
                $detail->sale_id    = $sale->sale_id;
                $detail->product_id = $productId;
                $detail->quantity   = $qty;
                $detail->unit       = $units[$i] ?? '';
                $detail->price      = $price;
                $detail->discount   = $discount;
                $detail->subtotal   = $subtotal;
                $detail->save();

                $product = Product::findOrFail($productId);
                $product->stock -= $qty;
                $product->save();
            }

            // Update header
            $sale->customer_id    = $request->customer_id ?: null;
            $sale->sale_date      = $request->sale_date;
            $sale->payment_method = $request->payment_method;
            $sale->payment_status = $request->payment_status;
            $sale->paid_date      = $request->payment_status === 'Lunas' ? now()->toDateString() : null;
            $sale->total_amount   = $totalAmount;
            $sale->notes          = $request->notes;
            $sale->save();

            // Update receivable
            if ($request->payment_status === 'Belum Bayar') {
                // Buat atau update receivable
                $receivable = $sale->receivable ?? new Receivable();
                $receivable->sale_id     = $sale->sale_id;
                $receivable->customer_id = $sale->customer_id;
                $receivable->due_date    = $request->due_date;
                $receivable->amount      = $totalAmount;
                $receivable->status      = 'Unpaid';
                $receivable->save();
            } else {
                // Lunas — hapus receivable kalau ada
                if ($sale->receivable) {
                    $sale->receivable->status = 'Paid';
                    $sale->receivable->save();
                }
            }
        });

        return redirect()
            ->route('sales.show', $sale->sale_id)
            ->with('success', 'Transaksi penjualan berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $sale = Sale::with('details', 'receivable')->findOrFail($id);

        DB::transaction(function () use ($sale) {
            // Kembalikan stok
            foreach ($sale->details as $detail) {
                $product = Product::findOrFail($detail->product_id);
                $product->stock += $detail->quantity;
                $product->save();
            }

            // Hapus receivable kalau ada
            if ($sale->receivable) {
                $sale->receivable->delete();
            }

            $sale->details()->delete();
            $sale->delete();
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Transaksi penjualan berhasil dihapus.');
    }
}