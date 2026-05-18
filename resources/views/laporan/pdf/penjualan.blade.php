<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Penjualan</title>
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
        .badge { padding: 2px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #999; }
    </style>
</head>
<body>

    <div class="header">
        <h1>TOKO SENTOSA</h1>
        <p>Jl. Bengkalis II No.91/93, Gang Buntu, Medan Timur, Medan 20231 | 082364740128</p>
        <h2>LAPORAN PENJUALAN</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <th>Total Transaksi</th>
                <th>Total Penjualan</th>
                <th>Rata-rata per Transaksi</th>
                <th>Dicetak</th>
            </tr>
            <tr>
                <td>{{ $sales->count() }} transaksi</td>
                <td>Rp {{ number_format($total, 0, ',', '.') }}</td>
                <td>Rp {{ $sales->count() > 0 ? number_format($total / $sales->count(), 0, ',', '.') : 0 }}</td>
                <td>{{ now()->format('d F Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>No Invoice</th>
                <th>Tanggal</th>
                <th>Customer</th>
                <th>Metode</th>
                <th>Status</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td>INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') }}</td>
                    <td>{{ $sale->customer->customer_name ?? 'Walk-in' }}</td>
                    <td>{{ $sale->payment_method }}</td>
                    <td>
                        @if($sale->payment_status === 'Lunas')
                            <span class="badge badge-green">Lunas</span>
                        @else
                            <span class="badge badge-red">Belum Lunas</span>
                        @endif
                    </td>
                    <td class="text-right">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data penjualan pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="5" class="text-right">Grand Total</td>
                <td class="text-right">Rp {{ number_format($total, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Dicetak oleh sistem pada {{ now()->format('d F Y H:i:s') }}
    </div>

</body>
</html>