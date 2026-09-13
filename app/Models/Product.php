<?php

namespace App\Models;

use App\Services\UnitConverter;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * A product counts as "new" (storefront badge + "Baru" ribbon) for this
     * many days after creation — a single constant so the window is easy to
     * tune without hunting through query call sites.
     */
    public const NEW_PRODUCT_DAYS = 7;

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
        'updated_by',
    ];

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'product_id', 'id');
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class, 'product_id', 'id');
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'product_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who last touched this product via ProductController::updatePhoto() —
     * only that endpoint fills updated_by, so this specifically means "who
     * last changed the photo", not "who last edited the product" in general
     * (the full edit form does not set it).
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function views()
    {
        return $this->hasMany(ProductView::class, 'product_id', 'id');
    }

    /**
     * Products created within the last NEW_PRODUCT_DAYS days.
     */
    public function scopeNewProducts($query)
    {
        return $query->where('created_at', '>=', now()->subDays(self::NEW_PRODUCT_DAYS));
    }

    /**
     * Products this specific user has not yet marked viewed (via
     * ProductView) — "unseen" is tracked per user per product, not per
     * visit, so browsing the catalog list never clears it on its own.
     */
    public function scopeUnseenBy($query, int $userId)
    {
        return $query->whereDoesntHave('views', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
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