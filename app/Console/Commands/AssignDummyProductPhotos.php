<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SupabaseStorageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Gives every product without any photo the same "CONTOH" placeholder
 * image: uploaded to Supabase once, then shared by path. The ids it
 * changed are logged so --undo reverts exactly those and nothing else.
 */
class AssignDummyProductPhotos extends Command
{
    /**
     * Single shared object in the bucket — never one copy per product.
     */
    public const STORAGE_PATH = 'products/contoh-umum.png';

    public const SOURCE_IMAGE = 'database/seeders/foto-contoh/umum.png';

    protected $signature = 'products:assign-dummy-photos
                            {--dry-run : Hanya tampilkan produk yang akan diubah (default)}
                            {--apply : Unggah foto contoh dan isi image_path}
                            {--undo : Kembalikan produk yang diubah perintah ini}';

    protected $description = 'Pasang satu foto contoh pada semua produk yang belum punya foto';

    public function handle(SupabaseStorageService $storage): int
    {
        if ($this->option('apply') && $this->option('undo')) {
            $this->error('Pilih salah satu: --apply atau --undo.');

            return self::FAILURE;
        }

        if ($this->option('undo')) {
            return $this->undo($storage);
        }

        if ($this->option('apply')) {
            return $this->apply($storage);
        }

        return $this->dryRun();
    }

    private function dryRun(): int
    {
        $count = $this->productsWithoutPhoto()->count();

        $this->info("[DRY RUN] {$count} produk tanpa foto akan diberi foto contoh.");
        $this->listExamples();
        $this->line('Tidak ada yang diubah. Jalankan dengan --apply untuk menerapkan.');

        return self::SUCCESS;
    }

    private function apply(SupabaseStorageService $storage): int
    {
        $ids = $this->productsWithoutPhoto()->pluck('id')->all();

        if ($ids === []) {
            $this->info('Semua produk sudah punya foto. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $source = base_path(self::SOURCE_IMAGE);
        if (! is_file($source)) {
            $this->error('File foto contoh tidak ditemukan: '.self::SOURCE_IMAGE);

            return self::FAILURE;
        }

        try {
            $path = $storage->upload(new UploadedFile($source, 'umum.png', 'image/png', null, true), self::STORAGE_PATH);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Re-check the photo columns at write time so a photo uploaded since
        // the id list was read is never overwritten. toBase() keeps
        // updated_at untouched — only image_path changes.
        $changedIds = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $stillEmpty = $this->productsWithoutPhoto()->whereIn('id', $chunk)->pluck('id')->all();
            Product::query()->toBase()->whereIn('id', $stillEmpty)->update(['image_path' => $path]);
            $changedIds = array_merge($changedIds, $stillEmpty);
        }

        $log = $this->readLog();
        $this->writeLog([
            'path' => $path,
            'product_ids' => array_values(array_unique(array_merge($log['product_ids'] ?? [], $changedIds))),
            'applied_at' => now()->toIso8601String(),
        ]);

        $this->info(count($changedIds)." produk diberi foto contoh ({$path}).");

        return self::SUCCESS;
    }

    private function undo(SupabaseStorageService $storage): int
    {
        $log = $this->readLog();

        if (empty($log['product_ids']) || empty($log['path'])) {
            $this->info('Tidak ada catatan perubahan (storage/app/dummy-photos-log.json). Tidak ada yang dikembalikan.');

            return self::SUCCESS;
        }

        // Only products still showing the placeholder — a real photo
        // uploaded afterwards is kept.
        $reverted = 0;
        foreach (array_chunk($log['product_ids'], 500) as $chunk) {
            $reverted += Product::query()->toBase()
                ->whereIn('id', $chunk)
                ->where('image_path', $log['path'])
                ->update(['image_path' => null]);
        }

        if (Product::where('image_path', $log['path'])->exists()) {
            $this->warn("File {$log['path']} masih dipakai produk lain, tidak dihapus dari Supabase.");
        } else {
            $storage->delete($log['path']);
            $this->line("File {$log['path']} dihapus dari Supabase.");
        }

        File::delete($this->logPath());

        $this->info("{$reverted} produk dikembalikan tanpa foto.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Product>
     */
    private function productsWithoutPhoto(): Builder
    {
        return Product::query()
            ->where(fn (Builder $q) => $q->whereNull('image_path')->orWhere('image_path', ''))
            ->where(fn (Builder $q) => $q->whereNull('image')->orWhere('image', ''));
    }

    private function listExamples(): void
    {
        $names = $this->productsWithoutPhoto()->orderBy('product_name')->limit(10)->pluck('product_name');

        foreach ($names as $name) {
            $this->line("  - {$name}");
        }
    }

    private function logPath(): string
    {
        return storage_path('app/dummy-photos-log.json');
    }

    /**
     * @return array{path?: string, product_ids?: list<int>, applied_at?: string}
     */
    private function readLog(): array
    {
        if (! is_file($this->logPath())) {
            return [];
        }

        return json_decode((string) file_get_contents($this->logPath()), true) ?: [];
    }

    /**
     * @param  array{path: string, product_ids: list<int>, applied_at: string}  $log
     */
    private function writeLog(array $log): void
    {
        File::ensureDirectoryExists(dirname($this->logPath()));
        File::put($this->logPath(), json_encode($log, JSON_PRETTY_PRINT));
    }
}
