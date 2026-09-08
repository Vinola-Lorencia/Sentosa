<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductOptionalFieldsTest extends TestCase
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

    public function test_can_create_product_with_only_required_fields()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'Minimal Product',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionDoesntHaveErrors();

        $product = Product::where('product_name', 'Minimal Product')->first();

        $this->assertNotNull($product);
        $this->assertEquals(10, (float) $product->stock);
        $this->assertNull($product->brand);
        $this->assertEquals(0, (float) $product->default_buy_price);
        $this->assertEquals(0, (float) $product->default_buy_discount);
        $this->assertEquals(0, (float) $product->sale_price);
        $this->assertEquals('Buah', $product->sale_unit);
        $this->assertEquals('Goni', $product->purchase_unit);
        $this->assertEquals(1, (float) $product->dus_to_unit);
        $this->assertNotNull($product->image_path);
    }

    public function test_missing_product_name_fails_validation()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'stock_qty'  => 10,
            'stock_unit' => 'Buah',
            'image'      => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertSessionHasErrors('product_name');
    }

    public function test_missing_stock_qty_fails_validation()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'No Stock Product',
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertSessionHasErrors('stock_qty');
    }

    public function test_missing_stock_unit_fails_validation()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'No Stock Unit Product',
            'stock_qty'    => 10,
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertSessionHasErrors('stock_unit');
    }

    public function test_missing_image_fails_validation()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'No Image Product',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
        ]);

        $response->assertSessionHasErrors('image');
    }

    public function test_can_still_provide_all_optional_fields_explicitly()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name'         => 'Full Product',
            'stock_qty'            => 5,
            'stock_unit'           => 'Buah',
            'image'                => UploadedFile::fake()->image('produk.jpg'),
            'brand'                => 'GM',
            'default_buy_price'    => 90000,
            'default_buy_discount' => 7,
            'sale_price'           => 120000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::where('product_name', 'Full Product')->first();
        $this->assertEquals('GM', $product->brand);
        $this->assertEquals(90000, (float) $product->default_buy_price);
        $this->assertEquals(7, (float) $product->default_buy_discount);
        $this->assertEquals(120000, (float) $product->sale_price);
        $this->assertEquals('Lusin', $product->sale_unit);
        $this->assertEquals('Dus', $product->purchase_unit);
        $this->assertEquals(12, (float) $product->dus_to_unit);
    }
}
