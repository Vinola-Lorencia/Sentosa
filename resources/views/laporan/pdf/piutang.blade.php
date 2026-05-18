<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Piutang</title>
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
        .overdue { color: #dc2626; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #999; }
    </style>
</head>
<body>

    <div class="header">
        <h1>TOKO SENTOSA</h1>
        <p>Jl. Bengkalis II No.91/93, Gang Buntu, Medan Timur, Medan 20231 | 082364740128</p>
        <h2>LAPORAN PIUTANG</h2>
        <p>
            Status: {{ $status ? ($status === 'Unpaid' ? 'Belum Lunas' : 'Lunas') : 'Semua' }} |
            Dicetak: {{ now()->format('d F Y H:i') }}
        </p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <th>Total Piutang</th>
                <th>Sudah Dibayar</th>
                <th>Sisa Piutang</th>
                <th>Jumlah Data</th>
            </tr>
            <tr>
                <td>Rp {{ number_format($totalPiutang, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($totalDibayar, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($totalSisa, 0, ',', '.') }}</td>
                <td>{{ $piutang->count() }} data</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>No Invoice</th>
                <th>Customer</th>
                <th class="text-right">Total</th>
                <th class="text-right">Dibayar</th>
                <th class="text-right">Sisa</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($piutang as $item)
                @php
                    $isOverdue = $item->status === 'Unpaid' &&
                        $item->due_date &&
                        \Carbon\Carbon::parse($item->due_date)->isPast();
                @endphp
                <tr>
                    <td>INV-{{ str_pad($item->sale_id, 3, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $item->customer->customer_name ?? 'Walk-in' }}</td>
                    <td class="text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->paid_amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->remaining_amount, 0, ',', '.') }}</td>
                    <td class="{{ $isOverdue ? 'overdue' : '' }}">
                        {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') : '-' }}
                        {{ $isOverdue ? '⚠' : '' }}
                    </td>
                    <td>
                        @if($item->status === 'Paid')
                            <span class="badge badge-green">Lunas</span>
                        @else
                            <span class="badge badge-red">Belum Lunas</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada data piutang.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="2" class="text-right">Grand Total</td>
                <td class="text-right">Rp {{ number_format($totalPiutang, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($totalDibayar, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($totalSisa, 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Dicetak oleh sistem pada {{ now()->format('d F Y H:i:s') }}
    </div>

</body>
</html>