<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The chatbot must never put more into the cart than is in stock — whatever
 * unit (or typo of a unit) the customer uses, across several messages, and
 * again when the order is finally created.
 */
class WhatsAppStockLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'whatsapp:+6281234567890';

    private const BOTTLE = 'BOTOL MY BOTTLE';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token', 'services.whatsapp.webhook_secret' => 'test-webhook-secret']);

        putenv('WHATSAPP_PROVIDER=fonnte');
        $_ENV['WHATSAPP_PROVIDER'] = 'fonnte';
        $_SERVER['WHATSAPP_PROVIDER'] = 'fonnte';

        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200)]);
    }

    protected function tearDown(): void
    {
        putenv('WHATSAPP_PROVIDER');
        unset($_ENV['WHATSAPP_PROVIDER'], $_SERVER['WHATSAPP_PROVIDER']);

        parent::tearDown();
    }

    /**
     * Rp66.000/Lusin, stok 30 Lusin (360 Buah).
     */
    private function bottle(float $stock = 360): Product
    {
        return Product::create([
            'product_name' => self::BOTTLE, 'sale_price' => 66000, 'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => $stock,
            'default_buy_price' => 0, 'default_buy_discount' => 0,
        ]);
    }

    private function send(string $message): string
    {
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
            'device' => '6281111111111', 'sender' => '6281234567890',
            'message' => $message, 'text' => 'non-button message',
        ])->assertOk();

        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send');

        return (string) $sent->last()['message'];
    }

    /**
     * Chat-mode turn where GPT claims the item was added.
     */
    private function fakeChatAdd(float $qty, string $unit, string $name = 'Botol my bottle'): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['mode' => 'chat', 'keywords' => ['my bottle'], 'bulk_items' => []]),
            ]]]]),
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode([
                    'reply' => "Pesanan Anda untuk {$qty} Lusin {$name} telah ditambahkan ke keranjang.",
                    'show_products' => [],
                    'cart_add' => [['product_name' => $name, 'qty' => $qty, 'unit' => $unit]],
                ]),
            ]]]]),
        ]);
    }

    private function fakeBulk(float $qty, string $unit): void
    {
        OpenAI::fake([CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['mode' => 'bulk', 'keywords' => [], 'bulk_items' => [['item' => 'my bottle', 'qty' => $qty, 'unit' => $unit]]]),
        ]]]])]);
    }

    private function currentSession(): WhatsAppSession
    {
        return WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
    }

    /**
     * @return array{qty: float, unit: string}|null
     */
    private function bottleLine(): ?array
    {
        $line = collect($this->currentSession()->cart ?? [])->firstWhere('product_name', self::BOTTLE);

        return $line ? ['qty' => round((float) $line['qty'], 4), 'unit' => $line['unit']] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $cart
     */
    private function seedSession(array $cart, string $step = 'chat', array $extra = []): void
    {
        WhatsAppSession::create(array_merge(['phone_number' => self::PHONE, 'step' => $step, 'messages' => [], 'cart' => $cart], $extra));
    }

    private function cartLine(Product $product, float $qty): array
    {
        return ['product_id' => $product->id, 'product_name' => self::BOTTLE, 'qty' => $qty, 'unit' => 'Lusin', 'price' => 66000, 'subtotal' => $qty * 66000];
    }

    // ─── Kasus produksi ────────────────────────────────────────────────────

    public function test_production_case_40_lisin_is_refused_and_gpt_claim_is_not_sent(): void
    {
        $this->bottle();
        $this->fakeChatAdd(40, 'lisin');

        $reply = $this->send('mau my botel 40 lisin');

        $this->assertNull($this->bottleLine());
        $this->assertStringNotContainsString('telah ditambahkan', $reply);
        $this->assertStringContainsString('stok *BOTOL MY BOTTLE* hanya 30 Lusin, jadi 40 Lusin belum bisa ditambahkan', $reply);
        $this->assertStringContainsString('Mau pesan 30 Lusin saja? Balas *30 lusin*', $reply);

        // GPT's false claim never reaches the history either.
        $this->assertStringNotContainsString('telah ditambahkan', (string) collect($this->currentSession()->messages)->last()['content']);
    }

    public function test_accepting_the_offer_orders_the_available_stock(): void
    {
        $this->bottle();
        $this->fakeChatAdd(40, 'Lusin');
        $this->send('mau my bottle 40 lusin');

        OpenAI::fake([]);
        $this->send('30 lusin');

        $this->assertSame(['qty' => 30.0, 'unit' => 'Lusin'], $this->bottleLine());
    }

    // ─── Melebihi / sama dengan stok ───────────────────────────────────────

    public function test_bulk_order_above_stock_is_refused_even_with_unit_typo(): void
    {
        $this->bottle();
        $this->fakeBulk(40, 'lisin');

        $reply = $this->send('my botel 40 lisin, ember 1');

        $this->assertNull($this->bottleLine());
        $this->assertStringContainsString('hanya 30 Lusin, jadi 40 Lusin', $reply);
    }

    public function test_order_equal_to_stock_is_accepted(): void
    {
        $this->bottle();
        $this->fakeChatAdd(30, 'Lusin');

        $reply = $this->send('mau my bottle 30 lusin');

        $this->assertSame(['qty' => 30.0, 'unit' => 'Lusin'], $this->bottleLine());
        $this->assertStringContainsString('telah ditambahkan', $reply); // accepted → GPT's reply is true
    }

    public function test_two_messages_whose_total_exceeds_stock_are_refused(): void
    {
        $this->bottle();
        $this->fakeChatAdd(20, 'Lusin');
        $this->send('mau my bottle 20 lusin');
        $this->assertSame(['qty' => 20.0, 'unit' => 'Lusin'], $this->bottleLine());

        $this->fakeChatAdd(11, 'Lusin');
        $reply = $this->send('tambah my bottle 11 lusin');

        $this->assertSame(['qty' => 20.0, 'unit' => 'Lusin'], $this->bottleLine());
        $this->assertStringContainsString('jadi 11 Lusin belum bisa ditambahkan. Di keranjang sudah ada 20 Lusin.', $reply);
    }

    // ─── Satuan berbeda ────────────────────────────────────────────────────

    /**
     * @return array<string, array{float, string, float|null}>
     */
    public static function unitOrders(): array
    {
        return [
            '360 Buah = 30 Lusin' => [360, 'buah', 30.0],
            '361 Buah > stok' => [361, 'buah', null],
            '30 lisin (typo)' => [30, 'lisin', 30.0],
            '30 lsn (singkatan)' => [30, 'lsn', 30.0],
            '30 lusinn (typo)' => [30, 'lusinn', 30.0],
            '2 gros = 288 Buah' => [2, 'gros', 24.0],
            '31 Lusin > stok' => [31, 'Lusin', null],
        ];
    }

    #[DataProvider('unitOrders')]
    public function test_units_and_unit_typos_are_converted_before_the_stock_check(float $qty, string $unit, ?float $expectedLusin): void
    {
        $this->bottle();
        $this->fakeBulk($qty, $unit);

        $this->send("my bottle {$qty} {$unit}");

        $this->assertSame($expectedLusin === null ? null : ['qty' => $expectedLusin, 'unit' => 'Lusin'], $this->bottleLine());
    }

    public function test_buah_added_on_top_of_lusin_in_cart_counts_both(): void
    {
        $bottle = $this->bottle();
        $this->seedSession([$this->cartLine($bottle, 20)]); // 240 Buah

        $this->fakeChatAdd(121, 'buah');
        $this->send('tambah my bottle 121 buah'); // 240 + 121 = 361 > 360
        $this->assertSame(['qty' => 20.0, 'unit' => 'Lusin'], $this->bottleLine());

        $this->fakeChatAdd(120, 'buah');
        $this->send('tambah my bottle 120 buah'); // 240 + 120 = 360
        $this->assertSame(['qty' => 30.0, 'unit' => 'Lusin'], $this->bottleLine());
    }

    public function test_product_name_gpt_made_up_is_reported_instead_of_claimed_added(): void
    {
        $this->bottle();
        $this->fakeChatAdd(2, 'Lusin', 'Botol Tumbler Ajaib');

        $reply = $this->send('mau tumbler ajaib 2 lusin');

        $this->assertSame([], $this->currentSession()->cart);
        $this->assertStringNotContainsString('telah ditambahkan', $reply);
        $this->assertStringContainsString('*Botol Tumbler Ajaib* tidak ditemukan', $reply);
    }

    // ─── Validasi ulang saat pesanan dibuat ────────────────────────────────

    public function test_create_order_refuses_an_old_cart_above_stock(): void
    {
        User::factory()->create(); // createOrder() uses user_id 1
        $bottle = $this->bottle();
        $this->seedSession([$this->cartLine($bottle, 40)], 'need_payment', ['customer_name' => 'Budi', 'address' => 'Ambil Sendiri di Toko']);
        OpenAI::fake([]);

        $reply = $this->send('2');

        $this->assertSame(0, Sale::count());
        $this->assertStringContainsString('Pesanan belum bisa dibuat karena stok tidak mencukupi', $reply);
        $this->assertStringContainsString('*BOTOL MY BOTTLE*: dipesan 40 Lusin, stok hanya 30 Lusin (maksimal 30 Lusin).', $reply);
        $this->assertStringContainsString('*30 lusin*', $reply);
        $this->assertSame('chat', $this->currentSession()->step);
        $this->assertSame([$bottle->id], $this->currentSession()->last_shown_products);

        // Customer fixes the quantity, then the order goes through.
        $this->send('30 lusin');
        $this->assertSame(['qty' => 30.0, 'unit' => 'Lusin'], $this->bottleLine());
    }

    public function test_create_order_refuses_when_stock_dropped_after_adding(): void
    {
        User::factory()->create();
        $bottle = $this->bottle();
        $this->seedSession([$this->cartLine($bottle, 30)], 'need_payment', ['customer_name' => 'Budi', 'address' => 'Ambil Sendiri di Toko']);
        $bottle->update(['stock' => 240]); // dijual di toko sementara itu
        OpenAI::fake([]);

        $reply = $this->send('2');

        $this->assertSame(0, Sale::count());
        $this->assertStringContainsString('stok hanya 20 Lusin (maksimal 20 Lusin)', $reply);
    }

    public function test_create_order_within_stock_still_creates_the_sale(): void
    {
        User::factory()->create();
        $bottle = $this->bottle();
        $this->seedSession([$this->cartLine($bottle, 30)], 'need_payment', ['customer_name' => 'Budi', 'address' => 'Ambil Sendiri di Toko']);
        OpenAI::fake([]);

        $reply = $this->send('2');

        $this->assertSame(1, Sale::count());
        $this->assertStringContainsString('Pesanan Berhasil Dibuat', $reply);
    }
}
