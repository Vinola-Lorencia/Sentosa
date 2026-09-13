<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\UnitConverter;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class TokoController extends Controller
{
    // ─── Katalog ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search = $request->search;
        $filter = $request->get('filter');

        $products = Product::where('stock', '>', 0)
            ->when($search, fn($q) => $q->whereRaw('LOWER(product_name) LIKE ?', ['%' . strtolower($search) . '%']))
            // ?filter=baru — same "new" definition as the navbar badge, so
            // clicking the badge shows exactly the products it counted.
            ->when($filter === 'baru', fn($q) => $q->newProducts()->unseenBy(Auth::id()))
            // Eager-loaded per the logged-in Pembeli only, so the "Baru"
            // ribbon (index.blade.php checks $product->views->isEmpty())
            // doesn't cost an extra query per card.
            ->with(['views' => fn($q) => $q->where('user_id', Auth::id())])
            ->orderBy('product_name')
            ->paginate(12)
            ->withQueryString();

        return view('toko.index', compact('products', 'search', 'filter'));
    }

    /**
     * Mark a single product as "seen" by the current user — this is what
     * clears both its own "Baru" ribbon and the navbar's new-product badge
     * count for that product specifically. Never triggered just by loading
     * the catalog list; only by opening a product's own detail.
     */
    public function markProductViewed(Product $product)
    {
        ProductView::firstOrCreate(
            ['user_id' => Auth::id(), 'product_id' => $product->id],
            ['viewed_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    // ─── Keranjang ───────────────────────────────────────────────────────────

    public function keranjang()
    {
        $keranjang = Session::get('keranjang', []);
        $total     = 0;

        foreach ($keranjang as $item) {
            $total += $item['subtotal'];
        }

        return view('toko.keranjang', compact('keranjang', 'total'));
    }

    public function tambahKeranjang(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|min:0.25',
            'unit'       => 'required|in:Buah,Lusin,Kodi,Gross',
        ]);

        $product   = Product::findOrFail($request->product_id);
        $keranjang = Session::get('keranjang', []);
        $key       = 'product_' . $product->id;

        // sale_price is quoted per sale_unit, so the cart always keeps
        // quantity/unit in sale_unit terms (same convention as
        // WhatsAppController::convertUnit()) regardless of which unit the
        // customer picked in the dropdown — route through true base units
        // so quantities from different chosen units merge correctly.
        $baseQtyToAdd = UnitConverter::toBaseUnit((float) $request->quantity, $request->unit, $product);

        $existingBaseQty = isset($keranjang[$key])
            ? UnitConverter::toBaseUnit((float) $keranjang[$key]['quantity'], $keranjang[$key]['unit'], $product)
            : 0.0;

        $totalBaseQty = $existingBaseQty + $baseQtyToAdd;

        if ($totalBaseQty > $product->stock) {
            return back()->with('error', "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}. Silakan sesuaikan jumlah pesanan.");
        }

        $qtyInSaleUnit = UnitConverter::fromBaseUnit($totalBaseQty, $product->sale_unit, $product);

        $keranjang[$key] = [
            'product_id'   => $product->id,
            'product_name' => $product->product_name,
            'unit'         => $product->sale_unit,
            'price'        => (float) $product->sale_price,
            'quantity'     => $qtyInSaleUnit,
            'subtotal'     => $qtyInSaleUnit * (float) $product->sale_price,
        ];

        Session::put('keranjang', $keranjang);

        return back()->with('success', "{$product->product_name} ditambahkan ke keranjang.");
    }

    public function updateKeranjang(Request $request, string $id)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        $keranjang = Session::get('keranjang', []);
        $key       = 'product_' . $id;

        if (isset($keranjang[$key])) {
            if ($request->quantity <= 0) {
                unset($keranjang[$key]);
            } else {
                $product = Product::findOrFail($id);

                // Cart quantity is stored in sale_unit terms — convert to
                // true base units before comparing against stock (also
                // stored in base/Buah units).
                $baseQty = UnitConverter::toBaseUnit((float) $request->quantity, $keranjang[$key]['unit'], $product);

                if ($baseQty > $product->stock) {
                    return back()->with('error', "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}. Silakan sesuaikan jumlah pesanan.");
                }

                $keranjang[$key]['quantity'] = $request->quantity;
                $keranjang[$key]['subtotal'] = $request->quantity * $keranjang[$key]['price'];
            }
        }

        Session::put('keranjang', $keranjang);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function hapusKeranjang(string $id)
    {
        $keranjang = Session::get('keranjang', []);
        unset($keranjang['product_' . $id]);
        Session::put('keranjang', $keranjang);

        return back()->with('success', 'Item dihapus dari keranjang.');
    }

    // ─── Checkout ────────────────────────────────────────────────────────────

    public function checkout()
    {
        $keranjang = Session::get('keranjang', []);

        if (empty($keranjang)) {
            return redirect()->route('toko.keranjang')
                ->with('error', 'Keranjang kosong.');
        }

        $total = collect($keranjang)->sum('subtotal');

        return view('toko.checkout', compact('keranjang', 'total'));
    }

    public function prosesCheckout(Request $request)
    {
        $request->validate([
            'nama'           => 'required|string|max:255',
            'alamat'         => 'nullable|string',
            'catatan'        => 'nullable|string',
            'payment_method' => 'required|in:Cash,Transfer,Utang',
        ]);

        $keranjang = Session::get('keranjang', []);

        if (empty($keranjang)) {
            return redirect()->route('toko.index')
                ->with('error', 'Keranjang kosong.');
        }

        try {
            DB::transaction(function () use ($request, $keranjang) {

                // Cek stok SEMUA item dulu, sebelum menyimpan apa pun. Kalau
                // satu produk kurang, exception ini membatalkan (rollback)
                // seluruh transaksi — sale/sale_detail yang sempat tersimpan
                // untuk item lain di baris berikutnya ikut dibatalkan — dan
                // ditangkap oleh catch di bawah tanpa pernah menyentuh
                // Session::forget('keranjang'), jadi keranjang tetap utuh.
                foreach ($keranjang as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $baseQty = UnitConverter::toBaseUnit((float) $item['quantity'], $item['unit'], $product);

                    if ($baseQty > $product->stock) {
                        throw new \Exception(
                            "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}. Silakan sesuaikan jumlah pesanan."
                        );
                    }
                }

                $total = collect($keranjang)->sum('subtotal');

                // ✅ Cari customer by nama user yang login
                $customer = Customer::where('customer_name', Auth::user()->name)->first();

                // Simpan sale
                $sale = new Sale();
                $sale->customer_id    = $customer?->id ?? null;
                $sale->user_id        = Auth::id();
                $sale->sale_date      = now()->toDateString();
                $sale->payment_method = $request->payment_method;
                $sale->payment_status = 'Belum Bayar';
                $sale->total_amount   = $total;
                $sale->is_new         = true;
                $sale->notes          = "Pembeli: {$request->nama}" .
                                        ($request->alamat ? " | Alamat: {$request->alamat}" : '') .
                                        ($request->catatan ? " | Catatan: {$request->catatan}" : '');
                $sale->save();

                // Simpan detail + kurangi stok
                foreach ($keranjang as $item) {
                    $detail = new SaleDetail();
                    $detail->sale_id    = $sale->sale_id;
                    $detail->product_id = $item['product_id'];
                    $detail->quantity   = $item['quantity'];
                    $detail->unit       = $item['unit'];
                    $detail->price      = $item['price'];
                    $detail->discount   = 0;
                    $detail->subtotal   = $item['subtotal'];
                    $detail->save();

                    $product = Product::findOrFail($item['product_id']);
                    $product->stock -= UnitConverter::toBaseUnit((float) $item['quantity'], $item['unit'], $product);
                    $product->save();
                }

                Session::put('last_order_id', $sale->sale_id);
            });
        } catch (\Exception $e) {
            // Cart is left untouched — we never call Session::forget('keranjang')
            // on this path, so the customer can adjust/remove the offending
            // item and retry without losing the rest of their cart.
            return redirect()->route('toko.keranjang')
                ->with('error', $e->getMessage());
        }

        // Only clear the cart once the whole transaction has committed.
        Session::forget('keranjang');

        return redirect()->route('toko.pesanan')
            ->with('success', 'Pesanan berhasil dikirim! Pemilik akan segera memproses pesananmu.');
    }

    // ─── Riwayat Pesanan ─────────────────────────────────────────────────────

    public function pesanan()
    {
        $lastOrderId = Session::get('last_order_id');

        // Sales made through the storefront always carry the checkout
        // user's own id (set in prosesCheckout()), so filtering by user_id
        // is a direct, reliable link — no need to fall back to matching
        // customers by phone/name, which the `users` table doesn't even
        // have a phone column for.
        $pesanan = Sale::with('details.product')
            ->where('user_id', Auth::id())
            ->latest('created_at')
            ->paginate(10);

        return view('toko.pesanan', compact('pesanan', 'lastOrderId'));
    }

    // ─── Tagihan Saya ────────────────────────────────────────────────────────

    public function tagihan()
    {
        // Invoices/receivables are recorded against customer_id (set whether
        // the sale came from the storefront or was entered by staff for a
        // walk-in), not user_id (which only tracks who processed a storefront
        // checkout) — so this must go through the customers.user_id link
        // rather than reusing pesanan()'s user_id filter.
        $customer = Auth::user()->customer;

        $sales = Sale::with('receivable.payments')
            ->where('customer_id', $customer?->id ?? 0)
            ->latest('sale_date')
            ->paginate(10);

        return view('toko.tagihan', compact('sales', 'customer'));
    }
}