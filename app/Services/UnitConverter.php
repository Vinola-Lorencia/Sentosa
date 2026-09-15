<?php

namespace App\Services;

use App\Models\Product;

class UnitConverter
{
    public const UNIT_TO_BASE = [
        'buah'  => 1,
        'pcs'   => 1,
        'biji'  => 1,
        'pak'   => 10,
        'set'   => 6,
        'lusin' => 12,
        'kodi'  => 20,
        'gross' => 144,
    ];

    private const NON_STANDARD_UNITS = ['goni', 'dus', 'karton'];

    /**
     * Recompute a sale_unit-quoted price into the price for a different
     * chosen unit — e.g. a product priced per Lusin, sold to a customer who
     * picked Buah. Rounded UP to the nearest Rp 1.000.
     *
     * This is the single source of truth for "harga per satuan dihitung
     * dari hasil pembagian/perkalian konversi satuan" across the app (admin
     * Sales form, storefront cart) — every call site must go through this
     * instead of reimplementing the ratio + rounding itself, so the rule
     * can never drift between places.
     *
     * Only standard units (see UNIT_TO_BASE) are recognized — matches the
     * admin Sales form's own guard, which leaves the price untouched/manual
     * for anything else (Dus/Goni/Karton, free-typed units).
     *
     * No real conversion happens when $chosenUnit equals $saleUnit (ratio
     * 1:1) — the original price is returned completely untouched in that
     * case, not rounded, since nothing was actually divided/multiplied.
     */
    public static function priceForUnit(float $basePrice, string $chosenUnit, string $saleUnit): float
    {
        $chosenUnit = strtolower(trim($chosenUnit));
        $saleUnit   = strtolower(trim($saleUnit));

        $chosenFactor = self::UNIT_TO_BASE[$chosenUnit] ?? null;
        $saleFactor   = self::UNIT_TO_BASE[$saleUnit] ?? null;

        if ($chosenFactor === null || $saleFactor === null || $chosenFactor === $saleFactor) {
            return $basePrice;
        }

        return self::roundPriceUpToThousand($basePrice * ($chosenFactor / $saleFactor));
    }

    /**
     * Round a price UP to the nearest Rp 1.000 (ceiling, never down):
     * 25.833 -> 26.000, 25.100 -> 26.000, 25.000 -> 25.000 (already exact).
     */
    public static function roundPriceUpToThousand(float $price): float
    {
        return ceil($price / 1000) * 1000;
    }

    /**
     * Convert a quantity expressed in an arbitrary unit into true base units
     * (Buah) — i.e. how many individual pieces this quantity represents.
     * Independent of the product's sale_unit: "1 Kodi" is always 20 pieces,
     * regardless of what unit the product happens to be sold in.
     */
    public static function toBaseUnit(float $qty, string $unit, Product $product): float
    {
        return $qty * self::factorFor(strtolower(trim($unit)), $product);
    }

    /**
     * Convert a quantity expressed in true base units (Buah) into an
     * arbitrary target unit.
     */
    public static function fromBaseUnit(float $baseQty, string $targetUnit, Product $product): float
    {
        $factor = self::factorFor(strtolower(trim($targetUnit)), $product);

        return $factor > 0 ? $baseQty / $factor : $baseQty;
    }

    /**
     * Pieces (Buah) per one of $unitLower.
     *
     * For goni/dus/karton, dus_to_unit is stored as "sale_units per packaging
     * unit" (per PurchaseOrderController::saveDetails()), not "pieces per
     * packaging unit" — e.g. dus_to_unit=2 on a sale_unit=Lusin product means
     * 1 Goni = 2 Lusin = 24 Buah. Convert to true pieces by also multiplying
     * by how many Buah are in one sale_unit.
     */
    private static function factorFor(string $unitLower, Product $product): float
    {
        if (in_array($unitLower, self::NON_STANDARD_UNITS, true)) {
            $saleUnitLower  = strtolower(trim((string) ($product->sale_unit ?? '')));
            $saleUnitFactor = self::UNIT_TO_BASE[$saleUnitLower] ?? 1;

            return (float) $product->dus_to_unit * $saleUnitFactor;
        }

        return (float) (self::UNIT_TO_BASE[$unitLower] ?? 1);
    }
}
