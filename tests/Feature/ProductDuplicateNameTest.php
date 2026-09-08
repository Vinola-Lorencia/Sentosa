<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductDuplicateNameTest extends TestCase
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

    public function test_store_rejects_duplicate_product_name_with_custom_message()
    {
        Product::create([
            'product_name' => 'GENTONG 6688 HITAM', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
        ]);

        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'GENTONG 6688 HITAM',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertSessionHasErrors([
            'product_name' => 'Produk dengan nama ini sudah terdaftar. Periksa kembali sebelum menambahkan.',
        ]);
    }

    public function test_store_allows_unique_product_name()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'Produk Baru Unik',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_check_product_name_endpoint_finds_partial_match()
    {
        Product::create([
            'product_name' => 'GENTONG 6688 HITAM', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/check-product-name?name=gentong');

        $response->assertStatus(200);
        $response->assertJson([
            'found'   => true,
            'matches' => ['GENTONG 6688 HITAM'],
        ]);
    }

    public function test_check_product_name_endpoint_returns_not_found_for_no_match()
    {
        $response = $this->actingAs($this->user)->getJson('/api/check-product-name?name=nonexistent');

        $response->assertStatus(200);
        $response->assertJson(['found' => false, 'matches' => []]);
    }

    public function test_check_product_name_endpoint_limits_to_three_matches()
    {
        foreach (['Sapu A', 'Sapu B', 'Sapu C', 'Sapu D'] as $name) {
            Product::create([
                'product_name' => $name, 'sale_price' => 1000, 'sale_unit' => 'Buah',
                'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
                'default_buy_price' => 500, 'default_buy_discount' => 0,
            ]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/check-product-name?name=sapu');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'matches');
    }

    public function test_check_product_name_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/check-product-name?name=sapu');

        $response->assertStatus(401);
    }

    public function test_check_product_name_endpoint_blank_query_returns_not_found()
    {
        Product::create([
            'product_name' => 'GENTONG 6688 HITAM', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/check-product-name?name=');

        $response->assertStatus(200);
        $response->assertJson(['found' => false, 'matches' => []]);
    }
}
