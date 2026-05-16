<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Sale;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        // ─── Summary Cards ────────────────────────────────────────────────

        // Total penjualan bulan ini
        $totalPenjualanBulanIni = Sale::whereMonth('sale_date', $now->month)
            ->whereYear('sale_date', $now->year)
            ->sum('total_amount');

        // Total pembelian bulan ini
        $totalPembelianBulanIni = Purchase::whereMonth('purchase_date', $now->month)
            ->whereYear('purchase_date', $now->year)
            ->sum('total_amount');

        // Total piutang outstanding
        $totalPiutang = Receivable::where('status', 'Unpaid')
            ->sum('remaining_amount');

        // Jumlah produk stok menipis (stok < 10)
        $stokMenipis = Product::where('stock', '<', 10)->count();

        // ─── Grafik Penjualan 6 Bulan ────────────────────────────────────

        $grafikPenjualan = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = Carbon::now()->subMonths($i);
            $total = Sale::whereMonth('sale_date', $bulan->month)
                ->whereYear('sale_date', $bulan->year)
                ->sum('total_amount');

            $grafikPenjualan[] = [
                'bulan' => $bulan->format('M Y'),
                'total' => $total,
            ];
        }

        // ─── Piutang Jatuh Tempo ─────────────────────────────────────────

        $piutangJatuhTempo = Receivable::with('customer', 'sale')
            ->where('status', 'Unpaid')
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get();

        // ─── Transaksi Terakhir ───────────────────────────────────────────

        $transaksiTerakhir = Sale::with('customer')
            ->latest('sale_id')
            ->take(5)
            ->get();

        // ─── Produk Stok Menipis ─────────────────────────────────────────

        $produkStokMenipis = Product::where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->take(8)
            ->get();

        return view('dashboard', compact(
            'totalPenjualanBulanIni',
            'totalPembelianBulanIni',
            'totalPiutang',
            'stokMenipis',
            'grafikPenjualan',
            'piutangJatuhTempo',
            'transaksiTerakhir',
            'produkStokMenipis',
        ));
    }
}