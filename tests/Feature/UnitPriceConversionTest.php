<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the storefront cart's unit-conversion pricing matches the admin
 * Sales form's applyUnitConversion() logic exactly:
 *   - Choosing the product's own sale_unit never divides the price.
 *   - Choosing a smaller unit (e.g. Buah on a Lusin-priced product) divides
 *     the price down proportionally.
 *   - The two ways of buying "the same amount of goods" (1 Lusin vs the
 *     equivalent 12 Buah) must produce the identical subtotal.
 */
class UnitPriceConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;
    private Product $product;

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

        // Priced per Lusin (12 buah) — Rp 120.000 / Lusin = Rp 10.000 / Buah.
        $this->product = Product::create([
            'product_name'         => 'Kursi Lipat Konversi',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 80000,
            'default_buy_discount' => 0,
            'sale_price'           => 120000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 144, // 12 Lusin worth, plenty for either scenario
        ]);
    }

    private function cartItem(int $productId, string $unit): CartItem
    {
        return CartItem::where('user_id', $this->pembeli->id)
            ->where('product_id', $productId)
            ->where('unit', $unit)
            ->firstOrFail();
    }

    public function test_qty_1_lusin_gives_full_price_subtotal_not_divided()
    {
        $response = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'unit'       => 'Lusin',
        ]);

        $response->assertRedirect();

        $item = $this->cartItem($this->product->id, 'Lusin');

        $this->assertEquals('Lusin', $item->unit);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(120000, $item->price);   // full price — NOT divided by 12
        $this->assertEquals(120000, $item->subtotal); // harga penuh x 1
    }

    public function test_qty_12_buah_gives_same_subtotal_as_qty_1_lusin()
    {
        $response = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id,
            'quantity'   => 12,
            'unit'       => 'Buah',
        ]);

        $response->assertRedirect();

        $item = $this->cartItem($this->product->id, 'Buah');

        $this->assertEquals('Buah', $item->unit);
        $this->assertEquals(12, $item->quantity);
        $this->assertEquals(10000, $item->price);     // 120000 / 12, price per Buah
        $this->assertEquals(120000, $item->subtotal);  // (harga/12) x 12 = harga penuh

        // Same total value of goods, regardless of which unit was used to buy it.
        $lusinResponse = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'unit'       => 'Lusin',
        ]);
        $lusinResponse->assertRedirect();

        $lusinItem = $this->cartItem($this->product->id, 'Lusin');
        $this->assertEquals($item->subtotal, $lusinItem->subtotal);
    }

    public function test_price_for_unit_larger_than_sale_unit_multiplies_up()
    {
        // Kodi (20 buah) on a product priced per Lusin (12 buah):
        // 120000 * (20/12) = 200000.
        $response = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'unit'       => 'Kodi',
        ]);

        $response->assertRedirect();

        $item = $this->cartItem($this->product->id, 'Kodi');

        $this->assertEquals(200000, $item->price);
        $this->assertEquals(200000, $item->subtotal);
    }

    public function test_checkout_preserves_the_correct_price_and_unit_into_sale_detail()
    {
        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'unit'       => 'Lusin',
        ]);

        $response = $this->actingAs($this->pembeli)->post(route('toko.checkout.proses'), [
            'nama' => 'Pembeli Test', 'payment_method' => 'Cash',
        ]);

        $response->assertRedirect(route('toko.pesanan'));

        $detail = SaleDetail::where('product_id', $this->product->id)->firstOrFail();

        $this->assertEquals('Lusin', $detail->unit);
        $this->assertEquals(1, (float) $detail->quantity);
        $this->assertEquals(120000, (float) $detail->price);
        $this->assertEquals(120000, (float) $detail->subtotal);

        // Stock deducted in true base units: 1 Lusin = 12 Buah.
        $this->assertEquals(144 - 12, (float) $this->product->fresh()->stock);

        // Cart cleared from the database after a successful checkout.
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_adding_same_product_in_two_different_units_creates_separate_cart_lines()
    {
        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id, 'quantity' => 1, 'unit' => 'Lusin',
        ]);
        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'Buah',
        ]);

        $keranjang = CartItem::where('user_id', $this->pembeli->id)->get();

        $this->assertCount(2, $keranjang);
        $this->assertEquals(1, $this->cartItem($this->product->id, 'Lusin')->quantity);
        $this->assertEquals(5, $this->cartItem($this->product->id, 'Buah')->quantity);
    }

    public function test_price_per_unit_is_rounded_up_to_nearest_thousand_when_division_is_not_exact()
    {
        // Rp 100.000 / Lusin -> Rp 8.333,33 / Buah -> rounded UP to Rp 9.000.
        $product = Product::create([
            'product_name'         => 'Produk Harga Ganjil',
            'sale_price'           => 100000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 100,
            'default_buy_price'    => 60000,
            'default_buy_discount' => 0,
        ]);

        $response = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit'       => 'Buah',
        ]);

        $response->assertRedirect();

        $item = $this->cartItem($product->id, 'Buah');

        $this->assertEquals(9000, $item->price);
        $this->assertEquals(9000, $item->subtotal);
    }

    public function test_subtotal_is_computed_from_the_already_rounded_price_not_rounded_after_multiplying()
    {
        // Same Rp 100.000/Lusin product, buying 3 Buah. If subtotal were
        // computed first (100000/12 * 3 = 25000 exactly) and rounded after,
        // it would still land on a clean 25000 by coincidence — so use a
        // quantity where the two orders of operation diverge to prove price
        // is rounded BEFORE multiplying: price/unit = ceil(8333.33) = 9000,
        // so 3 Buah must cost 3 x 9000 = 27000, not ceil(3 x 8333.33) = 25000.
        $product = Product::create([
            'product_name'         => 'Produk Urutan Pembulatan',
            'sale_price'           => 100000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 100,
            'default_buy_price'    => 60000,
            'default_buy_discount' => 0,
        ]);

        $response = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id,
            'quantity'   => 3,
            'unit'       => 'Buah',
        ]);

        $response->assertRedirect();

        $item = $this->cartItem($product->id, 'Buah');

        $this->assertEquals(9000, $item->price);
        $this->assertEquals(27000, $item->subtotal); // 3 x 9000, not ceil(3 x 8333.33...)
    }

    public function test_choosing_the_products_own_sale_unit_never_rounds_a_non_round_price()
    {
        $product = Product::create([
            'product_name'         => 'Produk Harga Asli',
            'sale_price'           => 15750, // deliberately not a multiple of 1.000
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 100,
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
        ]);

        $response = $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit'       => 'Lusin', // same as sale_unit — ratio 1:1, no real division
        ]);

        $response->assertRedirect();

        $item = $this->cartItem($product->id, 'Lusin');

        $this->assertEquals(15750, $item->price); // untouched, not rounded to 16000
    }

    public function test_rounded_price_carries_through_checkout_into_sale_detail()
    {
        $product = Product::create([
            'product_name'         => 'Produk Struk Bulat',
            'sale_price'           => 100000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 100,
            'default_buy_price'    => 60000,
            'default_buy_discount' => 0,
        ]);

        $this->actingAs($this->pembeli)->post(route('toko.keranjang.tambah'), [
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit'       => 'Buah',
        ]);

        $response = $this->actingAs($this->pembeli)->post(route('toko.checkout.proses'), [
            'nama' => 'Pembeli Test', 'payment_method' => 'Cash',
        ]);

        $response->assertRedirect(route('toko.pesanan'));

        $detail = SaleDetail::where('product_id', $product->id)->firstOrFail();

        // The rounded Rp 9.000, not the raw Rp 8.333,33 — struk/invoice
        // display just shows this stored value, so it's correct there too.
        $this->assertEquals(9000, (float) $detail->price);
        $this->assertEquals(9000, (float) $detail->subtotal);
    }
}
