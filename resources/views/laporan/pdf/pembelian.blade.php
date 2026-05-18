<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Pembelian</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header h2 { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .header p { font-size: 10px; color: #666; margin-top: 2px; }
        .summary { margin-bottom: 15px; }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 10px; text-align: center; }
        .summary th { padding: 5px 8px; background: #f3f4f6; border: 1px solid #e5e7eb; font-size: 10px; text-align: center; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data thead { background-color: #4f46e5; color: white; }
        table.data th { padding: 7px 8px; text-align: left; font-size: 10px; font-weight: bold; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        table.data tbody tr:nth-child(even) { background-color: #f9fafb; }
        .tfoot-row td { padding: 7px 8px; font-weight: bold; border-top: 2px solid #333; font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #999; }
    </style>
</head>
<body>

    <div class="header">
        <h1>TOKO SENTOSA</h1>
        <p>Jl. Bengkalis II No.91/93, Gang Buntu, Medan Timur, Medan 20231 | 082364740128</p>
        <h2>LAPORAN PEMBELIAN</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <th>Total Transaksi</th>
                <th>Total Pembelian</th>
                <th>Dicetak</th>
            </tr>
            <tr>
                <td>{{ $purchases->count() }} transaksi</td>
                <td>Rp {{ number_format($total, 0, ',', '.') }}</td>
                <td>{{ now()->format('d F Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Supplier</th>
                <th>No PO</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($purchases as $purchase)
                <tr>
                    <td>{{ str_pad($purchase->purchase_id, 3, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') }}</td>
                    <td>{{ $purchase->supplier->supplier_name ?? '-' }}</td>
                    <td>{{ $purchase->po_id ? 'PO-' . str_pad($purchase->po_id, 3, '0', STR_PAD_LEFT) : '-' }}</td>
                    <td class="text-right">Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada data pembelian pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="4" class="text-right">Grand Total</td>
                <td class="text-right">Rp {{ number_format($total, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Dicetak oleh sistem pada {{ now()->format('d F Y H:i:s') }}
    </div>

</body>
</html>