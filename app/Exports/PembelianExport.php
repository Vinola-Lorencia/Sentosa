<?php

namespace App\Exports;

use App\Models\Purchase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class PembelianExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return Purchase::with('supplier')
            ->whereBetween('purchase_date', [$this->startDate, $this->endDate])
            ->orderBy('purchase_date', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No Pembelian',
            'Tanggal',
            'Supplier',
            'No PO',
            'Total',
        ];
    }

    public function map($purchase): array
    {
        return [
            str_pad($purchase->purchase_id, 3, '0', STR_PAD_LEFT),
            Carbon::parse($purchase->purchase_date)->format('d/m/Y'),
            $purchase->supplier->supplier_name ?? '-',
            $purchase->po_id ? 'PO-' . str_pad($purchase->po_id, 3, '0', STR_PAD_LEFT) : '-',
            $purchase->total_amount,
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
        return 'Pembelian';
    }
}