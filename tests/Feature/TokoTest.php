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
            'stock'                => 24,
        ]);

        $response = $this->actingAs($this->pembeli)
            ->post(route('toko.keranjang.tambah'), [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit'       => 'Lusin',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('keranjang');
    }

    public function test_pembeli_can_tambah_keranjang_with_different_unit()
    {
        // Product is sold per Lusin (12 buah); customer orders 1 Kodi (20
        // buah) via the unit dropdown — the cart must store the quantity
        // converted into sale_unit (Lusin) terms: 20 buah / 12 = 1.666... Lusin.
        $product = Product::create([
            'product_name'         => 'Produk Test Kodi',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 24,
        ]);

        $response = $this->actingAs($this->pembeli)
            ->post(route('toko.keranjang.tambah'), [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit'       => 'Kodi',
            ]);

        $response->assertRedirect();
        $keranjang = session('keranjang');
        $item = $keranjang['product_' . $product->id];

        $this->assertEquals('Lusin', $item['unit']);
        $this->assertEqualsWithDelta(20 / 12, $item['quantity'], 0.001);
    }

    public function test_tambah_keranjang_rejects_quantity_exceeding_stock()
    {
        $product = Product::create([
            'product_name'         => 'Produk Test Stok Kecil',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 10,
        ]);

        // 1 Lusin = 12 buah, which exceeds the 10 buah in stock.
        $response = $this->actingAs($this->pembeli)
            ->post(route('toko.keranjang.tambah'), [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit'       => 'Lusin',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertArrayNotHasKey('product_' . $product->id, session('keranjang', []));
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
            'stock'                => 24,
        ]);

        // Tambah ke keranjang dulu — 1 Lusin = 12 buah
        $this->actingAs($this->pembeli)
            ->post(route('toko.keranjang.tambah'), [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit'       => 'Lusin',
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

        // Stok berkurang dalam base unit (Buah): 24 - (1 Lusin = 12 buah) = 12
        $this->assertEquals(12, $product->fresh()->stock);

        $this->assertDatabaseHas('sale_details', [
            'product_id' => $product->id,
            'unit'       => 'Lusin',
        ]);
    }

    public function test_pesanan_saya_shows_only_own_orders()
    {
        $otherPembeli = User::create([
            'name'     => 'Pembeli Lain',
            'email'    => 'pembeli2@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $this->pembeli->role_id,
        ]);

        $product = Product::create([
            'product_name'         => 'Produk Pesanan',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 100,
        ]);

        // Order milik $this->pembeli
        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id, 'quantity' => 2, 'unit' => 'Buah',
        ]);
        $this->actingAs($this->pembeli)->post(route('toko.checkout.proses'), [
            'nama' => 'Pembeli Test', 'payment_method' => 'Cash',
        ]);

        // Order milik pembeli lain
        $this->actingAs($otherPembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id, 'quantity' => 3, 'unit' => 'Buah',
        ]);
        $this->actingAs($otherPembeli)->post(route('toko.checkout.proses'), [
            'nama' => 'Pembeli Lain', 'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.pesanan'));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Pesanan Saya');
        $response->assertSee('Lihat Detail');

        $sales = \App\Models\Sale::where('user_id', $this->pembeli->id)->get();
        $this->assertCount(1, $sales);
        $otherSales = \App\Models\Sale::where('user_id', $otherPembeli->id)->get();
        $this->assertCount(1, $otherSales);
    }

    public function test_pesanan_saya_link_visible_on_katalog()
    {
        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));

        $response->assertSee('Pesanan Saya');
        $response->assertSee(route('toko.pesanan'), false);
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