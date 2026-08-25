<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;

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

        $products = Product::where('stock', '>', 0)
            ->when($search, fn($q) => $q->whereRaw('LOWER(product_name) LIKE ?', ['%' . strtolower($search) . '%']))
            ->orderBy('product_name')
            ->paginate(12);

        return view('toko.index', compact('products', 'search'));
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
        ]);

        $product   = Product::findOrFail($request->product_id);
        $keranjang = Session::get('keranjang', []);
        $key       = 'product_' . $product->id;

        if (isset($keranjang[$key])) {
            $keranjang[$key]['quantity'] += $request->quantity;
        } else {
            $keranjang[$key] = [
                'product_id'   => $product->id,
                'product_name' => $product->product_name,
                'unit'         => $product->sale_unit,
                'price'        => $product->sale_price,
                'quantity'     => $request->quantity,
                'subtotal'     => 0,
            ];
        }

        $keranjang[$key]['subtotal'] = $keranjang[$key]['quantity'] * $keranjang[$key]['price'];

        // Cek stok
        if ($keranjang[$key]['quantity'] > $product->stock) {
            return back()->with('error', "Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stock} {$product->sale_unit}");
        }

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

                if ($request->quantity > $product->stock) {
                    return back()->with('error', "Stok tidak mencukupi. Tersedia: {$product->stock} {$product->sale_unit}");
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

        DB::transaction(function () use ($request, $keranjang) {

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
                $product->stock -= $item['quantity'];
                $product->save();
            }

            Session::forget('keranjang');
            Session::put('last_order_id', $sale->sale_id);
        });

        return redirect()->route('toko.pesanan')
            ->with('success', 'Pesanan berhasil dikirim! Pemilik akan segera memproses pesananmu.');
    }

    // ─── Riwayat Pesanan ─────────────────────────────────────────────────────

    public function pesanan()
    {
        $lastOrderId = Session::get('last_order_id');

        $pesanan = Sale::with('details.product')
            ->where('user_id', Auth::id())
            ->latest('sale_id')
            ->take(10)
            ->get();

        return view('toko.pesanan', compact('pesanan', 'lastOrderId'));
    }
}