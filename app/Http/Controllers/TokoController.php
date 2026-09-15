<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
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

        $products = Product::available()
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
        $keranjang = CartItem::with('product')->where('user_id', Auth::id())->get();
        $total     = $keranjang->sum('subtotal');

        return view('toko.keranjang', compact('keranjang', 'total'));
    }

    public function tambahKeranjang(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|min:0.25',
            'unit'       => 'required|in:Buah,Lusin,Kodi,Gross',
        ]);

        $product = Product::findOrFail($request->product_id);
        $unit    = $request->unit;
        $userId  = Auth::id();

        // One row per user+product+unit (see the cart_items migration's
        // unique constraint) — the same product added in two different
        // units (e.g. 5 Buah on one visit, 1 Lusin on another) stays as two
        // separate rows, since price varies per chosen unit and there is no
        // single unit two such rows could be merged into.
        $existing = CartItem::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->where('unit', $unit)
            ->first();

        $newQuantity = (float) $request->quantity + (float) ($existing->quantity ?? 0);

        // Stock is one shared pool per product regardless of which unit(s)
        // it's expressed in across cart rows, so the check sums every other
        // row for this product — converted to true base units via
        // UnitConverter::toBaseUnit(), which is independent of sale_unit
        // (1 Lusin is always 12 pieces) — plus the new total for this unit.
        $totalBaseQtyForProduct = CartItem::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->where('unit', '!=', $unit)
            ->get()
            ->sum(fn ($item) => UnitConverter::toBaseUnit((float) $item->quantity, $item->unit, $product));

        $totalBaseQtyForProduct += UnitConverter::toBaseUnit($newQuantity, $unit, $product);

        if ($totalBaseQtyForProduct > $product->stock) {
            return back()->with('error', "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}. Silakan sesuaikan jumlah pesanan.");
        }

        CartItem::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $product->id, 'unit' => $unit],
            ['quantity' => $newQuantity]
        );

        return back()->with('success', "{$product->product_name} ditambahkan ke keranjang.");
    }

    public function updateKeranjang(Request $request, string $id)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        // Always scoped to the logged-in user — cart_items.id is a global
        // auto-increment shared across every customer, so without this a
        // user could edit/delete another customer's cart row just by
        // guessing an id.
        $item = CartItem::where('user_id', Auth::id())->where('id', $id)->first();

        if ($item) {
            if ($request->quantity <= 0) {
                $item->delete();
            } else {
                $product = $item->product;

                $totalBaseQtyForProduct = CartItem::where('user_id', Auth::id())
                    ->where('product_id', $product->id)
                    ->where('id', '!=', $item->id)
                    ->get()
                    ->sum(fn ($i) => UnitConverter::toBaseUnit((float) $i->quantity, $i->unit, $product));

                $totalBaseQtyForProduct += UnitConverter::toBaseUnit((float) $request->quantity, $item->unit, $product);

                if ($totalBaseQtyForProduct > $product->stock) {
                    return back()->with('error', "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}. Silakan sesuaikan jumlah pesanan.");
                }

                $item->quantity = (float) $request->quantity;
                $item->save();
            }
        }

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function hapusKeranjang(string $id)
    {
        CartItem::where('user_id', Auth::id())->where('id', $id)->delete();

        return back()->with('success', 'Item dihapus dari keranjang.');
    }

    // ─── Checkout ────────────────────────────────────────────────────────────

    public function checkout()
    {
        $keranjang = CartItem::with('product')->where('user_id', Auth::id())->get();

        if ($keranjang->isEmpty()) {
            return redirect()->route('toko.keranjang')
                ->with('error', 'Keranjang kosong.');
        }

        $total = $keranjang->sum('subtotal');

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

        $userId    = Auth::id();
        $keranjang = CartItem::with('product')->where('user_id', $userId)->get();

        if ($keranjang->isEmpty()) {
            return redirect()->route('toko.index')
                ->with('error', 'Keranjang kosong.');
        }

        try {
            DB::transaction(function () use ($request, $keranjang, $userId) {

                // Cek stok SEMUA item dulu, sebelum menyimpan apa pun. Kalau
                // satu produk kurang, exception ini membatalkan (rollback)
                // seluruh transaksi — sale/sale_detail yang sempat tersimpan
                // untuk item lain di baris berikutnya ikut dibatalkan, dan
                // ditangkap oleh catch di bawah. cart_items tidak pernah
                // disentuh sebelum baris ini berhasil sampai akhir, jadi
                // keranjang otomatis tetap utuh di database kalau gagal.
                foreach ($keranjang as $item) {
                    $product = $item->product;
                    $baseQty = UnitConverter::toBaseUnit((float) $item->quantity, $item->unit, $product);

                    if ($baseQty > $product->stock) {
                        throw new \Exception(
                            "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}. Silakan sesuaikan jumlah pesanan."
                        );
                    }
                }

                $total = $keranjang->sum('subtotal');

                // ✅ Cari customer by nama user yang login
                $customer = Customer::where('customer_name', Auth::user()->name)->first();

                // Simpan sale
                $sale = new Sale();
                $sale->customer_id    = $customer?->id ?? null;
                $sale->user_id        = $userId;
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
                    $product = $item->product;

                    $detail = new SaleDetail();
                    $detail->sale_id    = $sale->sale_id;
                    $detail->product_id = $item->product_id;
                    $detail->quantity   = $item->quantity;
                    $detail->unit       = $item->unit;
                    $detail->price      = $item->price;
                    $detail->discount   = 0;
                    $detail->subtotal   = $item->subtotal;
                    $detail->save();

                    $product->stock -= UnitConverter::toBaseUnit((float) $item->quantity, $item->unit, $product);
                    $product->save();
                }

                // Cart only cleared once every row above has committed.
                CartItem::where('user_id', $userId)->delete();

                Session::put('last_order_id', $sale->sale_id);
            });
        } catch (\Exception $e) {
            // Cart rows are untouched — the delete() above never ran, since
            // the exception aborted the transaction before reaching it.
            return redirect()->route('toko.keranjang')
                ->with('error', $e->getMessage());
        }

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