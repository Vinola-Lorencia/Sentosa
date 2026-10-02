<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\UnitConverter;
use Carbon\Carbon;
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
        $products = Product::where('stock', '>', 0)->get();

        return view('sales.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_date' => 'required|date',
            'payment_method' => 'required|in:Cash,Transfer,Utang',
            'payment_status' => 'required|in:Lunas,Belum Bayar',
            'notes' => 'nullable|string',
            'product_id' => 'required|array|min:1',
            'product_id.*' => 'required|exists:products,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|numeric|min:0.25',
            'price' => 'required|array',
            'price.*' => 'required|numeric|min:0',
            'discount' => 'required|array',
            'discount.*' => 'required|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
        ]);

        try {
            DB::transaction(function () use ($request) {

                $productIds = $request->product_id;
                $quantities = $request->quantity;
                $prices = $request->price;
                $discounts = $request->discount;
                $units = $request->unit;

                // Cek stok dulu (dikonversi ke sale_unit)
                foreach ($productIds as $i => $productId) {
                    $product = Product::findOrFail($productId);
                    $baseQty = UnitConverter::toBaseUnit((float) $quantities[$i], $units[$i] ?? '', $product);
                    if ($product->stock < $baseQty) {
                        throw new \Exception("Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}.");
                    }
                }

                // Hitung total
                $totalAmount = 0;
                foreach ($productIds as $i => $productId) {
                    $qty = $quantities[$i];
                    $price = $prices[$i];
                    $discount = $discounts[$i] ?? 0;
                    $totalAmount += $qty * $price * (1 - $discount / 100);
                }

                // Simpan header sale
                $sale = new Sale;
                $sale->customer_id = $request->customer_id ?: null;
                $sale->user_id = Auth::id();
                $sale->sale_date = $request->sale_date;
                $sale->payment_method = $request->payment_method;
                $sale->payment_status = $request->payment_status;
                $sale->paid_date = $request->payment_status === 'Lunas' ? $request->sale_date : null;
                $sale->total_amount = $totalAmount;
                $sale->notes = $request->notes;
                $sale->save();

                // Simpan detail + kurangi stok
                foreach ($productIds as $i => $productId) {
                    $qty = $quantities[$i];
                    $price = $prices[$i];
                    $discount = $discounts[$i] ?? 0;
                    $subtotal = $qty * $price * (1 - $discount / 100);

                    $detail = new SaleDetail;
                    $detail->sale_id = $sale->sale_id;
                    $detail->product_id = $productId;
                    $detail->quantity = $qty;
                    $detail->unit = $units[$i] ?? '';
                    $detail->price = $price;
                    $detail->discount = $discount;
                    $detail->subtotal = $subtotal;
                    $detail->save();

                    $product = Product::findOrFail($productId);
                    $baseQty = UnitConverter::toBaseUnit((float) $qty, $units[$i] ?? '', $product);
                    $product->stock -= $baseQty;
                    $product->save();
                }

                // Buat receivable kalau Belum Bayar
                if ($request->payment_status === 'Belum Bayar') {
                    $receivable = new Receivable;
                    $receivable->sale_id = $sale->sale_id;
                    $receivable->customer_id = $sale->customer_id;
                    $receivable->due_date = $request->due_date;
                    $receivable->amount = $totalAmount;
                    $receivable->paid_amount = 0;
                    $receivable->remaining_amount = $totalAmount;
                    $receivable->status = 'Unpaid';
                    $receivable->save();
                }
            });

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

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

    public function printDotMatrix(string $id)
    {
        $sale = Sale::with([
            'customer',
            'details.product',
            'receivable',
        ])->findOrFail($id);

        return view('sales.print-dotmatrix', compact('sale'));
    }

    /**
     * Plain-text nota for dot matrix Draft mode (e.g. Epson LX-310) — no
     * HTML/CSS at all, since Draft mode is a raw character stream and any
     * graphics-mode rendering (what browser printing produces, even for
     * printDotMatrix()'s HTML view) forces the printer into much slower,
     * more ribbon-hungry Letter Quality/graphics mode.
     */
    public function printText(string $id)
    {
        $sale = Sale::with([
            'customer',
            'details.product',
            'receivable',
        ])->findOrFail($id);

        return response($this->buildTextNota($sale), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Builds the fixed-width plain-text nota. 70 columns — intentionally
     * narrower than the printer's nominal 80-column width at 10 cpi, to
     * leave a safety margin for LX dot matrix units whose physical print
     * margins aren't precisely 80 columns.
     */
    private function buildTextNota(Sale $sale): string
    {
        // 70 (not the printer's nominal 80) — leaves a safety margin on both
        // sides for LX dot matrix units whose physical print margins aren't
        // precisely 80 columns, so content doesn't bleed past the
        // continuous-form paper's edge/perforation.
        $width = 70;
        $lines = [];

        $lines[] = str_pad('TOKO SENTOSA', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_pad('Jl. Bengkalis II No.91/93, Gang Buntu, Medan Timur, Medan 20231', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_pad('Telp: 082364740128', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('=', $width);

        $invoiceNo = 'INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT);
        $tanggal = Carbon::parse($sale->sale_date)->format('d/m/Y');

        $lines[] = sprintf('%-40s%s', "No. Nota  : {$invoiceNo}", "Tanggal   : {$tanggal}");
        $lines[] = sprintf(
            '%-40s%s',
            'Pelanggan : '.($sale->customer->customer_name ?? 'Walk-in Customer'),
            'Bayar     : '.$sale->payment_method
        );

        if ($sale->customer?->address) {
            $lines[] = 'Alamat    : '.$sale->customer->address;
        }

        $lines[] = str_repeat('-', $width);

        // Column widths sum to 70 (3+25+5+7+9+5+10 + 6 separating spaces).
        // Nama Produk keeps effectively the same room it had at 80 columns
        // (25 vs. the old 24) — Qty/Harga/Disc/Subtotal are trimmed instead,
        // since those rarely need more than a handful of digits.
        $itemFormat = '%-3s %-25s %5s %-7s %9s %5s %10s';
        $lines[] = sprintf($itemFormat, 'No', 'Nama Produk', 'Qty', 'Satuan', 'Harga', 'Disc', 'Subtotal');
        $lines[] = str_repeat('-', $width);

        foreach ($sale->details as $i => $detail) {
            $productName = $detail->product->product_name ?? '-';
            $unit = $detail->unit ?: $detail->product->sale_unit;

            $lines[] = sprintf(
                $itemFormat,
                (string) ($i + 1),
                substr($productName, 0, 25),
                (string) $detail->quantity,
                substr((string) $unit, 0, 7),
                number_format((float) $detail->price, 0, ',', '.'),
                $detail->discount.'%',
                number_format((float) $detail->subtotal, 0, ',', '.')
            );
        }

        $lines[] = str_repeat('-', $width);

        $totalFormat = '%-55s%15s';
        $lines[] = sprintf($totalFormat, 'TOTAL KESELURUHAN', 'Rp '.number_format((float) $sale->total_amount, 0, ',', '.'));

        if ($sale->payment_status === 'Belum Bayar' && $sale->receivable) {
            $lines[] = sprintf($totalFormat, 'SUDAH DIBAYAR', 'Rp '.number_format((float) $sale->receivable->paid_amount, 0, ',', '.'));
            $lines[] = sprintf($totalFormat, 'SISA TAGIHAN', 'Rp '.number_format((float) $sale->receivable->remaining_amount, 0, ',', '.'));
        }

        $statusLine = 'Status     : '.$sale->payment_status;
        if ($sale->payment_status === 'Belum Bayar' && $sale->receivable?->due_date) {
            $statusLine .= '          Jatuh Tempo: '.Carbon::parse($sale->receivable->due_date)->format('d/m/Y');
        }
        $lines[] = $statusLine;

        if ($sale->notes) {
            $lines[] = 'Catatan: '.$sale->notes;
        }

        // One blank line separating the totals/status block from the
        // signature block, and one more before the dotted signature line
        // itself — enough room to physically sign on continuous-form paper
        // without wasting a full blank half-page per nota.
        $lines[] = '';
        $lines[] = sprintf('%-40s%s', str_pad('Penerima', 20, ' ', STR_PAD_BOTH), str_pad('Hormat Kami', 20, ' ', STR_PAD_BOTH));
        $lines[] = '';
        $lines[] = sprintf(
            '%-40s%s',
            str_pad('......................', 30, ' ', STR_PAD_BOTH),
            str_pad('......................', 30, ' ', STR_PAD_BOTH)
        );
        $lines[] = sprintf(
            '%-40s%s',
            str_pad($sale->customer->customer_name ?? 'Walk-in Customer', 30, ' ', STR_PAD_BOTH),
            str_pad('Toko Sentosa', 30, ' ', STR_PAD_BOTH)
        );

        return implode("\n", $lines)."\n";
    }

    public function edit(string $id)
    {
        $sale = Sale::with('details.product', 'receivable')->findOrFail($id);
        $customers = Customer::all();
        $products = Product::all();

        return view('sales.edit', compact('sale', 'customers', 'products'));
    }

    public function update(Request $request, string $id)
    {
        $sale = Sale::with('details', 'receivable')->findOrFail($id);

        $request->validate([
            'sale_date' => 'required|date',
            'payment_method' => 'required|in:Cash,Transfer,Utang',
            'payment_status' => 'required|in:Lunas,Belum Bayar',
            'notes' => 'nullable|string',
            'product_id' => 'required|array|min:1',
            'product_id.*' => 'required|exists:products,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|numeric|min:0.25',
            'price' => 'required|array',
            'price.*' => 'required|numeric|min:0',
            'discount' => 'required|array',
            'discount.*' => 'required|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
        ]);

        try {
            DB::transaction(function () use ($request, $sale) {

                // Kembalikan stok lama (dikonversi ke sale_unit)
                foreach ($sale->details as $oldDetail) {
                    $product = Product::findOrFail($oldDetail->product_id);
                    $baseQty = UnitConverter::toBaseUnit((float) $oldDetail->quantity, $oldDetail->unit ?? '', $product);
                    $product->stock += $baseQty;
                    $product->save();
                }

                $sale->details()->delete();

                $productIds = $request->product_id;
                $quantities = $request->quantity;
                $prices = $request->price;
                $discounts = $request->discount;
                $units = $request->unit;

                // Cek stok baru (dikonversi ke sale_unit)
                foreach ($productIds as $i => $productId) {
                    $product = Product::findOrFail($productId);
                    $baseQty = UnitConverter::toBaseUnit((float) $quantities[$i], $units[$i] ?? '', $product);
                    if ($product->stock < $baseQty) {
                        throw new \Exception("Stok {$product->product_name} tidak mencukupi. Stok tersedia: {$product->stok_display}.");
                    }
                }

                $totalAmount = 0;

                foreach ($productIds as $i => $productId) {
                    $qty = $quantities[$i];
                    $price = $prices[$i];
                    $discount = $discounts[$i] ?? 0;
                    $subtotal = $qty * $price * (1 - $discount / 100);
                    $totalAmount += $subtotal;

                    $detail = new SaleDetail;
                    $detail->sale_id = $sale->sale_id;
                    $detail->product_id = $productId;
                    $detail->quantity = $qty;
                    $detail->unit = $units[$i] ?? '';
                    $detail->price = $price;
                    $detail->discount = $discount;
                    $detail->subtotal = $subtotal;
                    $detail->save();

                    $product = Product::findOrFail($productId);
                    $baseQty = UnitConverter::toBaseUnit((float) $qty, $units[$i] ?? '', $product);
                    $product->stock -= $baseQty;
                    $product->save();
                }

                // Update header
                $sale->customer_id = $request->customer_id ?: null;
                $sale->sale_date = $request->sale_date;
                $sale->payment_method = $request->payment_method;
                $sale->payment_status = $request->payment_status;
                $sale->paid_date = $request->payment_status === 'Lunas' ? now()->toDateString() : null;
                $sale->total_amount = $totalAmount;
                $sale->notes = $request->notes;
                $sale->save();

                // Update receivable
                if ($request->payment_status === 'Belum Bayar') {
                    $receivable = $sale->receivable ?? new Receivable;
                    $receivable->sale_id = $sale->sale_id;
                    $receivable->customer_id = $sale->customer_id;
                    $receivable->due_date = $request->due_date;
                    $receivable->amount = $totalAmount;
                    $receivable->status = 'Unpaid';
                    $receivable->save();
                } else {
                    if ($sale->receivable) {
                        $sale->receivable->status = 'Paid';
                        $sale->receivable->save();
                    }
                }
            });

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sales.show', $sale->sale_id)
            ->with('success', 'Transaksi penjualan berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $sale = Sale::with('details', 'receivable')->findOrFail($id);

        DB::transaction(function () use ($sale) {
            // Kembalikan stok (dikonversi ke sale_unit)
            foreach ($sale->details as $detail) {
                $product = Product::findOrFail($detail->product_id);
                $baseQty = UnitConverter::toBaseUnit((float) $detail->quantity, $detail->unit ?? '', $product);
                $product->stock += $baseQty;
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

    public function tandaiDiproses(string $id)
    {
        $sale = Sale::with('details')->findOrFail($id);

        try {
            DB::transaction(function () use ($sale) {
                foreach ($sale->details as $detail) {
                    $product = Product::findOrFail($detail->product_id);
                    $baseQty = UnitConverter::toBaseUnit((float) $detail->quantity, $detail->unit ?? '', $product);

                    if ($product->stock < $baseQty) {
                        throw new \Exception("Stok {$product->product_name} tidak mencukupi untuk memproses pesanan ini. Stok tersedia: {$product->stok_display}.");
                    }

                    $product->stock -= $baseQty;
                    $product->save();
                }

                $sale->is_new = false;
                $sale->save();
            });
        } catch (\Exception $e) {
            return redirect()
                ->route('dashboard')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Pesanan INV-'.str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT).' ditandai sudah diproses.');
    }
}
