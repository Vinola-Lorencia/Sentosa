<?php

namespace Tests\Feature\Uat;

use App\Models\Product;
use App\Services\UnitConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A4 — UnitConverter: konversi satuan ↔ buah, harga per satuan, pembulatan Rp1.000.
 */
class A04UnitConverterTest extends TestCase
{
    private function product(string $saleUnit = 'Lusin', float $dusToUnit = 2): Product
    {
        return new Product(['sale_unit' => $saleUnit, 'dus_to_unit' => $dusToUnit]);
    }

    /**
     * @return array<string, array{float, string, float}>
     */
    public static function toBuah(): array
    {
        return [
            '1 Buah' => [1, 'Buah', 1],
            '2 Lusin' => [2, 'Lusin', 24],
            '3 Kodi' => [3, 'Kodi', 60],
            '1 Gross' => [1, 'Gross', 144],
            '0,5 Lusin' => [0.5, 'lusin', 6],
            // Dus/Goni: dus_to_unit = 2 Lusin per kemasan → 24 Buah
            '1 Dus' => [1, 'Dus', 24],
            '2 Goni' => [2, 'Goni', 48],
        ];
    }

    #[DataProvider('toBuah')]
    public function test_to_base_unit(float $qty, string $unit, float $expectedBuah): void
    {
        $this->assertEqualsWithDelta($expectedBuah, UnitConverter::toBaseUnit($qty, $unit, $this->product()), 0.0001);
    }

    public function test_from_base_unit(): void
    {
        $this->assertEqualsWithDelta(2.5, UnitConverter::fromBaseUnit(30, 'Lusin', $this->product()), 0.0001);
        $this->assertEqualsWithDelta(1.5, UnitConverter::fromBaseUnit(30, 'Kodi', $this->product()), 0.0001);
        $this->assertEqualsWithDelta(0.5, UnitConverter::fromBaseUnit(72, 'Gross', $this->product()), 0.0001);
        $this->assertEqualsWithDelta(1.25, UnitConverter::fromBaseUnit(30, 'Dus', $this->product()), 0.0001);
    }

    /**
     * @return array<string, array{float, string, string, float}>
     */
    public static function prices(): array
    {
        return [
            'Lusin → Buah, pas' => [120000, 'Buah', 'Lusin', 10000],
            'Lusin → Buah, dibulatkan naik' => [100000, 'Buah', 'Lusin', 9000], // 8.333 → 9.000
            'Buah → Lusin' => [2500, 'Lusin', 'Buah', 30000],
            'Buah → Kodi, dibulatkan' => [1250, 'Kodi', 'Buah', 25000],
            'Lusin → Gross' => [25100, 'Gross', 'Lusin', 302000], // 301.200 → 302.000
            'satuan sama tidak dibulatkan' => [25500, 'Lusin', 'Lusin', 25500],
            'satuan non-standar tidak diubah' => [50000, 'Dus', 'Lusin', 50000],
        ];
    }

    #[DataProvider('prices')]
    public function test_price_for_unit(float $basePrice, string $chosen, string $saleUnit, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, UnitConverter::priceForUnit($basePrice, $chosen, $saleUnit), 0.001);
    }

    public function test_round_up_to_thousand(): void
    {
        $this->assertSame(26000.0, UnitConverter::roundPriceUpToThousand(25001));
        $this->assertSame(25000.0, UnitConverter::roundPriceUpToThousand(25000));
        $this->assertSame(1000.0, UnitConverter::roundPriceUpToThousand(1));
    }
}
