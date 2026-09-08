<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;
    private User $karyawan;
    private User $otherKaryawan;

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

        $this->otherKaryawan = User::create([
            'name' => 'Siti', 'email' => 'siti@test.com',
            'password' => bcrypt('password'), 'role_id' => $karyawanRole->id,
        ]);
    }

    public function test_pemilik_cannot_access_my_products_page()
    {
        $response = $this->actingAs($this->pemilik)->get(route('products.my'));

        $response->assertStatus(403);
    }

    public function test_karyawan_only_sees_own_products_with_correct_stats()
    {
        Product::create([
            'product_name' => 'Punya Budi', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 12,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'created_by' => $this->karyawan->id,
        ]);

        Product::create([
            'product_name' => 'Punya Siti', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 5,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'created_by' => $this->otherKaryawan->id,
        ]);

        $response = $this->actingAs($this->karyawan)->get(route('products.my'));

        $response->assertStatus(200);
        $response->assertSee('Produk yang Saya Tambahkan');
        $response->assertSee('Punya Budi');
        $response->assertDontSee('Punya Siti');
        $response->assertSee('1 produk'); // Total Bulan Ini
        $response->assertSee('Edit');
    }

    public function test_month_filter_excludes_old_products()
    {
        $old = Product::create([
            'product_name' => 'Produk Lama Budi', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'created_by' => $this->karyawan->id,
        ]);
        $old->created_at = now()->subMonths(2);
        $old->save();

        $response = $this->actingAs($this->karyawan)->get(route('products.my', ['filter' => 'month']));

        $response->assertStatus(200);
        $response->assertSee('Tidak ada produk pada periode ini.');
        // Still counted in "Total Keseluruhan" since that stat is unfiltered.
        $response->assertSee('1 produk');
    }

    public function test_menu_item_visible_only_for_karyawan()
    {
        $karyawanView = $this->actingAs($this->karyawan)->get(route('products.index'));
        $karyawanView->assertSee('Produk Saya');

        $pemilikView = $this->actingAs($this->pemilik)->get(route('products.index'));
        $pemilikView->assertDontSee('Produk Saya');
    }
}
