<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\WhatsAppSession;
use App\Services\UnitConverter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Laravel\Facades\OpenAI;
use Twilio\Rest\Client;

class WhatsAppController extends Controller
{
    private const MAX_HISTORY = 20;

    private const MAX_IMAGES = 3;

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function handle(Request $request): Response
    {
        [$from, $body] = $this->parseIncoming($request);

        if ($from === null) {
            return response('', 200);
        }

        $session = WhatsAppSession::firstOrCreate(
            ['phone_number' => $from],
            ['messages' => [], 'cart' => [], 'step' => 'greeting']
        );

        $reply = '';
        $productsToShow = collect();

        try {
            $handled = false;

            // ── Priority 0: cart inquiry at ANY step ─────────────────────────────
            if ($this->isLikelyCartQuery($body)) {
                if (empty($session->cart)) {
                    $reply = 'Keranjang masih kosong. Silakan pilih produk terlebih dahulu.';
                } else {
                    $reply = $this->formatCartSummary($session->cart);
                    if ($session->step === 'confirm_order') {
                        $reply .= "\n\nKetik *ya* untuk konfirmasi atau *batal* untuk membatalkan.";
                    } else {
                        $reply .= "\n\nKetik *selesai* jika sudah selesai berbelanja.";
                    }
                }
                $handled = true;
            }

            // ── Priority 1a: collecting name ──────────────────────────────────────
            if (! $handled && $session->step === 'need_name') {
                if ($this->isLikelyNonInput($body)) {
                    // Looks like a question/command — fall through to intent detection
                } else {
                    $session->customer_name = trim($body);
                    $session->step = 'need_address';
                    $session->save();
                    $reply = "Terima kasih, *{$session->customer_name}*! Sekarang tolong berikan alamat pengiriman Anda.\n_(Ketik *ambil sendiri* jika akan mengambil langsung ke toko)_";
                    $handled = true;
                }
            }

            // ── Priority 1b: collecting address ───────────────────────────────────
            if (! $handled && $session->step === 'need_address') {
                if ($this->isPickup($body)) {
                    $session->address = 'Ambil Sendiri di Toko';
                    $session->step = 'need_payment';
                    $session->save();
                    $reply = $this->paymentPrompt();
                    $handled = true;
                } elseif ($this->isLikelyNonInput($body)) {
                    // Looks like a question/command — fall through to intent detection
                } else {
                    $session->address = trim($body);
                    $session->step = 'need_payment';
                    $session->save();
                    $reply = $this->paymentPrompt();
                    $handled = true;
                }
            }

            // ── Priority 1b.5: collecting payment method ──────────────────────────
            if (! $handled && $session->step === 'need_payment') {
                $paymentMethod = $this->parsePaymentMethod($body);
                if ($paymentMethod !== null) {
                    $session->payment_method = $paymentMethod;
                    $session->save();
                    [$reply, $productsToShow] = $this->createOrder($session);
                    $handled = true;
                } elseif ($this->isLikelyNonInput($body)) {
                    // Question/command — fall through to intent detection
                } else {
                    // Unrecognised input — re-prompt
                    $reply = "Maaf, pilihan tidak dikenali.\n\n".$this->paymentPrompt();
                    $handled = true;
                }
            }

            // ── Priority 1c: waiting for explicit order confirmation ───────────────
            if (! $handled && $session->step === 'confirm_order') {
                if ($this->isConfirmation($body)) {
                    if (empty($session->customer_name)) {
                        $session->step = 'need_name';
                        $session->save();
                        $reply = 'Siap! Boleh minta nama Anda untuk pesanan ini?';
                    } elseif (empty($session->address)) {
                        $session->step = 'need_address';
                        $session->save();
                        $reply = "Boleh minta alamat pengirimannya, *{$session->customer_name}*?\n_(Ketik *ambil sendiri* jika akan mengambil langsung ke toko)_";
                    } elseif (empty($session->payment_method)) {
                        $session->step = 'need_payment';
                        $session->save();
                        $reply = $this->paymentPrompt();
                    } else {
                        [$reply, $productsToShow] = $this->createOrder($session);
                    }
                    $handled = true;
                }
                // Not a confirmation — fall through to intent detection
                // (allows customer to ask questions or add more items)
            }

            // ── Priority 1d: resolve pending clarification ────────────────────────
            // Never hijack a message that looks like a brand new multi-item
            // bulk order — otherwise a leftover unresolved clarification from
            // an earlier test/conversation silently swallows the next real
            // order instead of letting it reach extractIntent()/
            // handleBulkOrder() normally (see resolveClarification()'s own
            // number-pick shortcut, which a stray digit anywhere in a long
            // new message could otherwise trigger).
            if (! $handled && ! empty($session->pending_clarification) && ! $this->looksLikeMultiItemBulkMessage($body)) {
                [$handled, $reply] = $this->resolveClarification($body, $session);
            }

            // ── Priority 2: intent-based routing ─────────────────────────────────
            if (! $handled) {
                $intent = $this->extractIntent($body);
                $mode = $intent['mode'] ?? 'chat';
                $keywords = $intent['keywords'] ?? [];
                $bulkItems = $intent['bulk_items'] ?? [];

                if ($mode === 'cancel') {
                    $session->cart = [];
                    $session->pending_clarification = null;
                    $session->step = 'chat';
                    $session->save();
                    $reply = 'Pesanan dibatalkan. Ada yang bisa kami bantu lagi?';

                } elseif ($mode === 'done' || $mode === 'confirm') {
                    // Both "selesai" and "ya/iya" without confirm_order step → show cart + ask
                    if (empty($session->cart)) {
                        $reply = 'Belum ada produk dalam keranjang. Silakan pilih produk terlebih dahulu.';
                    } else {
                        $reply = $this->formatCartSummary($session->cart)
                               ."\n\nKetik *YA* untuk konfirmasi pesanan, atau *batal* untuk membatalkan.";
                        $session->step = 'confirm_order';
                        $session->save();
                    }

                } elseif ($mode === 'bulk' && ! empty($bulkItems)) {
                    [$reply, $productsToShow] = $this->handleBulkOrder($bulkItems, $session);

                } else {
                    // Stage 2: search DB with extracted keywords
                    $matchedProducts = $this->searchByKeywords($keywords);

                    // Stage 3: GPT generates reply using only the relevant product subset
                    [$reply, $productsToShow, $cartAdd] = $this->generateChatReply($body, $session, $matchedProducts);

                    $cart = $session->cart ?? [];
                    foreach ($cartAdd as $item) {
                        $product = Product::where('stock', '>', 0)
                            ->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($item['product_name'] ?? '').'%'])
                            ->first();
                        if ($product) {
                            $cart = $this->addToCart($cart, $product, (float) ($item['qty'] ?? 1), $item['unit'] ?? $product->sale_unit);
                        }
                    }

                    $history = $session->messages ?? [];
                    $history[] = ['role' => 'user',     'content' => $body];
                    $history[] = ['role' => 'assistant', 'content' => $reply];

                    $session->cart = $cart;
                    $session->messages = $history;
                    if (! in_array($session->step, ['confirm_order', 'need_name', 'need_address', 'need_payment'])) {
                        $session->step = 'chat';
                    }
                    $session->save();
                }
            }

            $session->last_activity = now();
            $session->save();

        } catch (RateLimitException $e) {
            \Log::warning('OpenAI rate limit hit', ['from' => $from]);
            $reply = 'Maaf, sistem kami sedang sibuk. Silakan coba lagi dalam beberapa saat.';
        } catch (\Throwable $e) {
            \Log::error('WhatsApp webhook error: '.$e->getMessage(), ['from' => $from]);
            $reply = 'Maaf, terjadi kesalahan. Silakan coba lagi atau hubungi kami langsung.';
        }

        // Send reply + optional product images
        try {
            $this->sendMessage($from, $reply);

            $baseUrl = rtrim(env('FILESYSTEM_URL', env('APP_URL')), '/');
            $isLocalhost = str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1');

            foreach ($productsToShow as $product) {
                $imageUrl = $baseUrl.'/storage_link/'.ltrim((string) $product->image, '/');

                \Log::info('WhatsApp image debug', [
                    'product' => $product->product_name,
                    'image_field' => $product->image,
                    'image_url' => $imageUrl,
                    'localhost' => $isLocalhost,
                ]);

                $caption = "📦 *{$product->product_name}*\n"
                         .'💰 Rp'.number_format($product->sale_price, 0, ',', '.')."/{$product->sale_unit}\n"
                         ."📦 Stok: {$product->stock} {$product->sale_unit}";

                if ($isLocalhost) {
                    // Twilio cannot fetch localhost URLs — send text-only with a note
                    $caption .= "\n🖼️ _(Gambar tersedia di sistem)_";
                    $this->sendMessage($from, $caption);
                } else {
                    $this->sendMessage($from, $caption, $imageUrl);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('WhatsApp send failed: '.$e->getMessage(), ['to' => $from]);
        }

        return response('', 200);
    }

    // ─── Conversation guard helpers ───────────────────────────────────────────

    private function transcribeVoiceNote(string $mediaUrl): ?string
    {
        try {
            Storage::makeDirectory('temp');
            $tempPath = storage_path('app/temp/voice_'.uniqid().'.ogg');

            $response = Http::withBasicAuth(
                config('services.twilio.sid'),
                config('services.twilio.token')
            )->get($mediaUrl);

            if (! $response->successful()) {
                Log::error('Voice note download failed', ['url' => $mediaUrl, 'status' => $response->status()]);

                return null;
            }

            file_put_contents($tempPath, $response->body());

            $result = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($tempPath, 'r'),
                'language' => 'id',
            ]);

            @unlink($tempPath);

            return trim($result->text);
        } catch (\Throwable $e) {
            Log::error('Whisper transcription failed', ['error' => $e->getMessage()]);
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return null;
        }
    }

    /**
     * True when the message looks like a multi-item bulk order: 2+
     * comma/newline-separated parts that each contain a number AND letters
     * (e.g. "panci 2 lusin, ember 5 buah"). Shared by every place that must
     * never mistake a fresh bulk order for something else (a cart query, a
     * leftover clarification reply, GPT's own intent classification).
     */
    private function looksLikeMultiItemBulkMessage(string $body): bool
    {
        $parts = preg_split('/[,\n]+/', $body);
        $itemLike = array_filter($parts, fn ($p) => preg_match('/\d/', $p) && preg_match('/[a-zA-Z]/u', $p));

        return count($itemLike) >= 2;
    }

    private function isLikelyCartQuery(string $body): bool
    {
        // Never intercept bulk orders (2+ comma/newline parts that each have a number + letters)
        if ($this->looksLikeMultiItemBulkMessage($body)) {
            return false;
        }

        $lower = strtolower($body);
        // "keranjang" excluded — it's also a common product name (keranjang sampah, keranjang plastik)
        // Only match explicit cart-view phrases or clear financial queries
        foreach (['total belanja', 'lihat keranjang', 'cek keranjang', 'isi keranjang',
            'ringkasan', 'summary', 'cart', 'total saya', 'total ku'] as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        // Single-word triggers only safe when message is short (no product list)
        if (strlen(trim($body)) <= 30) {
            foreach (['total', 'berapa', 'lihat'] as $kw) {
                if (str_contains($lower, $kw)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isLikelyNonInput(string $body): bool
    {
        // True when the message looks like a question or command, not a name/address
        if (str_contains($body, '?')) {
            return true;
        }
        $lower = strtolower($body);
        $flags = [
            'total', 'berapa', 'lihat', 'tidak', 'engga', 'enggak', 'nggak', 'batal',
            'cancel', 'iya', 'ya', 'ok', 'oke', 'okay', 'setuju', 'selesai', 'pesan',
            'order', 'beli', 'gambar', 'foto', 'harga', 'stok', 'info', 'mau', 'ingin',
        ];
        foreach ($flags as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function isConfirmation(string $body): bool
    {
        $lower = strtolower(trim($body));

        return in_array($lower, ['ya', 'iya', 'ok', 'oke', 'okay', 'setuju', 'yes', 'yep', 'yap', 'confirm']);
    }

    private function paymentPrompt(): string
    {
        return "Pembayaran dilakukan dengan cara apa?\n\n"
             ."1️⃣ Transfer Bank\n"
             ."2️⃣ Bayar di Toko (COD)\n\n"
             .'Balas dengan angka *1* atau *2*';
    }

    private function parsePaymentMethod(string $body): ?string
    {
        $lower = strtolower(trim($body));
        $transfer = ['1', 'transfer', 'transfer bank', 'bank', 'bca', 'bni', 'bri', 'mandiri'];
        $cod = ['2', 'cod', 'cash', 'tunai', 'bayar di toko', 'bayar toko', 'bayar langsung', 'cash on delivery'];
        if (in_array($lower, $transfer)) {
            return 'Transfer Bank';
        }
        if (in_array($lower, $cod)) {
            return 'Bayar di Toko';
        }
        foreach ($transfer as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Transfer Bank';
            }
        }
        foreach ($cod as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Bayar di Toko';
            }
        }

        return null;
    }

    private function isPickup(string $body): bool
    {
        $lower = strtolower(trim($body));
        $phrases = ['ambil sendiri', 'ambil langsung', 'datang sendiri', 'pickup', 'pick up',
            'jemput sendiri', 'ambil di toko', 'ke toko', 'self pickup'];
        foreach ($phrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    // ─── Stage 1: intent + keyword extraction (no product list needed) ────────

    private function extractIntent(string $body): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Kamu adalah ekstraktor intent pembelian. Analisis pesan pelanggan dan kembalikan JSON:
{
  "mode": "bulk" | "chat" | "confirm" | "done" | "cancel",
  "keywords": ["kata kunci nama produk dari pesan"],
  "bulk_items": [{"item": "nama produk", "qty": angka, "unit": "satuan"}]
}

Panduan mode:
- "bulk": pesan mengandung LEBIH DARI SATU produk dengan jumlah, ATAU satu produk dengan jumlah yang dipisah koma/newline. WAJIB bulk jika ada koma yang memisahkan item produk berbeda (contoh: "panci 2 lusin, mangkok 3 lusin, ember 5 lusin" → HARUS bulk). Jika ragu antara bulk dan chat, pilih bulk.
- "chat": pertanyaan umum, tanya harga/stok SATU produk saja, atau percakapan tanpa angka pemesanan
- "confirm": setuju/konfirmasi pesanan ("ya", "iya", "ok", "setuju", "benar", "lanjut")
- "done": selesai memilih ("udah", "cukup", "itu saja", "sudah", "selesai", "segitu saja")
- "cancel": batalkan ("batal", "cancel", "tidak jadi")

⚠️ ATURAN WAJIB:
- Jika pesan mengandung koma (,) dan setiap bagian berisi nama produk + angka → mode HARUS "bulk"
- Ekstrak qty dan unit PERSIS seperti yang dikatakan pelanggan. JANGAN konversi atau ubah satuan.
- Contoh BENAR: "3 lusin" → {"qty": 3, "unit": "lusin"}
- Contoh SALAH: "3 lusin" → {"qty": 36, "unit": "buah"} ← DILARANG
- unit default "buah" jika tidak disebutkan sama sekali.',
                ],
                ['role' => 'user', 'content' => $body],
            ],
        ]);

        $intent = json_decode($response->choices[0]->message->content, true) ?? [];

        // Safety net: if the message contains commas and at least 2 number+word pairs,
        // force mode=bulk regardless of what GPT returned.
        if (($intent['mode'] ?? '') !== 'bulk' && $this->looksLikeMultiItemBulkMessage($body)) {
            Log::info('Intent override → bulk (comma+number pattern)', ['original_mode' => $intent['mode'] ?? null, 'body' => $body]);
            $intent['mode'] = 'bulk';
        }

        \Log::info('Intent extracted', ['body' => $body, 'intent' => $intent]);

        return $intent;
    }

    // ─── Stage 2: DB search with keywords ────────────────────────────────────

    private function searchByKeywords(array $keywords): Collection
    {
        if (empty($keywords)) {
            return Product::where('stock', '>', 0)->get();
        }

        // Pass 1: AND — product must contain ALL keywords (strictest)
        $andResults = Product::where('stock', '>', 0)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($kw).'%']);
                }
            })
            ->get();

        if ($andResults->isNotEmpty()) {
            return $andResults;
        }

        // Pass 2: OR — product contains ANY keyword
        $orResults = Product::where('stock', '>', 0)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($kw).'%']);
                }
            })
            ->get();

        // Fall back to full catalog only if nothing matched at all
        return $orResults->isEmpty()
            ? Product::where('stock', '>', 0)->get()
            : $orResults;
    }

    // ─── Stage 3: GPT reply with only the relevant product subset ────────────

    private function generateChatReply(string $body, WhatsAppSession $session, Collection $products): array
    {
        $cart = $session->cart ?? [];

        $productList = $products->map(fn ($p) => "{$p->product_name} - Rp".number_format($p->sale_price, 0, ',', '.')."/{$p->sale_unit} (stok: {$p->stock})"
        )->join("\n");

        $cartSummary = empty($cart)
            ? 'Keranjang kosong.'
            : collect($cart)->map(fn ($i) => "- {$i['product_name']}: {$i['qty']} {$i['unit']}")->join("\n");

        $history = $session->messages ?? [];
        $messages = array_merge(
            [[
                'role' => 'system',
                'content' => "Kamu adalah asisten penjualan Toko Sentosa, toko grosir peralatan rumah tangga.

⚠️ PENTING: Gunakan HANYA daftar produk yang diberikan di bawah ini. Jangan gunakan informasi produk (harga, stok, satuan) dari percakapan sebelumnya karena data stok bisa berubah sewaktu-waktu. Data di bawah ini adalah data TERBARU dari database.

=== DATA PRODUK TERBARU (dari database, diperbarui sekarang) ===
{$productList}
=== AKHIR DATA PRODUK ===

Keranjang saat ini:
{$cartSummary}

Panduan:
- Gunakan HANYA harga dan stok dari daftar produk di atas, bukan dari riwayat percakapan.
- Cocokkan nama produk yang mirip jika tidak ada yang persis sama.
- Jika pembeli mau memesan, tambahkan ke cart_add.
- Jika pelanggan menggunakan kata 'tambah', 'pesan', 'mau', 'beli', 'order' diikuti nama produk dan jumlah, langsung tambahkan ke cart_add TANPA tanya konfirmasi lagi.
- Tampilkan gambar (show_products) jika pembeli menanyakan atau ingin melihat produk tertentu.
- Jika pelanggan bertanya produk apa saja yang tersedia (contoh: ada apa, ada barang apa, produk apa saja, ada apa lagi), tampilkan MAKSIMAL 10 produk saja dalam format singkat satu baris: \`- Nama Produk - Rp harga/satuan\`, lalu tambahkan catatan (ketik nama produk untuk info lebih detail). JANGAN tampilkan semua produk sekaligus karena akan terlalu panjang.
- Jawab singkat dan ramah dalam Bahasa Indonesia.

Balas dalam format JSON:
{
  \"reply\": \"teks balasan\",
  \"show_products\": [\"nama produk\"],
  \"cart_add\": [{\"product_name\": \"nama\", \"qty\": angka, \"unit\": \"satuan\"}]
}",
            ]],
            array_slice($history, -self::MAX_HISTORY),
            [['role' => 'user', 'content' => $body]]
        );

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
        ]);

        $raw = $response->choices[0]->message->content;
        $result = json_decode($raw, true) ?? [];

        $reply = $result['reply'] ?? $raw;
        $cartAdd = $result['cart_add'] ?? [];
        $showNames = $result['show_products'] ?? [];

        $productsToShow = collect($showNames)
            ->take(self::MAX_IMAGES)
            ->map(fn ($name) => Product::where('stock', '>', 0)
                ->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($name).'%'])
                ->first())
            ->filter(fn ($p) => $p && $p->image);

        return [$reply, $productsToShow, $cartAdd];
    }

    // ─── Product search with fuzzy word-split fallback ───────────────────────

    private function searchProduct(string $name): Collection
    {
        // Pass 0: exact (case-insensitive) name match wins outright, even
        // when a LIKE/fuzzy pass below would also match other similarly
        // named products — e.g. typing "KOBOKAN 03" must never trigger a
        // clarification prompt just because "KOBOKAN 013" also contains it.
        // Only acts when exactly one row matches exactly; if two or more
        // products share the identical name (case-insensitive) — a data
        // duplication issue, not real customer-facing ambiguity — this
        // falls through to the fuzzy passes below instead of guessing which
        // duplicate to silently pick.
        $exact = Product::where('stock', '>', 0)
            ->whereRaw('LOWER(product_name) = ?', [strtolower(trim($name))])
            ->get();

        if ($exact->count() === 1) {
            return $exact;
        }

        // Pass 1: exact phrase LIKE
        $found = Product::where('stock', '>', 0)
            ->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($name).'%'])
            ->get();

        if ($found->isNotEmpty()) {
            return $found;
        }

        $words = array_values(array_filter(
            explode(' ', preg_replace('/\s+/', ' ', trim($name))),
            fn ($w) => mb_strlen($w) >= 3
        ));

        if (empty($words)) {
            return collect();
        }

        // Pass 2: AND — product must contain ALL words
        $andFound = Product::where('stock', '>', 0)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($word).'%']);
                }
            })
            ->get();

        if ($andFound->isNotEmpty()) {
            return $andFound;
        }

        // Pass 3: OR fallback — handles typos/partial names
        return Product::where('stock', '>', 0)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($word).'%']);
                }
            })
            ->get();
    }

    // ─── Clarification resolver ───────────────────────────────────────────────

    private function resolveClarification(string $body, WhatsAppSession $session): array
    {
        $pending = $session->pending_clarification ?? [];
        $cart = $session->cart ?? [];
        $added = [];
        $stillPending = [];

        $lowerBody = strtolower(trim($body));

        // Ignore pure stop-words so "yang kecil" → keywords = ["kecil"]
        $stopWords = ['yang', 'mau', 'itu', 'ini', 'saja', 'deh', 'dong', 'kak', 'pak', 'bu'];
        $keywords = array_values(array_filter(
            explode(' ', preg_replace('/[^a-zA-Z0-9\s]/u', ' ', $lowerBody)),
            fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stopWords)
        ));

        // Check if customer typed a position number: "1", "2", "yang pertama",
        // etc. Only treated as a position pick when the WHOLE message is a
        // short selection reply — otherwise a lone digit anywhere inside an
        // unrelated longer message (e.g. "2" from "KOBOKAN 03 2 Lusin, ...")
        // would wrongly be read as "pelanggan memilih opsi ke-2" and silently
        // resolve every still-pending item using stale leftover qty/unit.
        $pickedIndex = null;
        if (mb_strlen(trim($body)) <= 20) {
            preg_match('/\b([1-9])\b/', $body, $numMatch);
            $pickedIndex = isset($numMatch[1]) ? ((int) $numMatch[1] - 1) : null;
        }

        foreach ($pending as $item) {
            $options = $item['options']; // [['id' => X, 'name' => 'Y'], ...]

            // Number pick takes priority
            if ($pickedIndex !== null && isset($options[$pickedIndex])) {
                $opt = $options[$pickedIndex];
                $product = Product::where('id', $opt['id'])->where('stock', '>', 0)->first();
                if ($product) {
                    $cart = $this->addToCart($cart, $product, $item['qty'], $item['unit'], true);
                    $added[] = $product->product_name;
                }

                continue;
            }

            // Keyword matching — score each option by how many keywords appear in its name
            $scores = [];
            foreach ($options as $idx => $opt) {
                $optLower = strtolower($opt['name']);
                $score = 0;
                foreach ($keywords as $kw) {
                    if (str_contains($optLower, $kw)) {
                        $score++;
                    }
                }
                if ($score > 0) {
                    $scores[$idx] = $score;
                }
            }

            if (! empty($scores)) {
                arsort($scores);
                $topScore = reset($scores);
                $topMatches = array_keys(array_filter($scores, fn ($s) => $s === $topScore));

                if (count($topMatches) === 1) {
                    $opt = $options[$topMatches[0]];
                    $product = Product::where('id', $opt['id'])->where('stock', '>', 0)->first();
                    if ($product) {
                        $cart = $this->addToCart($cart, $product, $item['qty'], $item['unit'], true);
                        $added[] = $product->product_name;
                    }
                } else {
                    $stillPending[] = $item; // still ambiguous after reply
                }
            } else {
                $stillPending[] = $item; // no keyword matched — keep pending
            }
        }

        if (empty($added)) {
            // Nothing resolved — let normal intent detection handle this message
            return [false, ''];
        }

        $session->cart = $cart;
        $session->pending_clarification = empty($stillPending) ? null : array_values($stillPending);
        $session->save();

        $total = collect($cart)->sum('subtotal');
        $cartLines = collect($cart)->map(function ($i) {
            $qty = (fmod((float) $i['qty'], 1) === 0.0) ? (int) $i['qty'] : $i['qty'];
            $unit = ucfirst(strtolower((string) ($i['unit'] ?? '')));

            return "• {$i['product_name']} × {$qty} {$unit} — Rp".number_format($i['subtotal'], 0, ',', '.');
        })->join("\n");

        $reply = '✅ Ditambahkan: *'.implode(', ', $added).'*';
        $reply .= "\n\n🛒 *Semua item di keranjang:*\n{$cartLines}";
        $reply .= "\n\n💰 *Total: Rp".number_format($total, 0, ',', '.').'*';

        if (! empty($stillPending)) {
            foreach ($stillPending as $item) {
                $opts = collect($item['options'])->map(fn ($o) => "  • {$o['name']}")->join("\n");
                $reply .= "\n\n❓ *{$item['original']}* masih perlu diperjelas:\n{$opts}";
            }
        } else {
            $reply .= "\n\nKetik *selesai* untuk konfirmasi atau tambah produk lagi.";
        }

        return [true, $reply];
    }

    // ─── Bulk order mode ──────────────────────────────────────────────────────

    private function handleBulkOrder(array $bulkItems, WhatsAppSession $session): array
    {
        $cart = $session->cart ?? [];
        $matched = [];
        $clarify = [];
        $notFound = [];

        $pending = [];

        foreach ($bulkItems as $item) {
            $name = $item['item'] ?? '';
            $qty = (float) ($item['qty'] ?? 1);
            $unit = $item['unit'] ?? 'buah';

            \Log::debug('WhatsApp bulk item extracted', ['name' => $name, 'qty' => $qty, 'unit' => $unit]);

            $found = $this->searchProduct($name);

            if ($found->count() === 1) {
                $product = $found->first();
                [$convertedQty, $saleUnit, , $subtotal] = $this->convertUnit($qty, $unit, $product);
                $cart = $this->addToCart($cart, $product, $qty, $unit, true); // replace, not accumulate

                // Display uses customer's original unit/qty (same-unit) or converted (different-unit)
                $customerUnitLower = strtolower(trim($unit));
                $saleUnitLower = strtolower(trim((string) ($product->sale_unit ?? '')));
                if ($customerUnitLower === $saleUnitLower || $saleUnitLower === '') {
                    $displayQty = $qty;
                    $displayUnit = ucfirst($customerUnitLower);
                } else {
                    $displayQty = (fmod($convertedQty, 1) === 0.0) ? (int) $convertedQty : $convertedQty;
                    $displayUnit = ucfirst($saleUnitLower);
                }
                $matched[] = "✅ {$product->product_name} × {$displayQty} {$displayUnit} — Rp"
                           .number_format($subtotal, 0, ',', '.');

            } elseif ($found->count() > 1) {
                $options = $found->take(3)
                    ->map(fn ($p) => ['id' => $p->id, 'name' => $p->product_name])
                    ->values()->all();
                $optText = collect($options)->map(fn ($o) => "  • {$o['name']}")->join("\n");
                $clarify[] = "❓ *{$name}* ada beberapa pilihan:\n{$optText}\nBalas dengan pilihan Anda (contoh: \"yang kecil\", \"yang besar\", atau \"1\"/\"2\")";

                $pending[] = [
                    'original' => $name,
                    'options' => $options,
                    'qty' => $qty,
                    'unit' => $unit,
                ];
            } else {
                $notFound[] = "❌ {$name} — tidak ditemukan";
            }
        }

        $session->cart = $cart;
        $session->pending_clarification = ! empty($pending) ? $pending : null;
        $session->save();

        $lines = ['📋 *Rekap Pesanan:*'];

        if (! empty($matched)) {
            $lines[] = "\n\n*Baru ditambahkan:*\n".implode("\n", $matched);
        }
        if (! empty($clarify)) {
            $lines[] = "\n\n*Perlu klarifikasi:*\n".implode("\n\n", $clarify);
        }
        if (! empty($notFound)) {
            $lines[] = "\n\n*Tidak ditemukan:*\n".implode("\n", $notFound);
        }

        if (! empty($cart)) {
            $total = collect($cart)->sum('subtotal');
            $cartLines = collect($cart)->map(function ($i) {
                $qty = (fmod((float) $i['qty'], 1) === 0.0) ? (int) $i['qty'] : $i['qty'];
                $unit = ucfirst(strtolower((string) ($i['unit'] ?? '')));

                return "• {$i['product_name']} × {$qty} {$unit} — Rp".number_format($i['subtotal'], 0, ',', '.');
            })->join("\n");

            $lines[] = "\n\n🛒 *Semua item di keranjang:*\n{$cartLines}";
            $lines[] = "\n\n💰 *Total: Rp".number_format($total, 0, ',', '.').'*';
            $lines[] = "\nKetik *selesai* untuk konfirmasi atau tambah produk lagi.";
        }

        return [implode('', $lines), collect()];
    }

    // ─── Create Sale + SaleDetails + decrement stock ──────────────────────────

    private function createOrder(WhatsAppSession $session): array
    {
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            return ['Keranjang kosong. Silakan pilih produk terlebih dahulu.', collect()];
        }

        $sale = null;

        DB::transaction(function () use ($session, $cart, &$sale) {
            $customer = Customer::firstOrCreate(
                ['phone' => $session->phone_number],
                [
                    'customer_name' => $session->customer_name ?? 'Pelanggan WhatsApp',
                    'address' => $session->address ?? '',
                ]
            );

            // Keep customer info current
            $customer->update([
                'customer_name' => $session->customer_name ?? $customer->customer_name,
                'address' => $session->address ?? $customer->address,
            ]);

            $total = collect($cart)->sum('subtotal');

            $sale = Sale::create([
                'customer_id' => $customer->id,
                'user_id' => 1,
                'sale_date' => now()->toDateString(),
                'payment_method' => $session->payment_method ?? 'Tunai',
                'payment_status' => 'Belum Bayar',
                'total_amount' => $total,
                'is_new' => true,
                'notes' => 'Pesanan via WhatsApp ('.$session->phone_number.')',
            ]);

            foreach ($cart as $item) {
                SaleDetail::create([
                    'sale_id' => $sale->sale_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'discount' => 0,
                    'subtotal' => $item['subtotal'],
                ]);
                // Stock is NOT decremented here — it's only decremented once the
                // owner clicks "Proses" on the dashboard (see SaleController::tandaiDiproses).
            }
        });

        $total = collect($cart)->sum('subtotal');
        $invoiceNo = str_pad($sale->sale_id, 6, '0', STR_PAD_LEFT);
        $itemList = collect($cart)
            ->map(function ($i) {
                $qty = (fmod((float) $i['qty'], 1) === 0.0) ? (int) $i['qty'] : $i['qty'];
                $unit = ucfirst(strtolower((string) ($i['unit'] ?? '')));

                return "- {$i['product_name']}: {$qty} {$unit}";
            })
            ->join("\n");

        $session->cart = [];
        $session->pending_clarification = null;
        $session->step = 'chat';
        $session->save();

        $transferInfo = '';
        if (($session->payment_method ?? '') === 'Transfer Bank') {
            $transferInfo = "\n\n💳 *Silakan transfer ke:*\n"
                          ."🏦 Bank BCA\n"
                          ."📋 No. Rekening: *1234567890*\n"
                          ."👤 Atas Nama: *Toko Sentosa*\n\n"
                          .'Konfirmasi pembayaran dengan kirim bukti transfer ke nomor ini.';
        }

        $reply = "✅ *Pesanan Berhasil Dibuat!*\n\n"
               ."🧾 No. Invoice: *INV-{$invoiceNo}*\n"
               ."👤 Nama: {$session->customer_name}\n"
               ."📍 Alamat: {$session->address}\n"
               ."💳 Pembayaran: {$session->payment_method}\n\n"
               ."*Produk:*\n{$itemList}\n\n"
               .'💰 *Total: Rp'.number_format($total, 0, ',', '.')."*\n"
               .'📋 Status: Belum Bayar'
               .$transferInfo
               ."\n\nTerima kasih sudah berbelanja di Toko Sentosa! 🙏";

        return [$reply, collect()];
    }

    // ─── Unit conversion ──────────────────────────────────────────────────────

    /**
     * Convert customer qty in their unit to product sale_unit qty and price.
     * Returns [converted_qty, unit_used, price_per_unit, subtotal].
     */
    private function convertUnit(float $customerQty, string $customerUnit, Product $product): array
    {
        $unitUsed = $product->sale_unit ?: $customerUnit;

        // sale_price is quoted per sale_unit, so the quantity multiplied
        // against it (and stored on SaleDetail) must stay in sale_unit
        // terms — not the true base/Buah quantity UnitConverter::toBaseUnit
        // now returns. Route through true base units then back out to
        // sale_unit terms so this stays correct regardless of which unit
        // the customer actually ordered in.
        $qtyInBase = UnitConverter::toBaseUnit($customerQty, $customerUnit, $product);
        $convertedQty = UnitConverter::fromBaseUnit($qtyInBase, $unitUsed, $product);

        $subtotal = (float) $product->sale_price * $convertedQty;

        return [$convertedQty, $unitUsed, (float) $product->sale_price, $subtotal];
    }

    // ─── Cart helper ──────────────────────────────────────────────────────────

    private function addToCart(array $cart, Product $product, float $qty, string $unit, bool $replace = false): array
    {
        [$convertedQty, $saleUnit, $price, $subtotal] = $this->convertUnit($qty, $unit, $product);

        foreach ($cart as &$item) {
            if ($item['product_id'] === $product->id) {
                if ($replace) {
                    $item['qty'] = $convertedQty;
                } else {
                    $item['qty'] += $convertedQty;
                }
                $item['unit'] = $saleUnit;
                $item['price'] = $price;
                $item['subtotal'] = $price * $item['qty'];

                return $cart;
            }
        }

        $cart[] = [
            'product_id' => $product->id,
            'product_name' => $product->product_name,
            'qty' => $convertedQty,
            'unit' => $saleUnit,
            'price' => $price,
            'subtotal' => $subtotal,
        ];

        return $cart;
    }

    private function formatCartSummary(array $cart): string
    {
        $total = collect($cart)->sum('subtotal');
        $itemList = collect($cart)
            ->map(function ($i) {
                $qty = (fmod((float) $i['qty'], 1) === 0.0) ? (int) $i['qty'] : $i['qty'];
                $unit = ucfirst(strtolower((string) ($i['unit'] ?? '')));

                return "- {$i['product_name']}: {$qty} {$unit} — Rp".number_format($i['subtotal'], 0, ',', '.');
            })
            ->join("\n");

        return "🛒 *Ringkasan Pesanan:*\n{$itemList}\n\n💰 *Total: Rp".number_format($total, 0, ',', '.').'*';
    }

    // ─── Send helpers ─────────────────────────────────────────────────────────

    private function sendMessage(string $to, string $body, ?string $mediaUrl = null): void
    {
        $provider = env('WHATSAPP_PROVIDER', 'twilio');

        if ($provider === 'fonnte') {
            $this->sendViaFonnte($to, $body, $mediaUrl);
        } elseif ($provider === 'meta') {
            $this->sendViaMeta($to, $body, $mediaUrl);
        } else {
            $this->sendViaTwilio($to, $body, $mediaUrl);
        }
    }

    private function sendViaFonnte(string $to, string $body, ?string $mediaUrl = null): void
    {
        // Fonnte's "target" is the bare number (62xxx), no whatsapp:/+ prefix —
        // same normalized $to format every provider's send method receives.
        $target = preg_replace('/^whatsapp:\+?/', '', $to);

        $payload = [
            'target' => $target,
            'message' => $body,
        ];

        if ($mediaUrl) {
            $payload['url'] = $mediaUrl;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => config('services.fonnte.token')])
                ->timeout(15)
                ->connectTimeout(10)
                // One retry after a transient network/timeout failure only —
                // never retries on a 4xx/5xx response from Fonnte itself.
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException)
                ->post('https://api.fonnte.com/send', $payload);

            if (! $response->successful()) {
                Log::error('Fonnte send failed', [
                    'to' => $target,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (ConnectionException $e) {
            Log::error('Fonnte send failed: connection error after retry', [
                'to' => $target,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendViaTwilio(string $to, string $body, ?string $mediaUrl = null): void
    {
        $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
        $from = env('TWILIO_WHATSAPP_FROM');

        // Split long messages at newlines to stay under Twilio's 1600-char limit
        if (strlen($body) > 1500 && ! $mediaUrl) {
            foreach ($this->splitMessage($body) as $chunk) {
                $twilio->messages->create($to, ['from' => $from, 'body' => $chunk]);
            }

            return;
        }

        $params = ['from' => $from, 'body' => $body];

        if ($mediaUrl) {
            $params['mediaUrl'] = [$mediaUrl];
        }

        $twilio->messages->create($to, $params);
    }

    private function splitMessage(string $body, int $limit = 1500): array
    {
        $lines = explode("\n", $body);
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            $candidate = $current === '' ? $line : $current."\n".$line;

            if (strlen($candidate) > $limit) {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                // Single line longer than limit — hard-cut it
                $current = strlen($line) > $limit
                    ? substr($line, 0, $limit)
                    : $line;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks ?: [$body];
    }

    private function sendViaMeta(string $to, string $body, ?string $mediaUrl = null): void
    {
        $phone = preg_replace('/^whatsapp:\+?/', '', $to);
        $phoneNumberId = env('META_PHONE_NUMBER_ID');
        $accessToken = env('META_ACCESS_TOKEN');

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
        ];

        if ($mediaUrl) {
            $payload['type'] = 'image';
            $payload['image'] = ['link' => $mediaUrl, 'caption' => $body];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['body' => $body];
        }

        Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $payload);
    }

    // ─── Incoming message parsers ─────────────────────────────────────────────

    private function parseIncoming(Request $request): array
    {
        if ($request->input('object') === 'whatsapp_business_account') {
            return $this->parseMetaIncoming($request);
        }
        if ($request->has('sender') || $request->has('device')) {
            return $this->parseFonnteIncoming($request);
        }

        return $this->parseTwilioIncoming($request);
    }

    // Fonnte's WhatsApp buttons stopped working platform-wide when WhatsApp
    // changed how it verifies button senders (May 2023) — Fonnte no longer
    // maintains the feature. The webhook still always sends a "text" field,
    // but for an ordinary (non-button) message it's just this literal
    // sentinel, never the customer's actual message.
    private const FONNTE_NON_BUTTON_TEXT = 'non-button message';

    private function parseFonnteIncoming(Request $request): array
    {
        $sender = $request->input('sender');

        if (! $sender) {
            return [null, ''];
        }

        // Normalize to the same 'whatsapp:+62xxx' shape every other provider's
        // parser produces, so WhatsAppSession lookups stay provider-agnostic.
        $digits = preg_replace('/\D/', '', (string) $sender);
        $from = 'whatsapp:+'.$digits;

        // "text" only carries real content for an actual button/list reply —
        // otherwise it's always the "non-button message" placeholder, so it
        // must be ignored in that case and "message" used instead.
        $text = trim((string) $request->input('text', ''));
        $isRealButton = $text !== '' && strtolower($text) !== self::FONNTE_NON_BUTTON_TEXT;
        $body = $isRealButton ? $text : $request->input('message', '');

        return [$from, (string) $body];
    }

    private function parseTwilioIncoming(Request $request): array
    {
        $from = $request->input('From');
        $body = $request->input('Body', '');
        $mediaUrl = $request->input('MediaUrl0');
        $mediaType = $request->input('MediaContentType0', '');

        if ($mediaUrl && (str_contains($mediaType, 'audio') || str_contains($mediaType, 'ogg'))) {
            $transcribed = $this->transcribeVoiceNote($mediaUrl);

            if ($transcribed === null) {
                $this->sendMessage($from, 'Maaf, kami tidak dapat memproses pesan suara Anda. Silakan kirim pesan teks.');

                return [null, ''];
            }

            Log::info('[Voice Note] Transcribed', ['from' => $from, 'text' => $transcribed]);
            $body = '[Voice Note] '.$transcribed;
        }

        return [$from, $body];
    }

    private function parseMetaIncoming(Request $request): array
    {
        $value = $request->input('entry.0.changes.0.value', []);
        $message = data_get($value, 'messages.0');

        if (! $message) {
            return [null, ''];
        }

        $from = 'whatsapp:+'.$message['from'];
        $type = $message['type'] ?? 'text';
        $body = '';

        if ($type === 'text') {
            $body = data_get($message, 'text.body', '');
        } elseif ($type === 'audio') {
            $mediaId = data_get($message, 'audio.id');
            $transcribed = $mediaId ? $this->transcribeMetaVoiceNote($mediaId) : null;

            if ($transcribed === null) {
                $this->sendMessage($from, 'Maaf, kami tidak dapat memproses pesan suara Anda. Silakan kirim pesan teks.');

                return [null, ''];
            }

            Log::info('[Voice Note Meta] Transcribed', ['from' => $from, 'text' => $transcribed]);
            $body = '[Voice Note] '.$transcribed;
        }

        return [$from, $body];
    }

    private function transcribeMetaVoiceNote(string $mediaId): ?string
    {
        try {
            Storage::makeDirectory('temp');
            $tempPath = storage_path('app/temp/voice_'.uniqid().'.ogg');
            $accessToken = env('META_ACCESS_TOKEN');

            $urlResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v18.0/{$mediaId}");

            if (! $urlResponse->successful()) {
                Log::error('Meta media URL fetch failed', ['media_id' => $mediaId]);

                return null;
            }

            $audioResponse = Http::withToken($accessToken)->get($urlResponse->json('url'));

            if (! $audioResponse->successful()) {
                Log::error('Meta audio download failed', ['media_id' => $mediaId]);

                return null;
            }

            file_put_contents($tempPath, $audioResponse->body());

            $result = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($tempPath, 'r'),
                'language' => 'id',
            ]);

            @unlink($tempPath);

            return trim($result->text);

        } catch (\Throwable $e) {
            Log::error('Meta Whisper transcription failed', ['error' => $e->getMessage()]);
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return null;
        }
    }
}
