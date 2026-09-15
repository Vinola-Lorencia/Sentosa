<?php

namespace Tests\Feature;

use App\Models\CartItem;
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
        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $this->pembeli->id,
            'product_id' => $product->id,
            'unit'       => 'Lusin',
        ]);
    }

    public function test_pembeli_can_tambah_keranjang_with_different_unit()
    {
        // Product is sold per Lusin (12 buah, Rp 15.000/Lusin). Customer
        // orders 1 Kodi (20 buah) via the unit dropdown — same logic as the
        // admin Sales form's applyUnitConversion(): quantity/unit stay
        // exactly as entered (1 Kodi, not converted into Lusin terms), and
        // the price is recalculated per Kodi: 15000 * (20/12) = 25000.
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
        $item = CartItem::where('user_id', $this->pembeli->id)
            ->where('product_id', $product->id)
            ->where('unit', 'Kodi')
            ->firstOrFail();

        $this->assertEquals('Kodi', $item->unit);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(25000, $item->price);
        $this->assertEquals(25000, $item->subtotal);
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
        $this->assertDatabaseMissing('cart_items', [
            'user_id'    => $this->pembeli->id,
            'product_id' => $product->id,
        ]);
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

    public function test_checkout_with_insufficient_stock_keeps_cart_intact()
    {
        $productA = Product::create([
            'product_name'         => 'Produk Aman',
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 50,
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
        ]);

        $productB = Product::create([
            'product_name'         => 'Produk Stok Kurang',
            'sale_price'           => 20000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 10,
            'default_buy_price'    => 12000,
            'default_buy_discount' => 0,
        ]);

        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $productA->id, 'quantity' => 5, 'unit' => 'Buah',
        ]);
        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $productB->id, 'quantity' => 10, 'unit' => 'Buah',
        ]);

        // Simulate another buyer taking most of productB's stock between the
        // cart add and checkout — tambahKeranjang's own check can't catch this.
        $productB->update(['stock' => 2]);

        $response = $this->actingAs($this->pembeli)
            ->post(route('toko.checkout.proses'), [
                'nama' => 'Pembeli Test', 'payment_method' => 'Cash',
            ]);

        $response->assertRedirect(route('toko.keranjang'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Produk Stok Kurang', session('error'));
        $this->assertStringContainsString('Silakan sesuaikan jumlah pesanan.', session('error'));

        // No sale was created at all — the whole transaction, including the
        // valid productA line, was rolled back because of productB's failure.
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertEquals(50, $productA->fresh()->stock);
        $this->assertEquals(2, $productB->fresh()->stock);

        // Both cart items are still there — nothing was lost.
        $this->assertDatabaseHas('cart_items', ['user_id' => $this->pembeli->id, 'product_id' => $productA->id]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $this->pembeli->id, 'product_id' => $productB->id]);
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

    public function test_cart_survives_logout_and_login()
    {
        $product = Product::create([
            'product_name'         => 'Produk Persisten',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 50,
        ]);

        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id, 'quantity' => 3, 'unit' => 'Buah',
        ]);

        // Log out — this is the exact call the bug report pointed at:
        // session()->invalidate() wipes the whole session, which is why a
        // session-only cart used to disappear.
        $this->post(route('logout'));

        // Log back in as the same user (fresh session, new session ID).
        $response = $this->actingAs($this->pembeli)->get(route('toko.keranjang'));

        $response->assertStatus(200);
        $response->assertSee('Produk Persisten');
        $response->assertSee('45.000'); // 3 x 15.000, subtotal still correct

        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $this->pembeli->id,
            'product_id' => $product->id,
            'quantity'   => 3,
            'unit'       => 'Buah',
        ]);
    }

    public function test_cart_is_scoped_per_user_not_shared()
    {
        $otherPembeli = User::create([
            'name' => 'Pembeli Lain', 'email' => 'lain@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->pembeli->role_id,
        ]);

        $product = Product::create([
            'product_name'         => 'Produk Bersama',
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 50,
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
        ]);

        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id, 'quantity' => 1, 'unit' => 'Buah',
        ]);

        $response = $this->actingAs($otherPembeli)->get(route('toko.keranjang'));

        $response->assertStatus(200);
        // Note: not asserting assertDontSee('Produk Bersama') here — the
        // success flash message from the first actingAs() call ("Produk
        // Bersama ditambahkan ke keranjang.") leaks into this response
        // because actingAs() reuses one shared test session/cookie jar
        // across both calls in this test, not because carts are actually
        // shared between users. Assert the real outcome instead: the
        // second user's cart is empty.
        $response->assertSee('Keranjang kamu kosong.');
        $this->assertDatabaseCount('cart_items', 1); // only $this->pembeli's row
        $this->assertDatabaseHas('cart_items', ['user_id' => $this->pembeli->id, 'product_id' => $product->id]);
        $this->assertDatabaseMissing('cart_items', ['user_id' => $otherPembeli->id]);
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