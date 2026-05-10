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
        $products  = Product::all();

        return view('purchase-orders.create', compact('suppliers', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'  => 'required|exists:suppliers,id',
            'po_date'      => 'required|date',
            'status'       => 'required|string|max:255',
            'notes'        => 'nullable|string',
            'product_id'   => 'required|array|min:1',
            'product_id.*' => 'required|exists:products,id',
            'quantity'     => 'required|array',
            'quantity.*'   => 'required|numeric|min:1',
            'price'        => 'required|array',
            'price.*'      => 'required|numeric|min:0',
            'discount'     => 'required|array',
            'discount.*'   => 'required|numeric|min:0|max:100',
            'unit'         => 'required|array',
            'unit.*'       => 'nullable|string',
        ]);

        // Simpan PO header
        $po = new PurchaseOrder();
        $po->supplier_id = $request->supplier_id;
        $po->po_date     = $request->po_date;
        $po->status      = $request->status;
        $po->notes       = $request->notes;
        $po->save();

        // Simpan PO details
        $productIds = $request->product_id;
        $quantities = $request->quantity;
        $prices     = $request->price;
        $discounts  = $request->discount;
        $units      = $request->unit;

        foreach ($productIds as $i => $productId) {
            $qty      = $quantities[$i];
            $price    = $prices[$i];
            $discount = $discounts[$i] ?? 0;
            $subtotal = $qty * $price * (1 - ($discount / 100));

            $detail = new PurchaseOrderDetail();
            $detail->po_id      = $po->po_id;
            $detail->product_id = $productId;
            $detail->quantity   = $qty;
            $detail->unit       = $units[$i] ?? '';
            $detail->price      = $price;
            $detail->discount   = $discount;
            $detail->subtotal   = $subtotal;
            $detail->save();
        }

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order created successfully.');
    }

    public function show(string $id)
    {
        //
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