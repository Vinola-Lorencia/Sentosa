<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductDecimalStockTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'product_name'         => 'GENTONG 6688 HITAM',
            'sale_price'           => 120000,
            'brand'                => 'GM',
            'default_buy_price'    => 100000,
            'default_buy_discount' => 0,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Goni',
            'dus_to_unit'          => 1.5,
            'stock'                => 39.5,
        ], $overrides);
    }

    public function test_can_create_product_with_decimal_stock()
    {
        // products.store now takes stock_qty + stock_unit (converted to Buah
        // server-side) instead of a raw stock value; image is also required.
        $response = $this->actingAs($this->user)->post(
            route('products.store'),
            $this->payload([
                'stock_qty'  => 39.5,
                'stock_unit' => 'Buah',
                'image'      => UploadedFile::fake()->image('produk.jpg'),
            ])
        );

        $response->assertRedirect(route('products.index'));
        $response->assertSessionDoesntHaveErrors();

        $product = Product::latest('id')->first();
        $this->assertEquals(39.5, (float) $product->stock);
    }

    public function test_can_update_product_with_decimal_stock()
    {
        $product = Product::create([
            'product_name'         => 'GENTONG 6688 HITAM',
            'sale_price'           => 120000,
            'brand'                => 'GM',
            'default_buy_price'    => 100000,
            'default_buy_discount' => 0,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Goni',
            'dus_to_unit'          => 1.5,
            'stock'                => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('products.update', $product), $this->payload(['stock' => 38.5]));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(38.5, (float) $product->fresh()->stock);
    }
}
