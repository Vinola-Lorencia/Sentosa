<?php

namespace Tests\Feature;

use App\Console\Commands\AssignDummyProductPhotos;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssignDummyProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const OBJECT_URL = 'https://supabase.test/storage/v1/object/produk/'.AssignDummyProductPhotos::STORAGE_PATH;

    private string $storageDir;

    private int $supabaseStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.secret_key' => 'test-secret',
            'services.supabase.storage_bucket' => 'produk',
        ]);

        // Keep the log file away from the real storage/ folder.
        $this->storageDir = sys_get_temp_dir().'/dummy-photos-test-'.uniqid();
        File::ensureDirectoryExists($this->storageDir.'/app');
        $this->app->useStoragePath($this->storageDir);

        Http::fake(['supabase.test/*' => fn () => Http::response(['Key' => 'ok'], $this->supabaseStatus)]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageDir);

        parent::tearDown();
    }

    private function product(string $name, ?string $imagePath = null, ?string $image = null): Product
    {
        return Product::create([
            'product_name' => $name, 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 5,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'image_path' => $imagePath, 'image' => $image,
        ]);
    }

    private function logFile(): string
    {
        return $this->storageDir.'/app/dummy-photos-log.json';
    }

    public function test_dry_run_is_the_default_and_changes_nothing(): void
    {
        $this->product('EMBER 10L');
        $this->product('GAYUNG');
        $this->product('TOPLES', 'products/asli.jpg');

        $this->artisan('products:assign-dummy-photos')
            ->expectsOutputToContain('2 produk tanpa foto akan diberi foto contoh')
            ->expectsOutputToContain('EMBER 10L')
            ->expectsOutputToContain('GAYUNG')
            ->assertSuccessful();

        $this->assertSame(2, Product::whereNull('image_path')->count());
        $this->assertFileDoesNotExist($this->logFile());
        Http::assertNothingSent();
    }

    public function test_dry_run_lists_at_most_ten_examples(): void
    {
        foreach (range(1, 12) as $i) {
            $this->product(sprintf('PRODUK %02d', $i));
        }

        $this->artisan('products:assign-dummy-photos --dry-run')
            ->expectsOutputToContain('12 produk')
            ->expectsOutputToContain('PRODUK 10')
            ->doesntExpectOutputToContain('PRODUK 11')
            ->assertSuccessful();
    }

    public function test_apply_uploads_once_and_gives_every_photoless_product_the_same_path(): void
    {
        $a = $this->product('EMBER 10L');
        $b = $this->product('GAYUNG', '');
        $c = $this->product('BASKOM');
        $withPath = $this->product('TOPLES', 'products/asli.jpg');
        $withLegacy = $this->product('PIRING', null, 'piring.jpg');
        $updatedAt = $a->updated_at;

        $this->artisan('products:assign-dummy-photos --apply')->assertSuccessful();

        foreach ([$a, $b, $c] as $product) {
            $this->assertSame(AssignDummyProductPhotos::STORAGE_PATH, $product->fresh()->image_path);
        }
        $this->assertEquals($updatedAt, $a->fresh()->updated_at);

        // Products that already had a photo are untouched.
        $this->assertSame('products/asli.jpg', $withPath->fresh()->image_path);
        $this->assertNull($withLegacy->fresh()->image_path);
        $this->assertSame('piring.jpg', $withLegacy->fresh()->image);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r->url() === self::OBJECT_URL);

        $log = json_decode(File::get($this->logFile()), true);
        $this->assertSame(AssignDummyProductPhotos::STORAGE_PATH, $log['path']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $log['product_ids']);
    }

    public function test_apply_without_photoless_products_uploads_nothing(): void
    {
        $this->product('TOPLES', 'products/asli.jpg');

        $this->artisan('products:assign-dummy-photos --apply')
            ->expectsOutputToContain('Semua produk sudah punya foto')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertFileDoesNotExist($this->logFile());
    }

    public function test_failed_upload_changes_no_product(): void
    {
        $this->supabaseStatus = 403;
        $product = $this->product('EMBER 10L');

        $this->artisan('products:assign-dummy-photos --apply')->assertFailed();

        $this->assertNull($product->fresh()->image_path);
        $this->assertFileDoesNotExist($this->logFile());
    }

    public function test_undo_reverts_only_products_changed_by_the_command_then_deletes_the_file(): void
    {
        $changed = $this->product('EMBER 10L');
        $replacedLater = $this->product('GAYUNG');
        $original = $this->product('TOPLES', 'products/asli.jpg');

        $this->artisan('products:assign-dummy-photos --apply')->assertSuccessful();

        // A real photo uploaded after --apply must survive the undo, and so
        // must a product someone manually pointed at the placeholder.
        $replacedLater->update(['image_path' => 'products/foto-baru.jpg']);
        $manual = $this->product('BASKOM', AssignDummyProductPhotos::STORAGE_PATH);
        $manual->update(['image_path' => null]);

        $this->artisan('products:assign-dummy-photos --undo')
            ->expectsOutputToContain('1 produk dikembalikan')
            ->assertSuccessful();

        $this->assertNull($changed->fresh()->image_path);
        $this->assertSame('products/foto-baru.jpg', $replacedLater->fresh()->image_path);
        $this->assertSame('products/asli.jpg', $original->fresh()->image_path);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && $r->url() === self::OBJECT_URL);
        $this->assertFileDoesNotExist($this->logFile());
    }

    public function test_undo_keeps_the_file_while_another_product_still_uses_it(): void
    {
        $changed = $this->product('EMBER 10L');
        $this->artisan('products:assign-dummy-photos --apply')->assertSuccessful();

        $other = $this->product('BASKOM', AssignDummyProductPhotos::STORAGE_PATH);

        $this->artisan('products:assign-dummy-photos --undo')
            ->expectsOutputToContain('masih dipakai produk lain')
            ->assertSuccessful();

        $this->assertNull($changed->fresh()->image_path);
        $this->assertSame(AssignDummyProductPhotos::STORAGE_PATH, $other->fresh()->image_path);
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'DELETE');
    }

    public function test_undo_without_log_changes_nothing(): void
    {
        $product = $this->product('TOPLES', AssignDummyProductPhotos::STORAGE_PATH);

        $this->artisan('products:assign-dummy-photos --undo')
            ->expectsOutputToContain('Tidak ada catatan perubahan')
            ->assertSuccessful();

        $this->assertSame(AssignDummyProductPhotos::STORAGE_PATH, $product->fresh()->image_path);
        Http::assertNothingSent();
    }

    public function test_apply_and_undo_together_is_rejected(): void
    {
        $product = $this->product('EMBER 10L');

        $this->artisan('products:assign-dummy-photos --apply --undo')->assertFailed();

        $this->assertNull($product->fresh()->image_path);
        Http::assertNothingSent();
    }
}
