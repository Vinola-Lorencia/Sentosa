<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitConversionStockBugTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pemilik']);
        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
    }

    private function makeProduct(string $saleUnit, float $stock, float $dusToUnit = 1): Product
    {
        return Product::create([
            'product_name'         => 'KURSI MAKAN 01',
            'brand'                => 'GM',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 120000,
            'sale_unit'            => $saleUnit,
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => $dusToUnit,
            'stock'                => $stock,
        ]);
    }

    private function sell(Product $product, float $qty, string $unit): void
    {
        $this->actingAs($this->user)->post(route('sales.store'), [
            'sale_date'      => now()->format('Y-m-d'),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'product_id'     => [$product->id],
            'quantity'       => [$qty],
            'price'          => [120000],
            'discount'       => [0],
            'unit'           => [$unit],
        ]);
    }

    public function test_sell_1_buah_from_lusin_product_deducts_exactly_1()
    {
        $product = $this->makeProduct('Lusin', 50);
        $this->sell($product, 1, 'Buah');
        $this->assertEquals(49.0, (float) $product->fresh()->stock);
    }

    public function test_sell_1_lusin_from_lusin_product_deducts_12()
    {
        $product = $this->makeProduct('Lusin', 50);
        $this->sell($product, 1, 'Lusin');
        $this->assertEquals(38.0, (float) $product->fresh()->stock);
    }

    public function test_sell_1_kodi_from_lusin_product_deducts_20()
    {
        $product = $this->makeProduct('Lusin', 50);
        $this->sell($product, 1, 'Kodi');
        $this->assertEquals(30.0, (float) $product->fresh()->stock);
    }

    public function test_sell_1_buah_from_buah_product_deducts_1()
    {
        $product = $this->makeProduct('Buah', 50);
        $this->sell($product, 1, 'Buah');
        $this->assertEquals(49.0, (float) $product->fresh()->stock);
    }

    // dus_to_unit is "sale_units per packaging unit", not "buah per packaging
    // unit" — these verify the Goni/Dus/Karton branch converts through the
    // sale_unit's own base-unit factor instead of taking dus_to_unit as buah.

    public function test_sell_1_goni_from_lusin_product_with_dus_to_unit_2_deducts_24()
    {
        $product = $this->makeProduct('Lusin', 100, dusToUnit: 2);
        $this->sell($product, 1, 'Goni');
        $this->assertEquals(76.0, (float) $product->fresh()->stock);
    }

    public function test_sell_1_dus_from_buah_product_with_dus_to_unit_5_deducts_5()
    {
        $product = $this->makeProduct('Buah', 100, dusToUnit: 5);
        $this->sell($product, 1, 'Dus');
        $this->assertEquals(95.0, (float) $product->fresh()->stock);
    }

    public function test_sell_1_karton_from_kodi_product_with_dus_to_unit_3_deducts_60()
    {
        $product = $this->makeProduct('Kodi', 100, dusToUnit: 3);
        $this->sell($product, 1, 'Karton');
        $this->assertEquals(40.0, (float) $product->fresh()->stock);
    }
}
