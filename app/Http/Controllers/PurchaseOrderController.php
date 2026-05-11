<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Supplier;

use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with('supplier')
            ->latest('po_id')
            ->paginate(10);

        return view('purchase-orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $suppliers = Supplier::all();

        $products = Product::all();

        return view(
            'purchase-orders.create',
            compact('suppliers', 'products')
        );
    }

    public function store(Request $request)
    {
        $request->validate([

            // HEADER
            'supplier_id' => 'required|exists:suppliers,id',

            'po_date' => 'required|date',

            'status' => 'required|string|max:255',

            'notes' => 'nullable|string',

            // ITEMS
            'product_id' => 'required|array|min:1',

            'product_id.*' => 'required|exists:products,id',

            // qty supplier
            'purchase_qty' => 'required|array',

            'purchase_qty.*' => 'required|numeric|min:1',

            // harga per unit jual
            'price' => 'required|array',

            'price.*' => 'required|numeric|min:0',

            'discount' => 'required|array',

            'discount.*' => 'required|numeric|min:0|max:100',

        ]);

        // ─────────────────────────────
        // SIMPAN HEADER
        // ─────────────────────────────

        $po = new PurchaseOrder();

        $po->supplier_id = $request->supplier_id;

        $po->po_date = $request->po_date;

        $po->status = $request->status;

        $po->notes = $request->notes;

        $po->save();

        // ─────────────────────────────
        // DETAIL DATA
        // ─────────────────────────────

        $productIds = $request->product_id;

        $purchaseQtys = $request->purchase_qty;

        $prices = $request->price;

        $discounts = $request->discount;

        // ─────────────────────────────
        // LOOP ITEMS
        // ─────────────────────────────

        foreach ($productIds as $i => $productId) {

            $product = Product::findOrFail($productId);

            // qty pembelian supplier
            $purchaseQty = $purchaseQtys[$i];

            // convert ke qty stok
            $qty = $purchaseQty * $product->dus_to_unit;

            // harga per unit jual
            $price = $prices[$i];

            // discount
            $discount = $discounts[$i] ?? 0;

            // subtotal berdasarkan qty hasil conversion
            $subtotal = $qty * $price * (1 - ($discount / 100));

            // ─────────────────────────
            // SAVE DETAIL
            // ─────────────────────────

            $detail = new PurchaseOrderDetail();

            // relasi
            $detail->po_id = $po->po_id;

            $detail->product_id = $productId;

            // pembelian supplier
            $detail->purchase_qty = $purchaseQty;

            $detail->purchase_unit = $product->purchase_unit;

            // qty stok hasil conversion
            $detail->quantity = $qty;

            // unit stok
            $detail->unit = $product->sale_unit;

            // pricing
            $detail->price = $price;

            $detail->discount = $discount;

            $detail->subtotal = $subtotal;

            $detail->save();
        }

        return redirect()
            ->route('purchase-orders.index')
            ->with(
                'success',
                'Purchase order created successfully.'
            );
    }

    public function show(string $id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'details.product'
        ])->findOrFail($id);

        return view(
            'purchase-orders.show',
            compact('purchaseOrder')
        );
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}