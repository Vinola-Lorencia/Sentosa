<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokoTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pembeli']);
        $this->pembeli = User::create([
            'name'     => 'Pembeli Test',
            'email'    => 'pembeli@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
    }

    public function test_pembeli_can_view_katalog()
    {
        $response = $this->actingAs($this->pembeli)
            ->get(route('toko.index'));

        $response->assertStatus(200);
    }

    public function test_pembeli_can_tambah_keranjang()
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
            'stock'                => 10,
        ]);

        $response = $this->actingAs($this->pembeli)
            ->post(route('toko.keranjang.tambah'), [
                'product_id' => $product->id,
                'quantity'   => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('keranjang');
    }

    public function test_pembeli_can_checkout()
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
            'stock'                => 10,
        ]);

        // Tambah ke keranjang dulu
        $this->actingAs($this->pembeli)
            ->post(route('toko.keranjang.tambah'), [
                'product_id' => $product->id,
                'quantity'   => 1,
            ]);

        // Checkout
        $response = $this->actingAs($this->pembeli)
            ->post(route('toko.checkout.proses'), [
                'nama'           => 'Pembeli Test',
                'alamat'         => 'Jl. Test No. 1',
                'catatan'        => null,
                'payment_method' => 'Cash',
            ]);

        $response->assertRedirect(route('toko.pesanan'));
        $this->assertDatabaseHas('sales', [
            'user_id'    => $this->pembeli->id,
            'is_new'     => true,
        ]);

        // Stok berkurang
        $this->assertEquals(9, $product->fresh()->stock);
    }

    public function test_pemilik_cannot_access_toko()
    {
        $role = Role::create(['role_name' => 'Pemilik']);
        $pemilik = User::create([
            'name'     => 'Pemilik Test',
            'email'    => 'pemilik@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);

        $response = $this->actingAs($pemilik)
            ->get(route('toko.index'));

        $response->assertStatus(403);
    }
}