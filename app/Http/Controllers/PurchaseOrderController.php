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
            'supplier_id'    => 'required|exists:suppliers,id',
            'po_date'        => 'required|date',
            'status'         => 'required|string|max:255',
            'notes'          => 'nullable|string',
            'product_id'     => 'required|array|min:1',
            'product_id.*'   => 'required|exists:products,id',
            'purchase_qty'   => 'required|array',
            'purchase_qty.*' => 'required|numeric|min:1',
            'price'          => 'required|array',
            'price.*'        => 'required|numeric|min:0',
            'discount'       => 'required|array',
            'discount.*'     => 'required|numeric|min:0|max:100',
        ]);

        $po = new PurchaseOrder();
        $po->supplier_id = $request->supplier_id;
        $po->po_date     = $request->po_date;
        $po->status      = $request->status;
        $po->notes       = $request->notes;
        $po->save();

        $this->saveDetails($po->po_id, $request);

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order created successfully.');
    }

    public function show(string $id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'details.product'
        ])->findOrFail($id);

        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function edit(string $id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'details.product'
        ])->findOrFail($id);

        $suppliers = Supplier::all();
        $products  = Product::all();

        return view('purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'products'));
    }

    public function update(Request $request, string $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'po_date'        => 'required|date',
            'status'         => 'required|string|max:255',
            'notes'          => 'nullable|string',
            'product_id'     => 'required|array|min:1',
            'product_id.*'   => 'required|exists:products,id',
            'purchase_qty'   => 'required|array',
            'purchase_qty.*' => 'required|numeric|min:1',
            'price'          => 'required|array',
            'price.*'        => 'required|numeric|min:0',
            'discount'       => 'required|array',
            'discount.*'     => 'required|numeric|min:0|max:100',
        ]);

        $purchaseOrder->supplier_id = $request->supplier_id;
        $purchaseOrder->po_date     = $request->po_date;
        $purchaseOrder->status      = $request->status;
        $purchaseOrder->notes       = $request->notes;
        $purchaseOrder->save();

        $purchaseOrder->details()->delete();
        $this->saveDetails($purchaseOrder->po_id, $request);

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(string $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        $purchaseOrder->details()->delete();
        $purchaseOrder->delete();

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order deleted successfully.');
    }

    // ─── Get items untuk AJAX di modul Pembelian ─────────────────────────────

    public function getItems(string $id)
    {
        $po = PurchaseOrder::with('details.product', 'supplier')->findOrFail($id);

        return response()->json([
            'supplier_id'   => $po->supplier_id,
            'supplier_name' => $po->supplier->supplier_name,
            'items'         => $po->details->map(fn($d) => [
                'product_id'    => $d->product_id,
                'product_name'  => $d->product->product_name,
                'purchase_qty'  => $d->purchase_qty,
                'purchase_unit' => $d->purchase_unit,
                'quantity'      => $d->quantity,
                'unit'          => $d->unit,
                'price'         => $d->price,
                'discount'      => $d->discount,
                'subtotal'      => $d->subtotal,
            ])
        ]);
    }

    // ─── Send PO via WhatsApp ─────────────────────────────────────────────────

    public function sendWhatsApp(string $id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'details.product'
        ])->findOrFail($id);

        $supplier = $purchaseOrder->supplier;

        $message  = "PURCHASE ORDER\n";
        $message .= "PO Number: PO-" . str_pad($purchaseOrder->po_id, 3, '0', STR_PAD_LEFT) . "\n";
        $message .= "Date: " . $purchaseOrder->po_date . "\n\n";
        $message .= "Items:\n";

        foreach ($purchaseOrder->details as $detail) {
            $message .= "- " . $detail->product->product_name;
            $message .= " | " . $detail->purchase_qty . " " . $detail->purchase_unit;
            $message .= "\n";
        }

        $message .= "\nThank you.";

        $phone = preg_replace('/[^0-9]/', '', $supplier->phone);
        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }

        $url = "https://wa.me/" . $phone . "?text=" . urlencode($message);

        return redirect($url);
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE HELPER — simpan detail (dipakai store & update)
    // ─────────────────────────────────────────────────────────

    private function saveDetails(int $poId, Request $request): void
    {
        $productIds   = $request->product_id;
        $purchaseQtys = $request->purchase_qty;
        $prices       = $request->price;
        $discounts    = $request->discount;

        foreach ($productIds as $i => $productId) {
            $product = Product::findOrFail($productId);

            $purchaseQty = $purchaseQtys[$i];
            $qty         = $purchaseQty * $product->dus_to_unit;
            $price       = $prices[$i];
            $discount    = $discounts[$i] ?? 0;
            $subtotal    = $qty * $price * (1 - ($discount / 100));

            $detail = new PurchaseOrderDetail();
            $detail->po_id         = $poId;
            $detail->product_id    = $productId;
            $detail->purchase_qty  = $purchaseQty;
            $detail->purchase_unit = $product->purchase_unit;
            $detail->quantity      = $qty;
            $detail->unit          = $product->sale_unit;
            $detail->price         = $price;
            $detail->discount      = $discount;
            $detail->subtotal      = $subtotal;
            $detail->save();
        }
    }
}