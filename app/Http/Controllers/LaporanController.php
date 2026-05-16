<?php

namespace App\Http\Controllers;

use App\Exports\PenjualanExport;
use App\Exports\PembelianExport;
use App\Exports\PiutangExport;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Sale;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    // ─── Penjualan ───────────────────────────────────────────────────────────

    public function penjualan(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate   = $request->end_date   ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $sales = Sale::with('customer')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->orderBy('sale_date', 'asc')
            ->get();

        $total = $sales->sum('total_amount');

        return view('laporan.penjualan', compact('sales', 'total', 'startDate', 'endDate'));
    }

    public function exportPenjualanExcel(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate   = $request->end_date   ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $filename = 'laporan-penjualan-' . $startDate . '-sd-' . $endDate . '.xlsx';

        return Excel::download(new PenjualanExport($startDate, $endDate), $filename);
    }

    public function exportPenjualanPdf(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate   = $request->end_date   ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $sales = Sale::with('customer')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->orderBy('sale_date', 'asc')
            ->get();

        $total = $sales->sum('total_amount');

        $pdf = Pdf::loadView('laporan.pdf.penjualan', compact('sales', 'total', 'startDate', 'endDate'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-penjualan-' . $startDate . '-sd-' . $endDate . '.pdf');
    }

    // ─── Pembelian ───────────────────────────────────────────────────────────

    public function pembelian(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate   = $request->end_date   ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $purchases = Purchase::with('supplier')
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->orderBy('purchase_date', 'asc')
            ->get();

        $total = $purchases->sum('total_amount');

        return view('laporan.pembelian', compact('purchases', 'total', 'startDate', 'endDate'));
    }

    public function exportPembelianExcel(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate   = $request->end_date   ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $filename = 'laporan-pembelian-' . $startDate . '-sd-' . $endDate . '.xlsx';

        return Excel::download(new PembelianExport($startDate, $endDate), $filename);
    }

    public function exportPembelianPdf(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate   = $request->end_date   ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $purchases = Purchase::with('supplier')
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->orderBy('purchase_date', 'asc')
            ->get();

        $total = $purchases->sum('total_amount');

        $pdf = Pdf::loadView('laporan.pdf.pembelian', compact('purchases', 'total', 'startDate', 'endDate'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-pembelian-' . $startDate . '-sd-' . $endDate . '.pdf');
    }

    // ─── Piutang ─────────────────────────────────────────────────────────────

    public function piutang(Request $request)
    {
        $status = $request->status ?? null;

        $piutang = Receivable::with('customer', 'sale')
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('due_date', 'asc')
            ->get();

        $totalPiutang  = $piutang->sum('amount');
        $totalDibayar  = $piutang->sum('paid_amount');
        $totalSisa     = $piutang->sum('remaining_amount');

        return view('laporan.piutang', compact('piutang', 'totalPiutang', 'totalDibayar', 'totalSisa', 'status'));
    }

    public function exportPiutangExcel(Request $request)
    {
        $status   = $request->status ?? null;
        $filename = 'laporan-piutang-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new PiutangExport($status), $filename);
    }

    public function exportPiutangPdf(Request $request)
    {
        $status = $request->status ?? null;

        $piutang = Receivable::with('customer', 'sale')
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('due_date', 'asc')
            ->get();

        $totalPiutang = $piutang->sum('amount');
        $totalDibayar = $piutang->sum('paid_amount');
        $totalSisa    = $piutang->sum('remaining_amount');

        $pdf = Pdf::loadView('laporan.pdf.piutang', compact('piutang', 'totalPiutang', 'totalDibayar', 'totalSisa', 'status'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-piutang-' . now()->format('Y-m-d') . '.pdf');
    }
}