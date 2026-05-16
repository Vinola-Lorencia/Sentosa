<?php

namespace App\Exports;

use App\Models\Receivable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class PiutangExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $status;

    public function __construct($status = null)
    {
        $this->status = $status;
    }

    public function collection()
    {
        $query = Receivable::with('customer', 'sale');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->orderBy('due_date', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'No Invoice',
            'Customer',
            'Total Piutang',
            'Sudah Dibayar',
            'Sisa',
            'Jatuh Tempo',
            'Status',
        ];
    }

    public function map($item): array
    {
        return [
            'INV-' . str_pad($item->sale_id, 3, '0', STR_PAD_LEFT),
            $item->customer->customer_name ?? 'Walk-in',
            $item->amount,
            $item->paid_amount,
            $item->remaining_amount,
            $item->due_date ? Carbon::parse($item->due_date)->format('d/m/Y') : '-',
            $item->status === 'Paid' ? 'Lunas' : 'Belum Lunas',
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
        return 'Piutang';
    }
}