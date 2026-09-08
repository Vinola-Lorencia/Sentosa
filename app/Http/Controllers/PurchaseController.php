<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\UnitConverter;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with('supplier', 'purchaseOrder')
            ->latest('purchase_id')
            ->paginate(10);

        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        // Hanya PO yang sudah Ordered atau Pending yang bisa dijadikan pembelian
        $purchaseOrders = PurchaseOrder::with('supplier')
            ->whereIn('status', ['Pending', 'Ordered'])
            ->latest('po_id')
            ->get();

        return view('purchases.create', compact('purchaseOrders'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'po_id'          => 'required|exists:purchase_orders,po_id',
            'purchase_date'  => 'required|date',
            'notes'          => 'nullable|string',
            'product_id'     => 'required|array|min:1',
            'product_id.*'   => 'required|exists:products,id',
            'quantity'       => 'required|array',
            'quantity.*'     => 'required|numeric|min:0',
            'price'          => 'required|array',
            'price.*'        => 'required|numeric|min:0',
            'discount'       => 'required|array',
            'discount.*'     => 'required|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request) {

            $productIds = $request->product_id;
            $quantities = $request->quantity;
            $prices     = $request->price;
            $discounts  = $request->discount;
            $units      = $request->unit;

            // Hitung total amount
            $totalAmount = 0;
            foreach ($productIds as $i => $productId) {
                $qty      = $quantities[$i];
                $price    = $prices[$i];
                $discount = $discounts[$i] ?? 0;
                $subtotal = $qty * $price * (1 - $discount / 100);
                $totalAmount += $subtotal;
            }

            // Ambil supplier_id dari PO
            $po = PurchaseOrder::findOrFail($request->po_id);

            // Simpan header purchase
            $purchase = new Purchase();
            $purchase->po_id         = $request->po_id;
            $purchase->supplier_id   = $po->supplier_id;
            $purchase->user_id       = Auth::id();
            $purchase->purchase_date = $request->purchase_date;
            $purchase->total_amount  = $totalAmount;
            $purchase->notes         = $request->notes;
            $purchase->save();

            // Simpan detail + update stok
            foreach ($productIds as $i => $productId) {
                $qty      = $quantities[$i];
                $price    = $prices[$i];
                $discount = $discounts[$i] ?? 0;
                $subtotal = $qty * $price * (1 - $discount / 100);

                $detail = new PurchaseDetail();
                $detail->purchase_id = $purchase->purchase_id;
                $detail->product_id  = $productId;
                $detail->quantity    = $qty;
                $detail->unit        = $units[$i] ?? '';
                $detail->price       = $price;
                $detail->discount    = $discount;
                $detail->subtotal    = $subtotal;
                $detail->save();

                // Update stok produk — $qty diterima dalam satuan unit[]
                // (mis. Lusin), stock disimpan dalam Buah, jadi harus
                // dikonversi dulu sebelum ditambahkan.
                $product = Product::findOrFail($productId);
                $buahDiterima = UnitConverter::toBaseUnit((float) $qty, $units[$i] ?? '', $product);
                $product->stock += $buahDiterima;
                $product->save();
            }

            // Update status PO jadi Completed
            $po->status = 'Completed';
            $po->save();
        });

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Transaksi pembelian berhasil disimpan.');
    }

    public function show(string $id)
    {
        $purchase = Purchase::with([
            'supplier',
            'purchaseOrder',
            'details.product',
        ])->findOrFail($id);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(string $id)
    {
        $purchase = Purchase::with('details.product')->findOrFail($id);

        $purchaseOrders = PurchaseOrder::with('supplier')
            ->latest('po_id')
            ->get();

        return view('purchases.edit', compact('purchase', 'purchaseOrders'));
    }

    public function update(Request $request, string $id)
    {
        $purchase = Purchase::with('details')->findOrFail($id);

        $request->validate([
            'purchase_date' => 'required|date',
            'notes'         => 'nullable|string',
            'product_id'    => 'required|array|min:1',
            'product_id.*'  => 'required|exists:products,id',
            'quantity'      => 'required|array',
            'quantity.*'    => 'required|numeric|min:0',
            'price'         => 'required|array',
            'price.*'       => 'required|numeric|min:0',
            'discount'      => 'required|array',
            'discount.*'    => 'required|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $purchase) {

            // Kembalikan stok lama (dikonversi ke Buah)
            foreach ($purchase->details as $oldDetail) {
                $product = Product::findOrFail($oldDetail->product_id);
                $buahLama = UnitConverter::toBaseUnit((float) $oldDetail->quantity, $oldDetail->unit ?? '', $product);
                $product->stock -= $buahLama;
                $product->save();
            }

            // Hapus detail lama
            $purchase->details()->delete();

            $productIds = $request->product_id;
            $quantities = $request->quantity;
            $prices     = $request->price;
            $discounts  = $request->discount;
            $units      = $request->unit;

            $totalAmount = 0;

            foreach ($productIds as $i => $productId) {
                $qty      = $quantities[$i];
                $price    = $prices[$i];
                $discount = $discounts[$i] ?? 0;
                $subtotal = $qty * $price * (1 - $discount / 100);
                $totalAmount += $subtotal;

                $detail = new PurchaseDetail();
                $detail->purchase_id = $purchase->purchase_id;
                $detail->product_id  = $productId;
                $detail->quantity    = $qty;
                $detail->unit        = $units[$i] ?? '';
                $detail->price       = $price;
                $detail->discount    = $discount;
                $detail->subtotal    = $subtotal;
                $detail->save();

                // Update stok baru (dikonversi ke Buah)
                $product = Product::findOrFail($productId);
                $buahDiterima = UnitConverter::toBaseUnit((float) $qty, $units[$i] ?? '', $product);
                $product->stock += $buahDiterima;
                $product->save();
            }

            // Update header
            $purchase->purchase_date = $request->purchase_date;
            $purchase->total_amount  = $totalAmount;
            $purchase->notes         = $request->notes;
            $purchase->save();
        });

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Transaksi pembelian berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $purchase = Purchase::with('details')->findOrFail($id);

        DB::transaction(function () use ($purchase) {

            // Kembalikan stok (dikonversi ke Buah)
            foreach ($purchase->details as $detail) {
                $product = Product::findOrFail($detail->product_id);
                $buah = UnitConverter::toBaseUnit((float) $detail->quantity, $detail->unit ?? '', $product);
                $product->stock -= $buah;
                $product->save();
            }

            $purchase->details()->delete();
            $purchase->delete();
        });

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Transaksi pembelian berhasil dihapus.');
    }
}