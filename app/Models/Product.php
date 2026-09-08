<?php

namespace App\Models;

use App\Services\UnitConverter;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_name',
        'brand',
        'image',
        'image_path',
        'default_buy_price',
        'default_buy_discount',
        'sale_price',
        'sale_unit',
        'purchase_unit',
        'dus_to_unit',
        'stock',
        'created_by',
    ];

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'product_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Human-readable stock, e.g. "15 Lusin + 5 Buah (185 Buah total)".
     *
     * The stock column stores raw quantity in base units (Buah). The
     * base-units-per-sale_unit factor comes from UnitConverter::UNIT_TO_BASE
     * (Lusin=12, Kodi=20, Gross=144, ...) rather than a per-product column,
     * since those ratios are fixed/universal — the same table the sales and
     * purchase-order conversion logic already uses.
     */
    public function getStokDisplayAttribute(): string
    {
        $stock    = (float) $this->stock;
        $saleUnit = (string) ($this->sale_unit ?: 'Buah');
        $factor   = UnitConverter::UNIT_TO_BASE[strtolower(trim($saleUnit))] ?? null;

        // Unrecognized unit — no safe conversion factor, show raw stock
        // under its own (actual) unit name rather than mislabeling it.
        if ($factor === null) {
            return $this->formatQty($stock) . ' ' . $saleUnit;
        }

        // Already the base unit (Buah/Pcs/Biji, factor 1) — nothing to convert.
        if ($factor <= 1) {
            return $this->formatQty($stock) . ' Buah';
        }

        $wholeUnits = floor($stock / $factor);
        $remainder  = round($stock - ($wholeUnits * $factor), 2);

        // Rounding the remainder can occasionally push it up to a full
        // $factor (float noise near a boundary, e.g. 23.999999998 -> 24.00
        // remainder before reabsorption) — fold it back into $wholeUnits.
        if ($remainder >= $factor) {
            $wholeUnits++;
            $remainder -= $factor;
        }

        if ($remainder == 0.0) {
            return "{$this->formatQty($wholeUnits)} {$saleUnit} ({$this->formatQty($stock)} buah)";
        }

        return "{$this->formatQty($wholeUnits)} {$saleUnit} + {$this->formatQty($remainder)} buah "
             . "({$this->formatQty($stock)} buah total)";
    }

    private function formatQty(float $qty): string
    {
        return fmod($qty, 1) == 0.0
            ? number_format($qty, 0, ',', '.')
            : rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
    }
}