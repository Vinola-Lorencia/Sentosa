<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
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

    public function test_can_view_sales_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('sales.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_create_sale_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('sales.create'));

        $response->assertStatus(200);
    }

    public function test_can_create_sale()
    {
        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 24, // 2 dozen in base units (Buah)
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('sales.store'), [
                'sale_date'      => now()->format('Y-m-d'),
                'payment_method' => 'Cash',
                'payment_status' => 'Lunas',
                'notes'          => null,
                'product_id'     => [$product->id],
                'quantity'       => [1],
                'price'          => [15000],
                'discount'       => [0],
                'unit'           => ['Lusin'],
            ]);

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', [
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
        ]);

        // Stok berkurang 12 (1 Lusin = 12 Buah), bukan 1
        $this->assertEquals(12, $product->fresh()->stock);
    }

    public function test_cannot_create_sale_if_stock_insufficient()
    {
        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('sales.store'), [
                'sale_date'      => now()->format('Y-m-d'),
                'payment_method' => 'Cash',
                'payment_status' => 'Lunas',
                'product_id'     => [$product->id],
                'quantity'       => [5], // melebihi stok
                'price'          => [15000],
                'discount'       => [0],
                'unit'           => ['Lusin'],
            ]);

        // Stok tidak berubah
        $this->assertEquals(1, $product->fresh()->stock);
    }

    public function test_can_delete_sale_and_restore_stock()
    {
        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 36, // 3 dozen in base units (Buah)
        ]);

        // Buat sale dulu (2 Lusin = 24 Buah)
        $this->actingAs($this->user)
            ->post(route('sales.store'), [
                'sale_date'      => now()->format('Y-m-d'),
                'payment_method' => 'Cash',
                'payment_status' => 'Lunas',
                'product_id'     => [$product->id],
                'quantity'       => [2],
                'price'          => [15000],
                'discount'       => [0],
                'unit'           => ['Lusin'],
            ]);

        $sale = Sale::latest('sale_id')->first();

        // Hapus sale
        $this->actingAs($this->user)
            ->delete(route('sales.destroy', $sale->sale_id));

        // Stok kembali ke 36
        $this->assertEquals(36, $product->fresh()->stock);
    }
}