<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseStorageService
{
    private string $url;
    private string $secretKey;
    private string $bucket;

    public function __construct()
    {
        $this->url       = rtrim((string) config('services.supabase.url'), '/');
        $this->secretKey = (string) config('services.supabase.secret_key');
        $this->bucket    = (string) config('services.supabase.storage_bucket');
    }

    /**
     * Upload a file to Supabase Storage. Returns the stored path.
     */
    public function upload(UploadedFile $file, string $path): string
    {
        $path = ltrim($path, '/');

        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'apikey'        => $this->secretKey,
                'x-upsert'      => 'true',
            ])
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
            ->post("{$this->url}/storage/v1/object/{$this->bucket}/{$path}");

        if ($response->failed()) {
            throw new RuntimeException('Gagal upload gambar ke Supabase Storage: ' . $response->body());
        }

        return $path;
    }

    /**
     * Build the public URL for a stored path.
     */
    public function getPublicUrl(string $path): string
    {
        return "{$this->url}/storage/v1/object/public/{$this->bucket}/" . ltrim($path, '/');
    }

    /**
     * Delete a file from Supabase Storage.
     */
    public function delete(string $path): void
    {
        Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'apikey'        => $this->secretKey,
            ])
            ->delete("{$this->url}/storage/v1/object/{$this->bucket}/" . ltrim($path, '/'));
    }
}
