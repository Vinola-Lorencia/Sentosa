<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductView;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductNewNotificationTest extends TestCase
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

    private function makeProduct(string $name, ?\Carbon\Carbon $createdAt = null, float $stock = 10): Product
    {
        $product = Product::create([
            'product_name'         => $name,
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => $stock,
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
        ]);

        if ($createdAt) {
            $product->created_at = $createdAt;
            $product->save();
        }

        return $product;
    }

    public function test_navbar_badge_shows_correct_unseen_new_product_count()
    {
        $this->makeProduct('Produk Baru 1'); // created_at = now(), within window
        $this->makeProduct('Produk Baru 2');
        $this->makeProduct('Produk Lama', now()->subDays(Product::NEW_PRODUCT_DAYS + 5));

        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));

        $response->assertStatus(200);
        // 2 new products, badge shows "2" (not counting the old one).
        $this->assertEquals(
            2,
            Product::newProducts()->unseenBy($this->pembeli->id)->count()
        );
        $this->assertMatchesRegularExpression(
            '/id="produk-baru-badge"[^>]*>\s*2\s*</',
            $response->getContent()
        );
    }

    public function test_navbar_badge_excludes_out_of_stock_new_products()
    {
        // Regression test: a new+unseen product with zero stock was
        // previously counted by the badge (Product::newProducts()->unseenBy()
        // has no stock check) but never appeared under ?filter=baru (the
        // catalog's base query has always excluded stock=0 products) —
        // badge showed 1, the filtered page showed 0. Both must now agree,
        // via the shared Product::available() scope.
        $inStock  = $this->makeProduct('Produk Baru Stok Ada', null, 10);
        $outStock = $this->makeProduct('Produk Baru Stok Habis', null, 0);

        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/id="produk-baru-badge"[^>]*>\s*1\s*</',
            $response->getContent()
        );

        $badgeCount  = Product::available()->newProducts()->unseenBy($this->pembeli->id)->count();
        $filterCount = Product::available()->newProducts()->unseenBy($this->pembeli->id)
            ->where('stock', '>', 0) // mirrors the catalog's base query explicitly
            ->count();

        $this->assertSame(1, $badgeCount);
        $this->assertSame($badgeCount, $filterCount, 'Badge count and catalog filter count must always match.');

        $filterResponse = $this->actingAs($this->pembeli)
            ->get(route('toko.index', ['filter' => 'baru']));

        $filterResponse->assertSee($inStock->product_name);
        $filterResponse->assertDontSee($outStock->product_name);
        $filterResponse->assertSee('Menampilkan 1 produk baru');
    }

    public function test_viewing_one_new_product_only_decrements_that_products_count()
    {
        $productA = $this->makeProduct('Produk A');
        $productB = $this->makeProduct('Produk B');

        $this->assertEquals(2, Product::newProducts()->unseenBy($this->pembeli->id)->count());

        $response = $this->actingAs($this->pembeli)
            ->postJson(route('toko.products.view', $productA));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // productA is now seen — only its own count entry is gone.
        $this->assertDatabaseHas('product_views', [
            'user_id'    => $this->pembeli->id,
            'product_id' => $productA->id,
        ]);
        $this->assertDatabaseMissing('product_views', [
            'user_id'    => $this->pembeli->id,
            'product_id' => $productB->id,
        ]);

        $remaining = Product::newProducts()->unseenBy($this->pembeli->id)->pluck('id');
        $this->assertCount(1, $remaining);
        $this->assertTrue($remaining->contains($productB->id));
        $this->assertFalse($remaining->contains($productA->id));
    }

    public function test_viewing_a_product_does_not_affect_another_users_unseen_state()
    {
        $product = $this->makeProduct('Produk Bersama');

        $otherPembeli = User::create([
            'name' => 'Pembeli Lain', 'email' => 'pembeli2@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->pembeli->role_id,
        ]);

        $this->actingAs($this->pembeli)->postJson(route('toko.products.view', $product));

        // Still unseen (and thus still "new") for the other user.
        $this->assertEquals(1, Product::newProducts()->unseenBy($otherPembeli->id)->count());
        $this->assertEquals(0, Product::newProducts()->unseenBy($this->pembeli->id)->count());
    }

    public function test_marking_viewed_twice_does_not_duplicate_or_error()
    {
        $product = $this->makeProduct('Produk Test');

        $this->actingAs($this->pembeli)->postJson(route('toko.products.view', $product))->assertStatus(200);
        $this->actingAs($this->pembeli)->postJson(route('toko.products.view', $product))->assertStatus(200);

        $this->assertEquals(
            1,
            ProductView::where('user_id', $this->pembeli->id)->where('product_id', $product->id)->count()
        );
    }

    public function test_old_product_never_counts_as_new_even_if_unseen()
    {
        $product = $this->makeProduct('Produk Lama', now()->subDays(Product::NEW_PRODUCT_DAYS + 1));

        $this->assertEquals(0, Product::newProducts()->unseenBy($this->pembeli->id)->count());

        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));
        $response->assertStatus(200);
        // The "🆕 Produk Baru" nav label always contains the word "Baru" —
        // check for the ribbon marker specifically, not that generic word.
        $response->assertDontSee('data-baru-badge="' . $product->id . '"', false);
    }

    public function test_index_shows_baru_ribbon_only_for_unseen_new_products()
    {
        $product = $this->makeProduct('Produk Ribbon');

        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));
        $response->assertSee('data-baru-badge="' . $product->id . '"', false);

        ProductView::create([
            'user_id' => $this->pembeli->id, 'product_id' => $product->id, 'viewed_at' => now(),
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));
        $response->assertDontSee('data-baru-badge="' . $product->id . '"', false);
    }

    public function test_filter_baru_shows_only_unseen_new_products()
    {
        $baru    = $this->makeProduct('Produk Baru');
        $lama    = $this->makeProduct('Produk Lama', now()->subDays(Product::NEW_PRODUCT_DAYS + 1));
        $dilihat = $this->makeProduct('Produk Sudah Dilihat');

        ProductView::create([
            'user_id' => $this->pembeli->id, 'product_id' => $dilihat->id, 'viewed_at' => now(),
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.index', ['filter' => 'baru']));

        $response->assertStatus(200);
        $response->assertSee($baru->product_name);
        $response->assertDontSee($lama->product_name);
        $response->assertDontSee($dilihat->product_name);
    }

    public function test_navbar_link_points_to_filtered_url()
    {
        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));

        $response->assertSee(route('toko.index', ['filter' => 'baru']), false);
    }

    public function test_index_without_filter_shows_all_products_including_old_ones()
    {
        $baru = $this->makeProduct('Produk Baru');
        $lama = $this->makeProduct('Produk Lama', now()->subDays(Product::NEW_PRODUCT_DAYS + 1));

        $response = $this->actingAs($this->pembeli)->get(route('toko.index'));

        $response->assertSee($baru->product_name);
        $response->assertSee($lama->product_name);
    }

    public function test_viewing_product_removes_it_from_filtered_list_on_next_load()
    {
        $product = $this->makeProduct('Sepatu Anak Terbaru');

        $this->actingAs($this->pembeli)
            ->get(route('toko.index', ['filter' => 'baru']))
            ->assertSee($product->product_name);

        $this->actingAs($this->pembeli)->postJson(route('toko.products.view', $product));

        $this->actingAs($this->pembeli)
            ->get(route('toko.index', ['filter' => 'baru']))
            ->assertDontSee($product->product_name);
    }
}
