<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleLabaTest extends TestCase
{
    use RefreshDatabase;

    public function test_laba_sums_correctly_across_mixed_units()
    {
        $role = Role::create(['role_name' => 'Pemilik']);
        $user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);

        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'sale_price'           => 120000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'default_buy_price'    => 100000, // per Lusin
            'default_buy_discount' => 10,     // -> HPP 90,000/Lusin = 7,500/Buah
            'stock'                => 100,
        ]);

        $sale = Sale::create([
            'user_id'        => $user->id,
            'sale_date'      => now()->format('Y-m-d'),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount'   => 250000,
        ]);

        // Sold 1 Buah at Rp10,000 -> laba = 10,000 - 7,500 = 2,500
        SaleDetail::create([
            'sale_id'    => $sale->sale_id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit'       => 'Buah',
            'price'      => 10000,
            'discount'   => 0,
            'subtotal'   => 10000,
        ]);

        // Sold 2 Lusin at Rp120,000/Lusin -> laba = 240,000 - (7,500*24) = 60,000
        SaleDetail::create([
            'sale_id'    => $sale->sale_id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit'       => 'Lusin',
            'price'      => 120000,
            'discount'   => 0,
            'subtotal'   => 240000,
        ]);

        $sale->load('details.product');

        $this->assertEquals(62500.0, $sale->laba);
    }
}
