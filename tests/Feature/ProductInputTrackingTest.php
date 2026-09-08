<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductInputTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;
    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $pemilikRole  = Role::create(['role_name' => 'Pemilik']);
        $karyawanRole = Role::create(['role_name' => 'Karyawan']);

        $this->pemilik = User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('password'), 'role_id' => $pemilikRole->id,
        ]);

        $this->karyawan = User::create([
            'name' => 'Budi', 'email' => 'budi@test.com',
            'password' => bcrypt('password'), 'role_id' => $karyawanRole->id,
        ]);
    }

    public function test_product_created_by_karyawan_records_created_by()
    {
        $this->actingAs($this->karyawan)->post(route('products.store'), [
            'product_name' => 'Produk Karyawan',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $product = Product::where('product_name', 'Produk Karyawan')->firstOrFail();

        $this->assertEquals($this->karyawan->id, $product->created_by);
        $this->assertEquals('Budi', $product->creator->name);
    }

    public function test_dashboard_shows_karyawan_widget_and_produk_terbaru()
    {
        $this->actingAs($this->karyawan)->post(route('products.store'), [
            'product_name' => 'Produk Terbaru Test',
            'stock_qty'    => 5,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response = $this->actingAs($this->pemilik)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Input Produk Karyawan');
        $response->assertSee('Budi');
        $response->assertSee('1 produk'); // this month's count for Budi
        $response->assertSee('Produk Terbaru');
        $response->assertSee('Produk Terbaru Test');
    }

    public function test_produk_terbaru_month_filter_excludes_old_products()
    {
        $old = Product::create([
            'product_name' => 'Produk Lama', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'created_by' => $this->karyawan->id,
        ]);
        $old->created_at = now()->subMonths(2);
        $old->save();

        $response = $this->actingAs($this->pemilik)->get(route('dashboard', ['produk_filter' => 'month']));

        $response->assertStatus(200);
        // "Produk Lama" legitimately still appears once, in the Input Produk
        // Karyawan widget's unfiltered "most recent product" — but the
        // Produk Terbaru table (which IS filtered) must correctly exclude it.
        $response->assertSee('Tidak ada produk pada periode ini.');
    }
}
