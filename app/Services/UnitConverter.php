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
     * Convert a quantity expressed in an arbitrary unit into the product's sale_unit.
     */
    public static function toBaseUnit(float $qty, string $unit, Product $product): float
    {
        $unitLower     = strtolower(trim($unit));
        $saleUnitLower = strtolower(trim((string) ($product->sale_unit ?? '')));

        if ($unitLower === $saleUnitLower || $saleUnitLower === '') {
            return $qty;
        }

        $unitFactor = self::factorFor($unitLower, $product);
        $baseFactor = self::factorFor($saleUnitLower, $product);

        return ($qty * $unitFactor) / $baseFactor;
    }

    /**
     * Convert a quantity expressed in the product's sale_unit into an arbitrary target unit.
     */
    public static function fromBaseUnit(float $baseQty, string $targetUnit, Product $product): float
    {
        $targetLower   = strtolower(trim($targetUnit));
        $saleUnitLower = strtolower(trim((string) ($product->sale_unit ?? '')));

        if ($targetLower === $saleUnitLower || $saleUnitLower === '') {
            return $baseQty;
        }

        $baseFactor   = self::factorFor($saleUnitLower, $product);
        $targetFactor = self::factorFor($targetLower, $product);

        return ($baseQty * $baseFactor) / $targetFactor;
    }

    private static function factorFor(string $unitLower, Product $product): float
    {
        if (in_array($unitLower, self::NON_STANDARD_UNITS, true)) {
            return (float) $product->dus_to_unit;
        }

        return (float) (self::UNIT_TO_BASE[$unitLower] ?? 1);
    }
}
