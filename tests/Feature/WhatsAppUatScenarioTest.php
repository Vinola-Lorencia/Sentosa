<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Models\WhatsAppSession;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Audio;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * UAT-style scenarios for the WhatsApp chatbot, driven end to end through
 * POST /webhook/whatsapp with Fonnte and OpenAI faked. Each test is one
 * customer scenario (A–L, plus A2/F2 found while reading the code).
 *
 * Expectations describe what a customer/owner would reasonably expect; a
 * failing test documents real behaviour and is intentionally NOT bent to
 * pass. Multi-check scenarios collect every deviation into $problems and
 * assert at the end, so one failure doesn't hide the next.
 */
class WhatsAppUatScenarioTest extends TestCase
{
    use RefreshDatabase;

    private const SENDER = '6281234567890';

    private const PHONE = 'whatsapp:+6281234567890';

    private const AUDIO_URL = 'https://files.fonnte.test/media/voice1.ogg';

    private const UNSUPPORTED_REPLY = 'Maaf, saya hanya bisa membaca pesan teks atau voice note. Silakan ketik produk yang ingin dipesan ya 🙏';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token', 'services.whatsapp.webhook_secret' => 'test-webhook-secret']);
        $this->setEnv('WHATSAPP_PROVIDER', 'fonnte');

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200),
            'files.fonnte.test/*' => Http::response('OggS-fake-audio-bytes', 200),
            'broken.fonnte.test/*' => Http::response('gone', 500),
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['WHATSAPP_PROVIDER', 'FILESYSTEM_URL'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(string $name, float $price, string $saleUnit, float $stock, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => $name,
            'sale_price' => $price,
            'sale_unit' => $saleUnit,
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => $stock,
            'default_buy_price' => 0,
            'default_buy_discount' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function send(string $message, string $sender = self::SENDER, array $extra = []): void
    {
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', array_merge([
            'device' => '6281111111111',
            'sender' => $sender,
            'message' => $message,
            'text' => 'non-button message',
        ], $extra))->assertStatus(200);
    }

    /**
     * @param  list<array<string, mixed>>  $bulkItems
     * @param  list<string>  $keywords
     */
    private function intent(string $mode, array $keywords = [], array $bulkItems = []): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['mode' => $mode, 'keywords' => $keywords, 'bulk_items' => $bulkItems]),
        ]]]]);
    }

    /**
     * @param  list<string>  $showProducts
     * @param  list<array<string, mixed>>  $cartAdd
     */
    private function chatReply(string $reply, array $showProducts = [], array $cartAdd = []): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['reply' => $reply, 'show_products' => $showProducts, 'cart_add' => $cartAdd]),
        ]]]]);
    }

    /**
     * Every Fonnte /send call, in order.
     *
     * @return list<array{target: string, message: string, url: ?string}>
     */
    private function sentMessages(?string $target = null): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send')
            ->map(fn (HttpRequest $r) => [
                'target' => (string) $r['target'],
                'message' => (string) $r['message'],
                'url' => $r['url'] ?? null,
            ])
            ->filter(fn ($m) => $target === null || $m['target'] === $target)
            ->values()
            ->all();
    }

    private function lastReply(?string $target = null): string
    {
        $messages = $this->sentMessages($target);

        return $messages === [] ? '' : end($messages)['message'];
    }

    /**
     * @param  list<array<string, mixed>>  $cart
     * @param  array<string, mixed>  $extra
     */
    private function seedSession(array $cart, string $step, array $extra = [], string $phone = self::PHONE): WhatsAppSession
    {
        return WhatsAppSession::create(array_merge([
            'phone_number' => $phone,
            'step' => $step,
            'cart' => $cart,
            'messages' => [],
        ], $extra));
    }

    /**
     * @return array<string, mixed>
     */
    private function cartLine(Product $product, float $qty): array
    {
        return [
            'product_id' => $product->id,
            'product_name' => $product->product_name,
            'qty' => $qty,
            'unit' => $product->sale_unit,
            'price' => (float) $product->sale_price,
            'subtotal' => (float) $product->sale_price * $qty,
        ];
    }

    /**
     * @param  list<string>  $problems
     */
    private function assertNoProblems(array $problems): void
    {
        $this->assertSame([], $problems, "Perilaku tidak sesuai harapan:\n- ".implode("\n- ", $problems));
    }

    // ─── A. Tanya produk ──────────────────────────────────────────────────────

    public function test_a_tanya_produk_harga_dan_stok_dari_database(): void
    {
        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7);
        $this->product('KURSI SANTAI NO.12', 95000, 'Buah', 4);
        $this->product('EMBER 10L', 12000, 'Buah', 50);

        // GPT replies with an intro only — the DB-built list must supply the facts.
        OpenAI::fake([
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Ada kak, berikut kursi santai yang tersedia:'),
        ]);

        $this->send('kursi santai no.10 ada?');

        $problems = [];

        $prompts = [];
        OpenAI::assertSent(Chat::class, function (string $method, array $params) use (&$prompts) {
            $prompts[] = $params['messages'][0]['content'];

            return true;
        });
        $stage2Prompt = $prompts[1] ?? '';

        if (! str_contains($stage2Prompt, 'KURSI SANTAI NO.10 - Rp85.000/Buah (stok: 7 Buah)')) {
            $problems[] = 'Prompt tahap 2 tidak memuat harga/stok KURSI SANTAI NO.10 dari database';
        }
        if (str_contains($stage2Prompt, 'EMBER 10L')) {
            $problems[] = 'Prompt tahap 2 memuat produk yang tidak relevan (EMBER 10L)';
        }

        $reply = $this->lastReply();
        if (! str_contains($reply, '- KURSI SANTAI NO.10 - Rp85.000/Buah (stok: 7 Buah)')) {
            $problems[] = "Balasan ke pelanggan tidak memuat harga & stok yang benar. Balasan: {$reply}";
        }

        $this->assertNoProblems($problems);
    }

    public function test_a2_harga_karangan_gpt_tidak_dikoreksi_sistem(): void
    {
        // Tambahan: GPT "mengarang" harga (Rp50.000, padahal DB Rp85.000).
        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7);

        OpenAI::fake([
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Ada kak, KURSI SANTAI NO.10 harganya Rp50.000, stok 20.'),
        ]);

        $this->send('kursi santai no.10 ada?');

        $reply = $this->lastReply();
        $this->assertStringNotContainsString(
            'Rp50.000',
            $reply,
            "Harga keliru dari GPT diteruskan apa adanya ke pelanggan. Balasan: {$reply}"
        );
    }

    // ─── B. Minta foto ────────────────────────────────────────────────────────

    public function test_b_minta_foto_produk_dikirim(): void
    {
        // Non-localhost base URL so the controller attempts a real media send.
        $this->setEnv('FILESYSTEM_URL', 'https://sentosa.test');

        // Photos uploaded via the current product form only fill image_path
        // (Supabase); the legacy `image` column stays null.
        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7, [
            'image' => null,
            'image_path' => 'products/product_kursi10.jpg',
        ]);

        OpenAI::fake([
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Ini fotonya kak.', ['KURSI SANTAI NO.10']),
        ]);

        $this->send('foto kursi santai');

        $mediaMessages = array_values(array_filter($this->sentMessages(), fn ($m) => $m['url'] !== null));

        $this->assertNotEmpty(
            $mediaMessages,
            'Tidak ada pesan bergambar yang dikirim ke Fonnte walau GPT mengisi show_products. '
            .'Pesan terkirim: '.json_encode($this->sentMessages(), JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_b2_produk_lama_tanpa_image_path_tetap_memakai_kolom_image(): void
    {
        // Kompatibilitas: legacy rows that only have the old local `image`.
        $this->setEnv('FILESYSTEM_URL', 'https://sentosa.test');

        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7, [
            'image' => 'products/kursi10-lama.jpg',
            'image_path' => null,
        ]);

        OpenAI::fake([
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Ini fotonya kak.', ['KURSI SANTAI NO.10']),
        ]);

        $this->send('foto kursi santai');

        $urls = array_values(array_filter(array_column($this->sentMessages(), 'url')));

        $this->assertSame(['https://sentosa.test/storage_link/products/kursi10-lama.jpg'], $urls);
    }

    // ─── C. Pesanan banyak item ───────────────────────────────────────────────

    public function test_c_pesanan_banyak_item_konversi_satuan_dan_total(): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $piring = $this->product('PIRING MAKAN', 25000, 'Lusin', 240);

        OpenAI::fake([
            $this->intent('bulk', ['kobokan', 'piring'], [
                ['item' => 'kobokan 03', 'qty' => 2, 'unit' => 'lusin'],
                ['item' => 'piring', 'qty' => 1, 'unit' => 'kodi'],
            ]),
        ]);

        $this->send('kobokan 03 2 lusin, piring 1 kodi');

        $problems = [];
        $cart = collect(WhatsAppSession::where('phone_number', self::PHONE)->first()?->cart ?? []);

        $kobokanLine = $cart->firstWhere('product_id', $kobokan->id);
        $piringLine = $cart->firstWhere('product_id', $piring->id);

        if ($cart->count() !== 2) {
            $problems[] = 'Keranjang berisi '.$cart->count().' baris, harapan 2';
        }
        if (! $kobokanLine || (float) $kobokanLine['qty'] !== 2.0 || strtolower($kobokanLine['unit']) !== 'lusin' || (float) $kobokanLine['subtotal'] !== 30000.0) {
            $problems[] = 'KOBOKAN 03 tidak tersimpan sebagai 2 Lusin / Rp30.000: '.json_encode($kobokanLine);
        }

        // 1 Kodi = 20 buah = 1,667 Lusin. Rule used everywhere else in the app
        // (UnitConverter::priceForUnit — admin Sales form, toko cart):
        // 25.000 × 20/12 = 41.666,67 → rounded UP to Rp42.000.
        if (! $piringLine) {
            $problems[] = 'PIRING MAKAN tidak masuk keranjang';
        } else {
            $piringBuah = (float) $piringLine['qty'] * 12;
            if (abs($piringBuah - 20) > 0.01) {
                $problems[] = "PIRING MAKAN setara {$piringBuah} buah, harapan 20 buah (1 Kodi)";
            }
            if ((float) $piringLine['subtotal'] !== 42000.0) {
                $problems[] = 'Subtotal PIRING MAKAN '.$piringLine['subtotal'].', harapan Rp42.000 (aturan pembulatan UnitConverter::priceForUnit yang dipakai toko & form penjualan)';
            }
        }

        $reply = $this->lastReply();
        if (preg_match('/\d\.\d{4,}/', $reply)) {
            $problems[] = "Balasan menampilkan angka desimal mentah. Balasan: {$reply}";
        }
        if (! str_contains($reply, 'Total: Rp')) {
            $problems[] = 'Balasan tidak menampilkan total';
        }

        $this->assertNoProblems($problems);
    }

    // ─── D. Nama ambigu → klarifikasi ─────────────────────────────────────────

    public function test_d_nama_ambigu_klarifikasi_angka_kata_dan_angka_acak(): void
    {
        $kecil = $this->product('BASKOM KECIL', 18000, 'Lusin', 240);
        $this->product('BASKOM BESAR', 36000, 'Lusin', 240);

        $ambiguousIntent = fn () => $this->intent('bulk', ['baskom'], [
            ['item' => 'baskom', 'qty' => 2, 'unit' => 'lusin'],
        ]);

        OpenAI::fake([
            $ambiguousIntent(), // phone 1: order
            $ambiguousIntent(), // phone 2: order
            $ambiguousIntent(), // phone 3: order
            $this->intent('chat'), // phone 3: long unrelated message
            $this->chatReply('Toko buka sampai jam 5 sore kak.'),
        ]);

        $problems = [];

        // D1 — pick by number.
        $this->send('baskom 2 lusin', '6281000000001');
        if (! str_contains($this->lastReply('6281000000001'), 'Perlu klarifikasi')) {
            $problems[] = 'D1: tidak ada pertanyaan klarifikasi untuk "baskom"';
        }
        $this->send('1', '6281000000001');
        $s1 = WhatsAppSession::where('phone_number', 'whatsapp:+6281000000001')->first();
        $line1 = collect($s1->cart)->firstWhere('product_id', $kecil->id);
        if (! $line1 || (float) $line1['qty'] !== 2.0 || $s1->pending_clarification !== null) {
            $problems[] = 'D1: balasan "1" tidak memasukkan BASKOM KECIL 2 Lusin / klarifikasi belum bersih: '.json_encode($s1->only('cart', 'pending_clarification'));
        }

        // D2 — pick by word.
        $this->send('baskom 2 lusin', '6281000000002');
        $this->send('yang kecil', '6281000000002');
        $s2 = WhatsAppSession::where('phone_number', 'whatsapp:+6281000000002')->first();
        $line2 = collect($s2->cart)->firstWhere('product_id', $kecil->id);
        if (! $line2 || $s2->pending_clarification !== null) {
            $problems[] = 'D2: balasan "yang kecil" tidak memilih BASKOM KECIL: '.json_encode($s2->only('cart', 'pending_clarification'));
        }

        // D3 — a stray digit in a long unrelated message is NOT a pick.
        $this->send('baskom 2 lusin', '6281000000003');
        $this->send('kak toko di jl bengkalis 2 buka sampai jam berapa ya hari minggu', '6281000000003');
        $s3 = WhatsAppSession::where('phone_number', 'whatsapp:+6281000000003')->first();
        if (! empty($s3->cart) || empty($s3->pending_clarification)) {
            $problems[] = 'D3: angka di pesan panjang dibaca sebagai pilihan: '.json_encode($s3->only('cart', 'pending_clarification'));
        }

        $this->assertNoProblems($problems);
    }

    // ─── E. Jumlah melebihi stok ──────────────────────────────────────────────

    public function test_e_jumlah_melebihi_stok(): void
    {
        $ember = $this->product('EMBER 10L', 12000, 'Buah', 3);

        OpenAI::fake([
            $this->intent('bulk', ['ember'], [['item' => 'ember 10l', 'qty' => 50, 'unit' => 'buah']]),
        ]);

        $this->send('ember 10l 50 buah');

        $problems = [];
        $line = collect(WhatsAppSession::where('phone_number', self::PHONE)->first()->cart)->firstWhere('product_id', $ember->id);
        $reply = $this->lastReply();

        $warned = str_contains(mb_strtolower($reply), 'stok');
        if ($line && (float) $line['qty'] > 3 && ! $warned) {
            $problems[] = "Keranjang menerima {$line['qty']} buah padahal stok 3, tanpa peringatan stok. Balasan: {$reply}";
        }

        // Continue to checkout: does an order for 50 get created against stock 3?
        User::factory()->create(); // id 1 — createOrder() hardcodes user_id 1
        WhatsAppSession::where('phone_number', self::PHONE)->update([
            'step' => 'need_payment',
            'customer_name' => 'Budi',
            'address' => 'Ambil Sendiri di Toko',
        ]);
        $this->send('2');

        $sale = Sale::with('details')->latest('sale_id')->first();
        if ($sale && (float) $sale->details->sum('quantity') > 3) {
            $problems[] = 'Pesanan dibuat untuk '.$sale->details->sum('quantity').' buah walau stok hanya 3 (sale_id '.$sale->sale_id.')';
        }

        // The shortfall only surfaces when the owner presses "Proses".
        if ($sale) {
            $owner = User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => 'Pemilik'])->id]);
            $this->actingAs($owner)->patch(route('sales.tandai-diproses', $sale->sale_id))
                ->assertSessionHas('error');
            if ((float) $ember->fresh()->stock !== 3.0) {
                $problems[] = 'Stok berubah setelah Proses gagal';
            }
        }

        $this->assertNoProblems($problems);
    }

    // ─── F. Alur lengkap ──────────────────────────────────────────────────────

    public function test_f_alur_lengkap_sampai_pesanan_dibuat(): void
    {
        User::factory()->create(); // id 1 — createOrder() hardcodes user_id 1
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'chat');

        OpenAI::fake([$this->intent('done')]); // "selesai"

        $replies = [];
        foreach (['selesai', 'ya', 'Budi Santoso', 'Jl. Bengkalis II No. 91', '1'] as $message) {
            $this->send($message);
            $replies[$message] = $this->lastReply();
        }

        $problems = [];
        $session = WhatsAppSession::where('phone_number', self::PHONE)->first();
        $sale = Sale::with('details', 'customer')->first();

        if (! $sale) {
            $problems[] = 'Tidak ada sale dibuat. Balasan terakhir: '.$replies['1'];
        } else {
            if (! $sale->is_new) {
                $problems[] = 'is_new bukan true';
            }
            if ($sale->payment_status !== 'Belum Bayar') {
                $problems[] = "payment_status = {$sale->payment_status}";
            }
            if ($sale->payment_method !== 'Transfer Bank') {
                $problems[] = "payment_method = {$sale->payment_method}";
            }
            if ($sale->details->count() !== 1 || (float) $sale->details[0]->quantity !== 2.0 || (float) $sale->total_amount !== 30000.0) {
                $problems[] = 'sale_details/total tidak sesuai: '.json_encode($sale->details->toArray());
            }
            if ($sale->customer?->phone !== self::PHONE || $sale->customer?->customer_name !== 'Budi Santoso' || $sale->customer?->address !== 'Jl. Bengkalis II No. 91') {
                $problems[] = 'Customer tidak sesuai: '.json_encode($sale->customer);
            }
        }
        if ((float) $kobokan->fresh()->stock !== 240.0) {
            $problems[] = 'Stok sudah berkurang sebelum Proses: '.$kobokan->fresh()->stock;
        }
        if (! empty($session->cart) || $session->step !== 'chat') {
            $problems[] = 'Sesi tidak direset setelah pesanan dibuat';
        }
        if (str_contains($replies['1'], '1234567890')) {
            $problems[] = 'Pesan konfirmasi masih memuat nomor rekening contoh "1234567890"';
        }

        $this->assertNoProblems($problems);
    }

    public function test_f2_nama_dan_alamat_umum_yang_mengandung_kata_pemicu(): void
    {
        // Tambahan: "Surya" mengandung "ya", "Jl. Sisingamangaraja Raya" mengandung "ya".
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_name', [], 'whatsapp:+6281000000011');
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_address', ['customer_name' => 'Budi'], 'whatsapp:+6281000000012');

        // If the name/address is not captured, the message falls through to GPT.
        OpenAI::fake([
            $this->intent('chat'), $this->chatReply('Baik kak.'),
            $this->intent('chat'), $this->chatReply('Baik kak.'),
        ]);

        $this->send('Surya', '6281000000011');
        $this->send('Jl. Sisingamangaraja Raya No. 5', '6281000000012');

        $problems = [];
        $nameSession = WhatsAppSession::where('phone_number', 'whatsapp:+6281000000011')->first();
        $addressSession = WhatsAppSession::where('phone_number', 'whatsapp:+6281000000012')->first();

        if ($nameSession->customer_name !== 'Surya') {
            $problems[] = 'Nama "Surya" tidak disimpan (customer_name = '.json_encode($nameSession->customer_name).", step = {$nameSession->step})";
        }
        if ($addressSession->address !== 'Jl. Sisingamangaraja Raya No. 5') {
            $problems[] = 'Alamat "Jl. Sisingamangaraja Raya No. 5" tidak disimpan (address = '.json_encode($addressSession->address).", step = {$addressSession->step})";
        }

        $this->assertNoProblems($problems);
    }

    // ─── G. Batal di tengah alur ──────────────────────────────────────────────

    public function test_g_batal_di_tengah_alur_mengosongkan_keranjang_dan_klarifikasi(): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'confirm_order', [
            'pending_clarification' => [[
                'original' => 'baskom',
                'options' => [['id' => 98, 'name' => 'BASKOM KECIL'], ['id' => 99, 'name' => 'BASKOM BESAR']],
                'qty' => 1,
                'unit' => 'lusin',
            ]],
        ]);

        OpenAI::fake([$this->intent('cancel')]);

        $this->send('batal');

        $session = WhatsAppSession::where('phone_number', self::PHONE)->first();
        $this->assertSame([], $session->cart);
        $this->assertNull($session->pending_clarification);
        $this->assertSame('chat', $session->step);
        $this->assertSame('Pesanan dibatalkan. Ada yang bisa kami bantu lagi?', $this->lastReply());
        $this->assertSame(0, Sale::count());
    }

    // ─── H. Voice note ────────────────────────────────────────────────────────

    public function test_h_voice_note_berhasil_gagal_dan_stiker(): void
    {
        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7);

        OpenAI::fake([
            TranscriptionResponse::fake(['text' => 'Kursi santai no 10 ada?']),
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Ada kak, KURSI SANTAI NO.10 Rp85.000.'),
        ]);

        $problems = [];

        // H1 — success.
        $this->send('non-text message', '6281000000021', ['url' => self::AUDIO_URL, 'extension' => 'ogg']);
        $s1 = WhatsAppSession::where('phone_number', 'whatsapp:+6281000000021')->first();
        $lastUser = collect($s1?->messages ?? [])->last(fn ($m) => $m['role'] === 'user');
        if (($lastUser['content'] ?? null) !== '[Voice Note] Kursi santai no 10 ada?') {
            $problems[] = 'H1: transkripsi tidak masuk alur chat: '.json_encode($lastUser);
        }
        if ($this->lastReply('6281000000021') !== 'Ada kak, KURSI SANTAI NO.10 Rp85.000.') {
            $problems[] = 'H1: balasan tidak terkirim';
        }

        // H2 — download fails.
        $this->send('non-text message', '6281000000022', ['url' => 'https://broken.fonnte.test/v.ogg', 'extension' => 'ogg']);
        if ($this->lastReply('6281000000022') !== 'Maaf, kami tidak dapat memproses pesan suara Anda. Silakan kirim pesan teks.') {
            $problems[] = 'H2: balasan gagal transkripsi salah: '.$this->lastReply('6281000000022');
        }

        // H3 — sticker (.webp) and photo (.jpg) without caption.
        $this->send('non-text message', '6281000000023', ['url' => 'https://files.fonnte.test/media/sticker.webp', 'extension' => 'webp']);
        $this->send('non-text message', '6281000000024', ['url' => 'https://files.fonnte.test/media/photo.jpg', 'extension' => 'jpg']);
        foreach (['6281000000023' => 'stiker', '6281000000024' => 'foto'] as $sender => $label) {
            if ($this->lastReply($sender) !== self::UNSUPPORTED_REPLY) {
                $problems[] = "H3: {$label} tidak dibalas teks tetap: ".$this->lastReply($sender);
            }
        }

        // Only H1 may reach OpenAI: 1 transcription + 2 chat calls.
        OpenAI::assertSent(Audio::class, 1);
        OpenAI::assertSent(Chat::class, 2);

        $this->assertNoProblems($problems);
    }

    // ─── I. Webhook ganda ─────────────────────────────────────────────────────

    public function test_i_webhook_ganda_tidak_menduplikasi_keranjang_atau_pesanan(): void
    {
        User::factory()->create(); // id 1
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);

        $bulk = fn () => $this->intent('bulk', ['kobokan'], [['item' => 'kobokan 03', 'qty' => 2, 'unit' => 'lusin']]);
        $chatAdd = fn () => $this->chatReply('Siap, ditambahkan.', [], [['product_name' => 'KOBOKAN 03', 'qty' => 2, 'unit' => 'lusin']]);

        OpenAI::fake([
            $bulk(), $bulk(), // I1: bulk payload twice
            $this->intent('chat', ['kobokan']), $chatAdd(), // I2: chat cart_add payload twice
            $this->intent('chat', ['kobokan']), $chatAdd(),
            $this->intent('chat'), $this->chatReply('Ada lagi yang bisa dibantu?'), // I3: 2nd "1" after order
        ]);

        $problems = [];
        $qtyFor = fn (string $phone) => (float) (collect(WhatsAppSession::where('phone_number', $phone)->first()->cart)->firstWhere('product_id', $kobokan->id)['qty'] ?? 0);

        // I1
        $this->send('kobokan 03 2 lusin, kobokan 03 2 lusin', '6281000000031');
        $this->send('kobokan 03 2 lusin, kobokan 03 2 lusin', '6281000000031');
        if ($qtyFor('whatsapp:+6281000000031') !== 2.0) {
            $problems[] = 'I1 (bulk): qty menjadi '.$qtyFor('whatsapp:+6281000000031').' Lusin, harapan 2';
        }

        // I2
        $this->send('tambah kobokan 03 2 lusin', '6281000000032');
        $this->send('tambah kobokan 03 2 lusin', '6281000000032');
        if ($qtyFor('whatsapp:+6281000000032') !== 2.0) {
            $problems[] = 'I2 (chat cart_add): payload yang sama dikirim 2x menjadi '.$qtyFor('whatsapp:+6281000000032').' Lusin, harapan 2';
        }

        // I3
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_payment', ['customer_name' => 'Budi', 'address' => 'Ambil Sendiri di Toko'], 'whatsapp:+6281000000033');
        $this->send('1', '6281000000033');
        $this->send('1', '6281000000033');
        if (Sale::count() !== 1) {
            $problems[] = 'I3: jumlah pesanan = '.Sale::count().', harapan 1';
        }

        $this->assertNoProblems($problems);
    }

    // ─── J. Dua pesan cepat saat konfirmasi ───────────────────────────────────

    public function test_j_dua_pesan_ya_beruntun_saat_konfirmasi(): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'confirm_order');

        OpenAI::fake([
            $this->intent('confirm'), // 2nd "ya" reaches GPT (step is already need_name)
            $this->intent('chat'), $this->chatReply('Baik kak.'), // name after that
        ]);

        $this->send('ya');
        $this->send('ya');
        $stepAfterDoubleYa = WhatsAppSession::where('phone_number', self::PHONE)->value('step');
        $this->send('Budi Santoso');

        $problems = [];
        $session = WhatsAppSession::where('phone_number', self::PHONE)->first();
        if ($stepAfterDoubleYa !== 'need_name') {
            $problems[] = "Setelah \"ya\" dua kali, step mundur ke \"{$stepAfterDoubleYa}\" (harapan tetap need_name)";
        }
        if ($session->customer_name !== 'Budi Santoso') {
            $problems[] = 'Nama yang dikirim sesudahnya tidak tersimpan (customer_name = '.json_encode($session->customer_name).", step = {$session->step})";
        }

        $this->assertNoProblems($problems);
    }

    // ─── K. Webhook tanpa token/pengirim valid ────────────────────────────────

    public function test_k_webhook_palsu_tidak_boleh_membuat_pesanan(): void
    {
        User::factory()->create(); // id 1
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);

        // A real customer's session waiting at the payment step.
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_payment', [
            'customer_name' => 'Budi',
            'address' => 'Jl. Bengkalis II No. 91',
        ]);

        // Spoofed sender without the webhook key, and with a wrong key.
        $this->postJson('/webhook/whatsapp', ['sender' => self::SENDER, 'message' => '2'])->assertStatus(403);
        $this->postJson('/webhook/whatsapp?key=salah', ['sender' => self::SENDER, 'message' => '2'])->assertStatus(403);

        // A correctly keyed payload with no sender at all is ignored.
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', ['message' => '2'])->assertStatus(200);

        $this->assertSame(
            0,
            Sale::count(),
            'Request tanpa token/device dengan sender palsu tetap membuat pesanan atas nama pelanggan tersebut'
        );
    }

    // ─── L. Rate limit & error umum ───────────────────────────────────────────

    public function test_l_rate_limit_dan_error_umum(): void
    {
        OpenAI::fake([
            new RateLimitException(new PsrResponse(429)),
            new \RuntimeException('Koneksi OpenAI terputus'),
        ]);

        $this->send('halo', '6281000000041');
        $this->send('halo', '6281000000042');

        $this->assertSame('Maaf, sistem kami sedang sibuk. Silakan coba lagi dalam beberapa saat.', $this->lastReply('6281000000041'));
        $this->assertSame('Maaf, terjadi kesalahan. Silakan coba lagi atau hubungi kami langsung.', $this->lastReply('6281000000042'));
    }
}
