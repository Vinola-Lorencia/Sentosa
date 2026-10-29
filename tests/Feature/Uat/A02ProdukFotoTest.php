<?php

namespace Tests\Feature\Uat;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * A2 — Produk: Pemilik tambah/ubah/hapus, Karyawan tambah/ubah saja.
 * A3 — Upload foto + kompresi (Supabase di-fake).
 */
class A02ProdukFotoTest extends OwnerUatTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'product_name' => 'EMBER 20L', 'brand' => 'GM', 'default_buy_price' => 6000, 'default_buy_discount' => 0,
            'sale_price' => 120000, 'sale_unit' => 'Lusin', 'purchase_unit' => 'Goni', 'dus_to_unit' => 2,
            'stock_qty' => 3, 'stock_unit' => 'Lusin',
        ], $overrides);
    }

    private function createVia(User $user): Product
    {
        $this->actingAs($user)
            ->post(route('products.store'), $this->form(['image' => UploadedFile::fake()->image('ember.jpg', 800, 600)]))
            ->assertRedirect(route('products.index'))
            ->assertSessionHasNoErrors();

        return Product::where('product_name', 'EMBER 20L')->firstOrFail();
    }

    public function test_pemilik_can_create_update_and_delete_product(): void
    {
        $product = $this->createVia($this->pemilik);
        $this->assertEquals(36, $product->stock); // 3 Lusin = 36 Buah
        $this->assertSame($this->pemilik->id, $product->created_by);

        $this->actingAs($this->pemilik)
            ->put(route('products.update', $product), $this->form(['sale_price' => 125000, 'stock_qty' => 40, 'stock_unit' => 'Buah']))
            ->assertRedirect(route('products.index'));
        $this->assertEquals(125000, $product->fresh()->sale_price);
        $this->assertEquals(40, $product->fresh()->stock);

        $this->actingAs($this->pemilik)->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));
        $this->assertModelMissing($product);
    }

    public function test_karyawan_can_create_and_update_but_not_delete_product(): void
    {
        $product = $this->createVia($this->karyawan);
        $this->assertSame($this->karyawan->id, $product->created_by);

        $this->actingAs($this->karyawan)
            ->put(route('products.update', $product), $this->form(['sale_price' => 130000]))
            ->assertRedirect(route('products.index'));
        $this->assertEquals(130000, $product->fresh()->sale_price);

        $this->actingAs($this->karyawan)->delete(route('products.destroy', $product))->assertForbidden();
        $this->assertModelExists($product);
    }

    public function test_duplicate_product_name_is_rejected(): void
    {
        $this->product('EMBER 20L');

        $this->actingAs($this->pemilik)
            ->post(route('products.store'), $this->form(['image' => UploadedFile::fake()->image('a.jpg')]))
            ->assertSessionHasErrors('product_name');
        $this->assertSame(1, Product::where('product_name', 'EMBER 20L')->count());
    }

    public function test_photo_upload_is_compressed_stored_and_image_path_filled(): void
    {
        $product = $this->product();
        $original = UploadedFile::fake()->image('kamera-hp.jpg', 4000, 3000);
        $originalSize = $original->getSize();

        $this->actingAs($this->karyawan)
            ->patch(route('products.update-photo', $product), ['image' => $original])
            ->assertOk()
            ->assertJson(['success' => true]);

        $product->refresh();
        $this->assertMatchesRegularExpression('#^products/product_[\w.]+\.jpg$#', $product->image_path);
        $this->assertSame($this->karyawan->id, $product->updated_by);

        $upload = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->first(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), '/storage/v1/object/produk/'));
        $this->assertNotNull($upload, 'Tidak ada unggahan ke Supabase');
        $this->assertSame('https://supabase.test/storage/v1/object/produk/'.$product->image_path, $upload->url());

        $stored = $upload->body();
        $info = getimagesizefromstring($stored);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame(1600, $info[0]); // dikecilkan ke lebar maksimum
        $this->assertSame(1200, $info[1]);
        $this->assertLessThan($originalSize, strlen($stored));
    }

    public function test_photo_upload_rejects_non_image(): void
    {
        $product = $this->product();

        $this->actingAs($this->pemilik)
            ->patchJson(route('products.update-photo', $product), ['image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
            ->assertUnprocessable();

        $this->assertNull($product->fresh()->image_path);
        Http::assertNothingSent();
    }
}
