<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

        // Grafik 6 bulan — satu query menggantikan 6 query whereMonth/whereYear
        // terpisah, supaya jumlah round-trip ke DB tidak ikut membengkak
        // (tiap round-trip ke Supabase yang di-host remote punya latensi
        // jaringan tersendiri). Pengelompokan per bulan dilakukan di PHP
        // (bukan lewat fungsi SQL seperti TO_CHAR) supaya query ini tetap
        // portable lintas driver DB (Postgres di produksi, SQLite di test).
        $enamBulanLalu = Carbon::now()->subMonths(5)->startOfMonth();
        $penjualanPerBulan = Sale::where('sale_date', '>=', $enamBulanLalu)
            ->get(['sale_date', 'total_amount'])
            ->groupBy(fn ($sale) => Carbon::parse($sale->sale_date)->format('Y-m'))
            ->map(fn ($group) => $group->sum('total_amount'));

        $grafikPenjualan = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = Carbon::now()->subMonths($i);

            $grafikPenjualan[] = [
                'bulan' => $bulan->format('M Y'),
                'total' => (float) ($penjualanPerBulan[$bulan->format('Y-m')] ?? 0),
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
        // "jumlah_bulan_ini"/"produk_terakhir" tetap murni soal produk BARU
        // (created_by) — tidak diubah, supaya tampilan lama tidak berubah
        // arti. Aktivitas ganti foto (termasuk ke produk lama yang bukan
        // mereka buat) dihitung terpisah di foto_bulan_ini/foto_terakhir.
        //
        // Sebelumnya ini melakukan 4 query TERPISAH per karyawan (N+1) —
        // dengan setiap query berarti satu round-trip jaringan ke Supabase,
        // ini salah satu penyebab dashboard timeout. Digantikan dengan 4
        // query ter-agregasi total (bukan per-karyawan), lalu dipetakan
        // kembali per user di PHP.
        $karyawanUsers = User::whereHas('role', function ($q) {
            $q->where('role_name', 'Karyawan');
        })->get();

        $karyawanIds = $karyawanUsers->pluck('id');

        $jumlahBulanIniPerKaryawan = Product::whereIn('created_by', $karyawanIds)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->groupBy('created_by')
            ->selectRaw('created_by, count(*) as jumlah')
            ->pluck('jumlah', 'created_by');

        $fotoBulanIniPerKaryawan = Product::whereIn('updated_by', $karyawanIds)
            ->whereMonth('updated_at', $now->month)
            ->whereYear('updated_at', $now->year)
            ->groupBy('updated_by')
            ->selectRaw('updated_by, count(*) as jumlah')
            ->pluck('jumlah', 'updated_by');

        $produkTerakhirPerKaryawan = Product::whereIn('created_by', $karyawanIds)
            ->latest('created_at')
            ->get()
            ->groupBy('created_by')
            ->map(fn ($produk) => $produk->first());

        $fotoTerakhirPerKaryawan = Product::whereIn('updated_by', $karyawanIds)
            ->latest('updated_at')
            ->get()
            ->groupBy('updated_by')
            ->map(fn ($produk) => $produk->first());

        $inputProdukKaryawan = $karyawanUsers->map(function ($karyawan) use (
            $jumlahBulanIniPerKaryawan,
            $fotoBulanIniPerKaryawan,
            $produkTerakhirPerKaryawan,
            $fotoTerakhirPerKaryawan
        ) {
            return [
                'user' => $karyawan,
                'jumlah_bulan_ini' => (int) ($jumlahBulanIniPerKaryawan[$karyawan->id] ?? 0),
                'produk_terakhir' => $produkTerakhirPerKaryawan[$karyawan->id] ?? null,
                'foto_bulan_ini' => (int) ($fotoBulanIniPerKaryawan[$karyawan->id] ?? 0),
                'foto_terakhir' => $fotoTerakhirPerKaryawan[$karyawan->id] ?? null,
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
