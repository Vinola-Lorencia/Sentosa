<?php

namespace Tests\Feature;

use App\Console\Commands\AssignDummyProductPhotos;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The placeholder from products:assign-dummy-photos is ONE Supabase file
 * shared by many products. Replacing, removing or deleting one product's
 * photo must not delete that file while other products still use it.
 */
class ProductSharedPhotoDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED = AssignDummyProductPhotos::STORAGE_PATH;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.secret_key' => 'test-secret',
            'services.supabase.storage_bucket' => 'produk',
        ]);

        Http::preventStrayRequests();
        Http::fake(['supabase.test/*' => Http::response(['Key' => 'ok'], 200)]);

        $this->pemilik = User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('password'), 'role_id' => Role::create(['role_name' => 'Pemilik'])->id,
        ]);
    }

    private function product(string $name, ?string $imagePath = self::SHARED): Product
    {
        return Product::create([
            'product_name' => $name, 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 5,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'image_path' => $imagePath,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_name' => $product->product_name, 'sale_price' => 1000, 'brand' => 'GM',
            'default_buy_price' => 500, 'default_buy_discount' => 0, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock_qty' => 5, 'stock_unit' => 'Buah',
        ], $overrides);
    }

    private function assertFileDeleted(string $path, bool $deleted): void
    {
        $matches = fn (HttpRequest $r) => $r->method() === 'DELETE'
            && $r->url() === 'https://supabase.test/storage/v1/object/produk/'.$path;

        $deleted ? Http::assertSent($matches) : Http::assertNotSent($matches);
    }

    public function test_replacing_photo_via_edit_form_keeps_shared_placeholder_file(): void
    {
        $edited = $this->product('EMBER 10L');
        $other = $this->product('GAYUNG');

        $this->actingAs($this->pemilik)
            ->put(route('products.update', $edited), $this->updatePayload($edited, [
                'image' => UploadedFile::fake()->image('foto.jpg'),
            ]))
            ->assertRedirect(route('products.index'));

        $this->assertNotSame(self::SHARED, $edited->fresh()->image_path);
        $this->assertSame(self::SHARED, $other->fresh()->image_path);
        $this->assertFileDeleted(self::SHARED, false);
    }

    public function test_replacing_photo_via_quick_photo_upload_keeps_shared_placeholder_file(): void
    {
        $edited = $this->product('EMBER 10L');
        $other = $this->product('GAYUNG');

        $this->actingAs($this->pemilik)
            ->patch(route('products.update-photo', $edited), ['image' => UploadedFile::fake()->image('foto.jpg')])
            ->assertOk();

        $this->assertNotSame(self::SHARED, $edited->fresh()->image_path);
        $this->assertSame(self::SHARED, $other->fresh()->image_path);
        $this->assertFileDeleted(self::SHARED, false);
    }

    public function test_removing_photo_keeps_shared_placeholder_file(): void
    {
        $edited = $this->product('EMBER 10L');
        $other = $this->product('GAYUNG');

        $this->actingAs($this->pemilik)
            ->put(route('products.update', $edited), $this->updatePayload($edited, ['remove_image' => '1']))
            ->assertRedirect(route('products.index'));

        $this->assertNull($edited->fresh()->image_path);
        $this->assertSame(self::SHARED, $other->fresh()->image_path);
        $this->assertFileDeleted(self::SHARED, false);
    }

    public function test_deleting_product_keeps_shared_placeholder_file(): void
    {
        $deleted = $this->product('EMBER 10L');
        $other = $this->product('GAYUNG');

        $this->actingAs($this->pemilik)
            ->delete(route('products.destroy', $deleted))
            ->assertRedirect(route('products.index'));

        $this->assertModelMissing($deleted);
        $this->assertSame(self::SHARED, $other->fresh()->image_path);
        $this->assertFileDeleted(self::SHARED, false);
    }

    public function test_deleting_last_product_using_a_photo_still_deletes_the_file(): void
    {
        $deleted = $this->product('EMBER 10L');

        $this->actingAs($this->pemilik)->delete(route('products.destroy', $deleted));

        $this->assertFileDeleted(self::SHARED, true);
    }

    public function test_replacing_an_unshared_photo_still_deletes_the_old_file(): void
    {
        $edited = $this->product('EMBER 10L', 'products/asli.jpg');
        $this->product('GAYUNG');

        $this->actingAs($this->pemilik)
            ->patch(route('products.update-photo', $edited), ['image' => UploadedFile::fake()->image('foto.jpg')])
            ->assertOk();

        $this->assertFileDeleted('products/asli.jpg', true);
        $this->assertFileDeleted(self::SHARED, false);
    }
}
