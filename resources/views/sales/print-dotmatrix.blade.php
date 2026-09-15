<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }} — Toko Sentosa</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0.25in 0.3in;
        }

        table { width: 100%; border-collapse: collapse; }

        th, td { padding: 2px 4px; text-align: left; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .header { text-align: center; margin-bottom: 8px; }
        .header h1 { font-size: 14px; margin: 0; letter-spacing: 1px; }
        .header p { margin: 0; font-size: 10px; }

        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .divider-solid { border-top: 1px solid #000; margin: 6px 0; }

        .meta-row { display: flex; justify-content: space-between; margin-bottom: 2px; }

        .items-table th { border-bottom: 1px solid #000; font-weight: bold; }
        .items-table td { vertical-align: top; }

        .totals { margin-top: 6px; }
        .totals .text-right { font-weight: bold; }

        .signatures { margin-top: 30px; display: flex; justify-content: space-between; }
        .signatures div { width: 45%; text-align: center; }
        .signatures .line { margin-top: 40px; border-top: 1px solid #000; padding-top: 2px; }

        .no-print { text-align: center; margin-bottom: 12px; }
        .no-print button {
            font-family: inherit;
            font-size: 12px;
            padding: 6px 16px;
            cursor: pointer;
        }

        @media print {
            .no-print { display: none !important; }

            @page {
                size: 9.5in 11in;
                margin: 0.4in 0.3in;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button type="button" onclick="window.print()">Cetak Nota</button>
    </div>

    <div class="header">
        <h1>TOKO SENTOSA</h1>
        <p>Jl. Bengkalis II No.91/93, Gang Buntu</p>
        <p>Medan Timur, Medan 20231</p>
        <p>Telp: 082364740128</p>
    </div>

    <div class="divider-solid"></div>

    <div class="meta-row">
        <span>No. Nota&nbsp;: INV-{{ str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT) }}</span>
        <span>Tanggal&nbsp;: {{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') }}</span>
    </div>
    <div class="meta-row">
        <span>Pelanggan&nbsp;: {{ $sale->customer->customer_name ?? 'Walk-in Customer' }}</span>
        <span>Bayar&nbsp;: {{ $sale->payment_method }}</span>
    </div>
    @if($sale->customer?->address)
        <div class="meta-row">
            <span>Alamat&nbsp;: {{ $sale->customer->address }}</span>
        </div>
    @endif

    <div class="divider"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 4%;">No</th>
                <th style="width: 34%;">Nama Produk</th>
                <th class="text-center" style="width: 8%;">Qty</th>
                <th class="text-center" style="width: 10%;">Satuan</th>
                <th class="text-right" style="width: 14%;">Harga</th>
                <th class="text-right" style="width: 8%;">Disc</th>
                <th class="text-right" style="width: 22%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->details as $i => $detail)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $detail->product->product_name ?? '-' }}</td>
                    <td class="text-center">{{ $detail->quantity }}</td>
                    <td class="text-center">{{ $detail->unit ?: $detail->product->sale_unit }}</td>
                    <td class="text-right">{{ number_format($detail->price, 0, ',', '.') }}</td>
                    <td class="text-right">{{ $detail->discount }}%</td>
                    <td class="text-right">{{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="totals">
        <table>
            <tr>
                <td class="text-right" style="width: 78%;">TOTAL KESELURUHAN</td>
                <td class="text-right" style="width: 22%;">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
            </tr>
            @if($sale->payment_status === 'Belum Bayar' && $sale->receivable)
                <tr>
                    <td class="text-right">SUDAH DIBAYAR</td>
                    <td class="text-right">Rp {{ number_format($sale->receivable->paid_amount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-right">SISA TAGIHAN</td>
                    <td class="text-right">Rp {{ number_format($sale->receivable->remaining_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="meta-row" style="margin-top: 6px;">
        <span>Status&nbsp;: {{ $sale->payment_status }}</span>
        @if($sale->payment_status === 'Belum Bayar' && $sale->receivable?->due_date)
            <span>Jatuh Tempo&nbsp;: {{ \Carbon\Carbon::parse($sale->receivable->due_date)->format('d/m/Y') }}</span>
        @endif
    </div>

    @if($sale->notes)
        <div class="divider"></div>
        <p>Catatan: {{ $sale->notes }}</p>
    @endif

    <div class="signatures">
        <div>
            <p>Penerima</p>
            <div class="line">{{ $sale->customer->customer_name ?? 'Walk-in Customer' }}</div>
        </div>
        <div>
            <p>Hormat Kami</p>
            <div class="line">Toko Sentosa</div>
        </div>
    </div>

</body>
</html>
