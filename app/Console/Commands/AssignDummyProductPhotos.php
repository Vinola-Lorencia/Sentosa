<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SupabaseStorageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Gives every product without any photo the same "CONTOH" placeholder
 * image: uploaded to Supabase once, then shared by path. The ids it
 * changed are logged so --undo reverts exactly those and nothing else.
 *
 * --all also replaces existing photos. Their old image_path is kept in the
 * log (so --undo restores it) and, like every data change here, written to
 * a CSV backup first; the old files in Supabase are never deleted.
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
                            {--all : Ganti foto SEMUA produk dengan foto contoh, termasuk yang sudah punya foto (gabung dengan --dry-run untuk pratinjau)}
                            {--undo : Kembalikan produk yang diubah perintah ini}';

    protected $description = 'Pasang satu foto contoh pada semua produk yang belum punya foto';

    public function handle(SupabaseStorageService $storage): int
    {
        if ($this->option('apply') && $this->option('undo')) {
            $this->error('Pilih salah satu: --apply atau --undo.');

            return self::FAILURE;
        }

        if ($this->option('all') && $this->option('undo')) {
            $this->error('Pilih salah satu: --all atau --undo.');

            return self::FAILURE;
        }

        if ($this->option('undo')) {
            return $this->undo($storage);
        }

        if ($this->option('all')) {
            return $this->option('dry-run') ? $this->dryRunAll() : $this->apply($storage, true);
        }

        if ($this->option('apply')) {
            return $this->apply($storage, false);
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

    private function dryRunAll(): int
    {
        $products = $this->productsNotOnPlaceholder()->orderBy('id')->get(['id', 'product_name', 'image_path']);

        $this->info("[DRY RUN] {$products->count()} produk akan diganti fotonya dengan foto contoh (".self::STORAGE_PATH.').');

        if ($products->isNotEmpty()) {
            $this->table(
                ['ID', 'Nama', 'image_path lama'],
                $products->map(fn (Product $p) => [$p->id, $p->product_name, $p->image_path ?: '-'])->all(),
            );
        }

        $this->line('Tidak ada yang diubah. Jalankan dengan --all (tanpa --dry-run) untuk menerapkan.');

        return self::SUCCESS;
    }

    private function apply(SupabaseStorageService $storage, bool $all): int
    {
        $products = ($all ? $this->productsNotOnPlaceholder() : $this->productsWithoutPhoto())
            ->orderBy('id')
            ->get(['id', 'product_name', 'image_path']);
        $ids = $products->pluck('id')->all();

        if ($ids === []) {
            $this->info($all
                ? 'Semua produk sudah memakai foto contoh. Tidak ada yang diubah.'
                : 'Semua produk sudah punya foto. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $source = base_path(self::SOURCE_IMAGE);
        if (! is_file($source)) {
            $this->error('File foto contoh tidak ditemukan: '.self::SOURCE_IMAGE);

            return self::FAILURE;
        }

        // Backup before anything is uploaded or changed; no backup, no change.
        try {
            $backupPath = $this->writeBackupCsv($products);
        } catch (Throwable $e) {
            $this->error('Gagal menulis CSV cadangan, tidak ada yang diubah: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line("Cadangan image_path lama: {$backupPath}");

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
            $stillEmpty = $all ? $chunk : $this->productsWithoutPhoto()->whereIn('id', $chunk)->pluck('id')->all();
            Product::query()->toBase()->whereIn('id', $stillEmpty)->update(['image_path' => $path]);
            $changedIds = array_merge($changedIds, $stillEmpty);
        }

        // Real photos replaced by --all, so --undo can put them back. An
        // entry from an earlier run wins: it holds the original photo.
        $previousPaths = $products
            ->filter(fn (Product $p) => (string) $p->image_path !== '')
            ->mapWithKeys(fn (Product $p) => [$p->id => $p->image_path])
            ->all();

        $log = $this->readLog();
        $this->writeLog([
            'path' => $path,
            'product_ids' => array_values(array_unique(array_merge($log['product_ids'] ?? [], $changedIds))),
            'previous_image_paths' => ($log['previous_image_paths'] ?? []) + $previousPaths,
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
        // uploaded afterwards is kept. Photos replaced by --all go back to
        // their old image_path; the rest go back to no photo.
        $previousPaths = $log['previous_image_paths'] ?? [];
        $reverted = 0;
        foreach (array_chunk(array_diff($log['product_ids'], array_keys($previousPaths)), 500) as $chunk) {
            $reverted += Product::query()->toBase()
                ->whereIn('id', $chunk)
                ->where('image_path', $log['path'])
                ->update(['image_path' => null]);
        }

        $restored = 0;
        foreach ($previousPaths as $id => $oldPath) {
            $restored += Product::query()->toBase()
                ->where('id', $id)
                ->where('image_path', $log['path'])
                ->update(['image_path' => $oldPath]);
        }

        if (Product::where('image_path', $log['path'])->exists()) {
            $this->warn("File {$log['path']} masih dipakai produk lain, tidak dihapus dari Supabase.");
        } else {
            $storage->delete($log['path']);
            $this->line("File {$log['path']} dihapus dari Supabase.");
        }

        File::delete($this->logPath());

        $this->info("{$reverted} produk dikembalikan tanpa foto.");
        if ($restored > 0) {
            $this->info("{$restored} produk dikembalikan ke foto lamanya.");
        }

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

    /**
     * Every product --all would change: all except those already showing
     * the placeholder.
     *
     * @return Builder<Product>
     */
    private function productsNotOnPlaceholder(): Builder
    {
        return Product::query()
            ->where(fn (Builder $q) => $q->whereNull('image_path')->orWhere('image_path', '!=', self::STORAGE_PATH));
    }

    /**
     * Writes id, name and the old image_path of $products to
     * storage/app/backup-foto-produk-<tanggal-jam>.csv and returns its path.
     * Throws when the file can't be fully written.
     *
     * @param  Collection<int, Product>  $products
     */
    private function writeBackupCsv(Collection $products): string
    {
        $path = storage_path('app/backup-foto-produk-'.now()->toWib()->format('Y-m-d_His').'.csv');
        File::ensureDirectoryExists(dirname($path));

        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membuat {$path}");
        }

        $ok = fputcsv($handle, ['id', 'name', 'image_path']) !== false;
        foreach ($products as $product) {
            $ok = $ok && fputcsv($handle, [$product->id, $product->product_name, (string) $product->image_path]) !== false;
        }
        $ok = fclose($handle) && $ok;

        if (! $ok) {
            throw new RuntimeException("Gagal menulis {$path}");
        }

        return $path;
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
     * @return array{path?: string, product_ids?: list<int>, previous_image_paths?: array<int, string>, applied_at?: string}
     */
    private function readLog(): array
    {
        if (! is_file($this->logPath())) {
            return [];
        }

        return json_decode((string) file_get_contents($this->logPath()), true) ?: [];
    }

    /**
     * @param  array{path: string, product_ids: list<int>, previous_image_paths: array<int, string>, applied_at: string}  $log
     */
    private function writeLog(array $log): void
    {
        File::ensureDirectoryExists(dirname($this->logPath()));
        File::put($this->logPath(), json_encode($log, JSON_PRETTY_PRINT));
    }
}
