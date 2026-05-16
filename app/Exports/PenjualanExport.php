<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class PenjualanExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function collection()
    {
        return Sale::with('customer')
            ->whereBetween('sale_date', [$this->startDate, $this->endDate])
            ->orderBy('sale_date', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No Invoice',
            'Tanggal',
            'Customer',
            'Metode Bayar',
            'Status Bayar',
            'Total',
        ];
    }

    public function map($sale): array
    {
        return [
            'INV-' . str_pad($sale->sale_id, 3, '0', STR_PAD_LEFT),
            Carbon::parse($sale->sale_date)->format('d/m/Y'),
            $sale->customer->customer_name ?? 'Walk-in',
            $sale->payment_method,
            $sale->payment_status,
            $sale->total_amount,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}