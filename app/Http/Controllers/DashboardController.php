<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\User;

use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();

        // Summary Cards
        $totalPenjualanBulanIni = Sale::whereMonth('sale_date', $now->month)
            ->whereYear('sale_date', $now->year)
            ->sum('total_amount');

        $totalPembelianBulanIni = Purchase::whereMonth('purchase_date', $now->month)
            ->whereYear('purchase_date', $now->year)
            ->sum('total_amount');

        $totalPiutang = Receivable::where('status', 'Unpaid')
            ->sum('remaining_amount');

        $stokMenipis = Product::where('stock', '<', 10)->count();

        // Grafik 6 bulan
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

        // Piutang jatuh tempo
        $piutangJatuhTempo = Receivable::with('customer', 'sale')
            ->where('status', 'Unpaid')
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get();

        // Transaksi terakhir
        $transaksiTerakhir = Sale::with('customer')
            ->latest('sale_id')
            ->take(5)
            ->get();

        // Stok menipis
        $produkStokMenipis = Product::where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->take(8)
            ->get();

        // ✅ Pesanan baru dari pembeli
        $pesananBaru = Sale::where('is_new', true)
            ->with('customer')
            ->latest('sale_id')
            ->get();

        // ✅ Input produk oleh Karyawan — jumlah bulan ini + produk terakhir
        $inputProdukKaryawan = User::whereHas('role', function ($q) {
                $q->where('role_name', 'Karyawan');
            })
            ->get()
            ->map(function ($karyawan) use ($now) {
                return [
                    'user'            => $karyawan,
                    'jumlah_bulan_ini' => Product::where('created_by', $karyawan->id)
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year)
                        ->count(),
                    'produk_terakhir' => Product::where('created_by', $karyawan->id)
                        ->latest('created_at')
                        ->first(),
                ];
            });

        // ✅ Produk terbaru — filter: today / week / month
        $produkFilter = $request->get('produk_filter', 'all');
        $produkTerbaruQuery = Product::with('creator')
            ->whereNotNull('created_at')
            ->latest('created_at');

        if ($produkFilter === 'today') {
            $produkTerbaruQuery->whereDate('created_at', $now->toDateString());
        } elseif ($produkFilter === 'week') {
            $produkTerbaruQuery->whereBetween('created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ]);
        } elseif ($produkFilter === 'month') {
            $produkTerbaruQuery->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year);
        }

        $produkTerbaru = $produkTerbaruQuery->take(10)->get();

        return view('dashboard', compact(
            'totalPenjualanBulanIni',
            'totalPembelianBulanIni',
            'totalPiutang',
            'stokMenipis',
            'grafikPenjualan',
            'piutangJatuhTempo',
            'transaksiTerakhir',
            'produkStokMenipis',
            'pesananBaru',
            'inputProdukKaryawan',
            'produkTerbaru',
            'produkFilter',
        ));
    }
}