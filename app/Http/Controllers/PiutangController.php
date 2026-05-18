<?php

namespace App\Http\Controllers;

use App\Models\Receivable;
use App\Models\ReceivablePayment;
use Illuminate\Http\Request;

class PiutangController extends Controller
{
    public function index()
    {
        $piutang = Receivable::with(['customer', 'sale'])
            ->latest()
            ->paginate(10);

        return view('piutang.index', compact('piutang'));
    }

    public function show(string $id)
    {
        $piutang = Receivable::with([
            'customer',
            'sale.details.product',
            'payments',
        ])->findOrFail($id);

        return view('piutang.show', compact('piutang'));
    }

    public function bayar(Request $request, string $id)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'notes'        => 'nullable|string',
        ]);

        $piutang = Receivable::findOrFail($id);

        // Pastikan tidak lebih dari sisa
        if ($request->amount > $piutang->remaining_amount) {
            return back()->withErrors(['amount' => 'Jumlah bayar melebihi sisa piutang.']);
        }

        // Simpan pembayaran
        $payment = new ReceivablePayment();
        $payment->receivable_id = $piutang->receivable_id;
        $payment->amount        = $request->amount;
        $payment->payment_date  = $request->payment_date;
        $payment->notes         = $request->notes;
        $payment->save();

        // Update piutang
        $piutang->paid_amount      += $request->amount;
        $piutang->remaining_amount -= $request->amount;

        // Cek apakah lunas
        if ($piutang->remaining_amount <= 0) {
            $piutang->remaining_amount = 0;
            $piutang->status           = 'Paid';

            // Update sale
            $sale = $piutang->sale;
            $sale->payment_status = 'Lunas';
            $sale->paid_date      = $request->payment_date;
            $sale->save();
        }

        $piutang->save();

        return redirect()
            ->route('piutang.show', $piutang->receivable_id)
            ->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function destroy(string $id)
    {
        $piutang = Receivable::findOrFail($id);
        $piutang->delete();

        return redirect()
            ->route('piutang.index')
            ->with('success', 'Data piutang berhasil dihapus.');
    }
}