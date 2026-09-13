<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPhotoActivityTest extends TestCase
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

    private function legacyProductWithoutPhoto(string $name): Product
    {
        // Simulates one of the ~300 old GM-seeded products: no created_by,
        // no image_path — exactly the case this feature needs to cover.
        return Product::create([
            'product_name' => $name, 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 5,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
        ]);
    }

    public function test_update_photo_records_updated_by()
    {
        $product = $this->legacyProductWithoutPhoto('Produk Lama Tanpa Foto');

        $response = $this->actingAs($this->karyawan)
            ->patch(route('products.update-photo', $product), [
                'image' => UploadedFile::fake()->image('foto.jpg'),
            ]);

        $response->assertStatus(200);
        $this->assertEquals($this->karyawan->id, $product->fresh()->updated_by);
        $this->assertEquals('Budi', $product->fresh()->updater->name);
    }

    public function test_dashboard_shows_photo_activity_count_separately_from_product_count()
    {
        $productA = $this->legacyProductWithoutPhoto('Produk A');
        $productB = $this->legacyProductWithoutPhoto('Produk B');

        // Karyawan uploads photos to two pre-existing products they did NOT create.
        $this->actingAs($this->karyawan)->patch(route('products.update-photo', $productA), [
            'image' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $this->actingAs($this->karyawan)->patch(route('products.update-photo', $productB), [
            'image' => UploadedFile::fake()->image('b.jpg'),
        ]);

        $response = $this->actingAs($this->pemilik)->get(route('dashboard'));

        $response->assertStatus(200);

        // Isolate Budi's own card via its data-user-id anchor so this checks
        // his specific counts, not just that "0 produk" appears anywhere.
        $this->assertMatchesRegularExpression(
            '/data-user-id="' . $this->karyawan->id . '".*?0\s*produk/s',
            $response->getContent()
        );
        $response->assertSee('2 foto');
    }

    public function test_karyawan_products_endpoint_includes_photo_only_activity()
    {
        $product = $this->legacyProductWithoutPhoto('Produk Difoto Saja');

        $this->actingAs($this->karyawan)->patch(route('products.update-photo', $product), [
            'image' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'products');
        $response->assertJsonFragment([
            'product_name' => 'Produk Difoto Saja',
            'activity'     => 'Mengubah Foto',
        ]);
    }

    public function test_karyawan_products_endpoint_still_reports_created_products_as_menambah_produk()
    {
        $this->actingAs($this->karyawan)->post(route('products.store'), [
            'product_name' => 'Produk Baru Budi',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'product_name' => 'Produk Baru Budi',
            'activity'     => 'Menambah Produk',
        ]);
    }

    public function test_karyawan_products_endpoint_does_not_duplicate_a_product_both_created_and_rephotographed()
    {
        $this->actingAs($this->karyawan)->post(route('products.store'), [
            'product_name' => 'Produk Ganti Foto',
            'stock_qty'    => 10,
            'stock_unit'   => 'Buah',
            'image'        => UploadedFile::fake()->image('produk.jpg'),
        ]);
        $product = Product::where('product_name', 'Produk Ganti Foto')->firstOrFail();

        // Same karyawan later swaps the photo on the product they created.
        $product->created_at = now()->subMinute();
        $product->save();

        $this->actingAs($this->karyawan)->patch(route('products.update-photo', $product), [
            'image' => UploadedFile::fake()->image('baru.jpg'),
        ]);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'products'); // appears once, not twice
        $response->assertJsonFragment([
            'product_name' => 'Produk Ganti Foto',
            'activity'     => 'Mengubah Foto', // the more recent action wins
        ]);
    }

    public function test_karyawan_products_endpoint_does_not_crash_on_legacy_product_with_null_created_at()
    {
        // Reproduces the real GM-seeded products: bulk-inserted via
        // DB::table()->insert() (bypassing Eloquent), so created_at/
        // updated_at are genuinely NULL at the DB level — not just "unset"
        // on a fresh model. Carbon::gt() throws a TypeError if handed null,
        // which is exactly what crashed /api/karyawan-products/9 in
        // production for a legacy product Anwar uploaded a photo to.
        $id = DB::table('products')->insertGetId([
            'product_name'         => 'Kursi Lama Tanpa Tanggal',
            'sale_price'           => 1000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 5,
            'default_buy_price'    => 500,
            'default_buy_discount' => 0,
            'created_at'           => null,
            'updated_at'           => null,
        ]);
        $product = Product::findOrFail($id);
        $this->assertNull($product->created_at);

        $this->actingAs($this->karyawan)->patch(route('products.update-photo', $product), [
            'image' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertStatus(200);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'product_name' => 'Kursi Lama Tanpa Tanggal',
            'activity'     => 'Mengubah Foto',
        ]);
    }

    public function test_photo_activity_by_one_karyawan_does_not_appear_under_another()
    {
        $otherKaryawan = User::create([
            'name' => 'Siti', 'email' => 'siti@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->karyawan->role_id,
        ]);

        $product = $this->legacyProductWithoutPhoto('Produk Netral');

        $this->actingAs($this->karyawan)->patch(route('products.update-photo', $product), [
            'image' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$otherKaryawan->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'products');
    }
}
