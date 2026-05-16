<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Sale;

use Illuminate\Http\Request;

class PengirimanController extends Controller
{
    public function index()
    {
        $pengiriman = Delivery::with('sale.customer')
            ->latest('delivery_id')
            ->paginate(10);

        return view('pengiriman.index', compact('pengiriman'));
    }

    public function create()
    {
        $sales = Sale::with('customer')
            ->latest('sale_id')
            ->get();

        return view('pengiriman.create', compact('sales'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id'         => 'required|exists:sales,sale_id',
            'delivery_date'   => 'required|date',
            'type'            => 'required|in:Ambil Sendiri,Dikirim',
            'status'          => 'required|in:Pending,Dikirim',
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'address'         => 'nullable|string',
            'expedition'      => 'nullable|string|max:255',
            'shipping_cost'   => 'nullable|numeric|min:0',
            'colly'           => 'required|integer|min:1',
            'notes'           => 'nullable|string',
        ]);

        $delivery = new Delivery();
        $delivery->sale_id         = $request->sale_id;
        $delivery->delivery_date   = $request->delivery_date;
        $delivery->type            = $request->type;
        $delivery->status          = $request->status;
        $delivery->recipient_name  = $request->recipient_name;
        $delivery->recipient_phone = $request->recipient_phone;
        $delivery->address         = $request->address;
        $delivery->expedition      = $request->expedition;
        $delivery->shipping_cost   = $request->shipping_cost ?? 0;
        $delivery->colly           = $request->colly;
        $delivery->notes           = $request->notes;
        $delivery->save();

        return redirect()
            ->route('pengiriman.show', $delivery->delivery_id)
            ->with('success', 'Data pengiriman berhasil disimpan.');
    }

    public function show(string $id)
    {
        $pengiriman = Delivery::with([
            'sale.customer',
            'sale.details.product',
        ])->findOrFail($id);

        return view('pengiriman.show', compact('pengiriman'));
    }

    public function edit(string $id)
    {
        $pengiriman = Delivery::with('sale.customer')->findOrFail($id);
        $sales      = Sale::with('customer')->latest('sale_id')->get();

        return view('pengiriman.edit', compact('pengiriman', 'sales'));
    }

    public function update(Request $request, string $id)
    {
        $pengiriman = Delivery::findOrFail($id);

        $request->validate([
            'delivery_date'   => 'required|date',
            'type'            => 'required|in:Ambil Sendiri,Dikirim',
            'status'          => 'required|in:Pending,Dikirim',
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'address'         => 'nullable|string',
            'expedition'      => 'nullable|string|max:255',
            'shipping_cost'   => 'nullable|numeric|min:0',
            'colly'           => 'required|integer|min:1',
            'notes'           => 'nullable|string',
        ]);

        $pengiriman->delivery_date   = $request->delivery_date;
        $pengiriman->type            = $request->type;
        $pengiriman->status          = $request->status;
        $pengiriman->recipient_name  = $request->recipient_name;
        $pengiriman->recipient_phone = $request->recipient_phone;
        $pengiriman->address         = $request->address;
        $pengiriman->expedition      = $request->expedition;
        $pengiriman->shipping_cost   = $request->shipping_cost ?? 0;
        $pengiriman->colly           = $request->colly;
        $pengiriman->notes           = $request->notes;
        $pengiriman->save();

        return redirect()
            ->route('pengiriman.show', $pengiriman->delivery_id)
            ->with('success', 'Data pengiriman berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $pengiriman = Delivery::findOrFail($id);
        $pengiriman->delete();

        return redirect()
            ->route('pengiriman.index')
            ->with('success', 'Data pengiriman berhasil dihapus.');
    }
}