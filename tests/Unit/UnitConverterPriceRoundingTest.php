<?php

namespace Tests\Unit;

use App\Services\UnitConverter;
use PHPUnit\Framework\TestCase;

/**
 * Covers the single centralized rounding rule (UnitConverter::
 * roundPriceUpToThousand() / priceForUnit()) that every call site — admin
 * Sales form JS, storefront cart — is meant to go through instead of
 * reimplementing the ratio + rounding itself.
 */
class UnitConverterPriceRoundingTest extends TestCase
{
    public function test_round_price_up_to_thousand_examples_from_spec()
    {
        $this->assertEquals(26000, UnitConverter::roundPriceUpToThousand(25833));
        $this->assertEquals(26000, UnitConverter::roundPriceUpToThousand(25100));
        $this->assertEquals(25000, UnitConverter::roundPriceUpToThousand(25000)); // already exact — unchanged
    }

    public function test_round_price_up_to_thousand_never_rounds_down()
    {
        $this->assertEquals(1000, UnitConverter::roundPriceUpToThousand(1));
        $this->assertEquals(1000, UnitConverter::roundPriceUpToThousand(999.99));
        $this->assertEquals(0, UnitConverter::roundPriceUpToThousand(0));
    }

    public function test_price_for_unit_divides_down_and_rounds_up()
    {
        // Rp 100.000 / Lusin -> Rp 8.333,33 / Buah, rounded up to Rp 9.000.
        $price = UnitConverter::priceForUnit(100000, 'Buah', 'Lusin');

        $this->assertEquals(9000, $price);
    }

    public function test_price_for_unit_multiplies_up_and_rounds_up()
    {
        // Rp 100.000 / Lusin (12 buah) -> Kodi (20 buah):
        // 100000 * 20/12 = 166.666,67 -> rounded up to Rp 167.000.
        $price = UnitConverter::priceForUnit(100000, 'Kodi', 'Lusin');

        $this->assertEquals(167000, $price);
    }

    public function test_price_for_unit_returns_original_price_untouched_when_ratio_is_one()
    {
        // Choosing the product's own sale_unit — no real division happens,
        // so a non-round-thousand sale_price is returned completely as-is,
        // not rounded.
        $price = UnitConverter::priceForUnit(15750, 'Lusin', 'Lusin');

        $this->assertEquals(15750, $price);
    }

    public function test_price_for_unit_case_insensitive_and_trims_whitespace()
    {
        $price = UnitConverter::priceForUnit(100000, ' KODI ', ' lusin ');

        $this->assertEquals(167000, $price);
    }

    public function test_price_for_unit_leaves_price_untouched_for_unrecognized_units()
    {
        // Matches the admin Sales form's own guard: Dus/Goni/Karton or any
        // free-typed unit skips conversion entirely and leaves it manual.
        $this->assertEquals(100000, UnitConverter::priceForUnit(100000, 'Dus', 'Lusin'));
        $this->assertEquals(100000, UnitConverter::priceForUnit(100000, 'Lusin', 'Dus'));
    }
}
