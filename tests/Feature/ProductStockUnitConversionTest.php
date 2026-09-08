<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductStockUnitConversionTest extends TestCase
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

    private function store(string $name, float $qty, string $unit): Product
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => $name,
            'stock_qty'    => $qty,
            'stock_unit'   => $unit,
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionDoesntHaveErrors();

        return Product::where('product_name', $name)->firstOrFail();
    }

    public function test_50_lusin_converts_to_600()
    {
        $this->assertEquals(600.0, (float) $this->store('P Lusin', 50, 'Lusin')->stock);
    }

    public function test_1_kodi_converts_to_20()
    {
        $this->assertEquals(20.0, (float) $this->store('P Kodi', 1, 'Kodi')->stock);
    }

    public function test_5_buah_converts_to_5()
    {
        $this->assertEquals(5.0, (float) $this->store('P Buah', 5, 'Buah')->stock);
    }

    public function test_2_gross_converts_to_288()
    {
        $this->assertEquals(288.0, (float) $this->store('P Gross', 2, 'Gross')->stock);
    }

    public function test_missing_stock_unit_fails_validation()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'No Unit',
            'stock_qty'    => 5,
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertSessionHasErrors('stock_unit');
    }

    public function test_invalid_stock_unit_rejected()
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), [
            'product_name' => 'Bad Unit',
            'stock_qty'    => 5,
            'stock_unit'   => 'Dus',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertSessionHasErrors('stock_unit');
    }
}
