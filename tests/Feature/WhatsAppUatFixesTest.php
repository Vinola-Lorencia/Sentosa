<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression tests for the fixes made after WhatsAppUatScenarioTest:
 * whole-word command detection (F2), photo sending from image_path (B),
 * stock checks when adding to the chatbot cart (E), repeated "ya" during
 * data collection (J), configurable bank info (F) and the webhook secret (K).
 */
class WhatsAppUatFixesTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = '/webhook/whatsapp?key=test-webhook-secret';

    private const SENDER = '6281234567890';

    private const PHONE = 'whatsapp:+6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token', 'services.whatsapp.webhook_secret' => 'test-webhook-secret']);

        putenv('WHATSAPP_PROVIDER=fonnte');
        $_ENV['WHATSAPP_PROVIDER'] = 'fonnte';
        $_SERVER['WHATSAPP_PROVIDER'] = 'fonnte';

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200),
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

    private function send(string $message): void
    {
        $this->postJson(self::WEBHOOK, [
            'device' => '6281111111111',
            'sender' => self::SENDER,
            'message' => $message,
            'text' => 'non-button message',
        ])->assertStatus(200);
    }

    /**
     * @param  list<string>  $keywords
     * @param  list<array<string, mixed>>  $bulkItems
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

    private function lastReply(): string
    {
        $sent = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send')
            ->values();

        return $sent->isEmpty() ? '' : (string) $sent->last()['message'];
    }

    /**
     * @param  list<array<string, mixed>>  $cart
     * @param  array<string, mixed>  $extra
     */
    private function seedSession(array $cart, string $step, array $extra = []): WhatsAppSession
    {
        return WhatsAppSession::create(array_merge([
            'phone_number' => self::PHONE,
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

    // ─── F2: whole-word command detection ─────────────────────────────────────

    /**
     * @return array<string, array{string}>
     */
    public static function confirmationWords(): array
    {
        return [
            'ya' => ['ya'],
            'ya.' => ['ya.'],
            'Oke' => ['Oke'],
            'iya!' => ['iya!'],
        ];
    }

    #[DataProvider('confirmationWords')]
    public function test_confirmation_words_still_recognized_at_confirm_order(string $word): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'confirm_order');
        OpenAI::fake([]);

        $this->send($word);

        $this->assertSame('need_name', WhatsAppSession::where('phone_number', self::PHONE)->value('step'));
        OpenAI::assertNothingSent();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function namesAndAddressesWithTriggerSubstrings(): array
    {
        return [
            'nama Maulana (mau)' => ['need_name', 'Maulana', 'customer_name'],
            'nama Oktavia (ok)' => ['need_name', 'Oktavia', 'customer_name'],
            'alamat Jl. Beringin (ingin)' => ['need_address', 'Jl. Beringin No. 3', 'address'],
            'alamat Jl. Pesantren (pesan)' => ['need_address', 'Jl. Pesantren Gg. Belimbing 7', 'address'],
        ];
    }

    #[DataProvider('namesAndAddressesWithTriggerSubstrings')]
    public function test_names_and_addresses_containing_trigger_substrings_are_saved(string $step, string $message, string $column): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], $step, ['customer_name' => $step === 'need_address' ? 'Budi' : null]);
        OpenAI::fake([]);

        $this->send($message);

        $this->assertSame($message, WhatsAppSession::where('phone_number', self::PHONE)->value($column));
        OpenAI::assertNothingSent();
    }

    public function test_real_question_at_name_step_is_still_not_saved_as_name(): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_name');
        OpenAI::fake([
            $this->intent('chat', ['kobokan']),
            $this->chatReply('KOBOKAN 03 Rp15.000/Lusin.'),
        ]);

        // "harganya" = whole word "harga" + "-nya" suffix.
        $this->send('harganya kobokan 03 brp');

        $session = WhatsAppSession::where('phone_number', self::PHONE)->first();
        $this->assertNull($session->customer_name);
        $this->assertSame('need_name', $session->step);
    }

    // ─── B: photos from image_path (Supabase) ─────────────────────────────────

    public function test_photo_is_sent_from_supabase_public_url_and_prompt_says_photos_are_sent(): void
    {
        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.storage_bucket' => 'produk',
        ]);
        // Even on a localhost APP_URL, a Supabase URL is public and must be sent.
        putenv('FILESYSTEM_URL=http://localhost');
        $_ENV['FILESYSTEM_URL'] = $_SERVER['FILESYSTEM_URL'] = 'http://localhost';

        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7, [
            'image' => null,
            'image_path' => 'products/product_kursi10.jpg',
        ]);
        OpenAI::fake([
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Berikut fotonya kak.', ['KURSI SANTAI NO.10']),
        ]);

        $this->send('foto kursi santai');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send'
            && ($r['url'] ?? null) === 'https://supabase.test/storage/v1/object/public/produk/products/product_kursi10.jpg');

        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => str_contains(
            $params['messages'][0]['content'],
            'Sistem akan OTOMATIS mengirim foto produk'
        ));
    }

    // ─── E: stock check when adding to the cart ────────────────────────────────

    public function test_clarification_pick_exceeding_stock_is_refused_with_friendly_message(): void
    {
        $kecil = $this->product('BASKOM KECIL', 18000, 'Lusin', 12); // 1 Lusin in stock
        $this->product('BASKOM BESAR', 36000, 'Lusin', 240);
        $this->seedSession([], 'chat', ['pending_clarification' => [[
            'original' => 'baskom',
            'options' => [['id' => $kecil->id, 'name' => 'BASKOM KECIL'], ['id' => $kecil->id + 1, 'name' => 'BASKOM BESAR']],
            'qty' => 5,
            'unit' => 'lusin',
        ]]]);
        OpenAI::fake([]);

        $this->send('1');

        $session = WhatsAppSession::where('phone_number', self::PHONE)->first();
        $this->assertSame([], $session->cart);
        $this->assertStringContainsString('stok *BASKOM KECIL* hanya 1 Lusin', $this->lastReply());
        OpenAI::assertNothingSent();
    }

    public function test_chat_cart_add_is_refused_when_total_in_cart_would_exceed_stock(): void
    {
        $ember = $this->product('EMBER 10L', 12000, 'Buah', 5);
        $this->seedSession([$this->cartLine($ember, 4)], 'chat');
        OpenAI::fake([
            $this->intent('chat', ['ember']),
            $this->chatReply('Siap, ditambahkan.', [], [['product_name' => 'EMBER 10L', 'qty' => 2, 'unit' => 'buah']]),
        ]);

        // 4 already in cart + 2 more = 6 > 5 in stock.
        $this->send('tambah ember 10l 2 buah');

        $line = collect(WhatsAppSession::where('phone_number', self::PHONE)->first()->cart)->firstWhere('product_id', $ember->id);
        $this->assertSame(4.0, (float) $line['qty']);
        $this->assertStringContainsString('stok *EMBER 10L* hanya 5 Buah', $this->lastReply());
    }

    public function test_bulk_item_within_stock_is_still_added(): void
    {
        $ember = $this->product('EMBER 10L', 12000, 'Buah', 5);
        OpenAI::fake([$this->intent('bulk', ['ember'], [['item' => 'ember 10l', 'qty' => 5, 'unit' => 'buah']])]);

        $this->send('ember 10l 5 buah');

        $line = collect(WhatsAppSession::where('phone_number', self::PHONE)->first()->cart)->firstWhere('product_id', $ember->id);
        $this->assertSame(5.0, (float) $line['qty']);
        $this->assertStringNotContainsString('Stok tidak cukup', $this->lastReply());
    }

    // ─── J: repeated "ya" while collecting details ─────────────────────────────

    public function test_repeated_ya_at_need_address_repeats_the_question_and_batal_still_cancels(): void
    {
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_address', ['customer_name' => 'Budi']);
        OpenAI::fake([$this->intent('confirm'), $this->intent('cancel')]);

        $this->send('ya');
        $this->assertSame('need_address', WhatsAppSession::where('phone_number', self::PHONE)->value('step'));
        $this->assertStringContainsString('Boleh minta alamat pengirimannya, *Budi*?', $this->lastReply());

        $this->send('batal');
        $session = WhatsAppSession::where('phone_number', self::PHONE)->first();
        $this->assertSame('chat', $session->step);
        $this->assertSame([], $session->cart);
    }

    // ─── F: bank info from config ──────────────────────────────────────────────

    public function test_transfer_order_shows_bank_account_from_config(): void
    {
        config([
            'services.shop.bank_name' => 'TESTBANK',
            'services.shop.bank_account' => '000-TEST-111',
            'services.shop.bank_account_name' => 'Pemilik Uji',
        ]);
        User::factory()->create(); // id 1 — createOrder() hardcodes user_id 1
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_payment', ['customer_name' => 'Budi', 'address' => 'Ambil Sendiri di Toko']);
        OpenAI::fake([]);

        $this->send('1');

        $reply = $this->lastReply();
        $this->assertStringContainsString('🏦 Bank TESTBANK', $reply);
        $this->assertStringContainsString('No. Rekening: *000-TEST-111*', $reply);
        $this->assertStringContainsString('Atas Nama: *Pemilik Uji*', $reply);
    }

    public function test_transfer_order_without_configured_account_shows_safe_message(): void
    {
        config(['services.shop.bank_account' => '']);
        User::factory()->create(); // id 1
        $kobokan = $this->product('KOBOKAN 03', 15000, 'Lusin', 240);
        $this->seedSession([$this->cartLine($kobokan, 2)], 'need_payment', ['customer_name' => 'Budi', 'address' => 'Ambil Sendiri di Toko']);
        OpenAI::fake([]);

        $this->send('1');

        $reply = $this->lastReply();
        $this->assertStringContainsString('Info rekening untuk transfer akan dikirimkan oleh admin kami', $reply);
        $this->assertStringNotContainsString('No. Rekening', $reply);
    }

    // ─── K: webhook secret ─────────────────────────────────────────────────────

    public function test_webhook_is_rejected_when_no_secret_is_configured(): void
    {
        config(['services.whatsapp.webhook_secret' => '']);
        OpenAI::fake([]);

        $this->postJson('/webhook/whatsapp?key=', [
            'device' => '6281111111111',
            'sender' => self::SENDER,
            'message' => 'halo',
        ])->assertStatus(403);

        $this->assertSame(0, WhatsAppSession::count());
        Http::assertNothingSent();
    }

    public function test_webhook_with_correct_key_is_processed(): void
    {
        OpenAI::fake([$this->intent('chat'), $this->chatReply('Halo kak!')]);

        $this->send('halo');

        $this->assertSame('Halo kak!', $this->lastReply());
    }

    public function test_product_without_any_photo_sends_no_media(): void
    {
        $this->product('KURSI SANTAI NO.10', 85000, 'Buah', 7, ['image' => null, 'image_path' => null]);
        OpenAI::fake([
            $this->intent('chat', ['kursi santai']),
            $this->chatReply('Berikut fotonya kak.', ['KURSI SANTAI NO.10']),
        ]);

        $this->send('foto kursi santai');

        Http::assertNotSent(fn (HttpRequest $r) => ($r['url'] ?? null) !== null);
    }
}
