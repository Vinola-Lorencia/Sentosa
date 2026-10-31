<?php

namespace Tests\Feature;

use App\Models\Product;
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
 * Stock is stored in Buah; the chatbot must show it in the product's sale
 * unit ("30 Lusin", not "360.00 Lusin") and must refuse orders above it,
 * whatever unit the customer orders in.
 */
class WhatsAppStockDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'whatsapp:+6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fonnte.token' => 'test-fonnte-token',
            'services.whatsapp.webhook_secret' => 'test-webhook-secret',
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.storage_bucket' => 'produk',
        ]);

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
     * @param  array<string, mixed>  $overrides
     */
    private function product(string $name, float $stock, string $saleUnit = 'Lusin', float $price = 66000, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => $name, 'sale_price' => $price, 'sale_unit' => $saleUnit,
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => $stock,
            'default_buy_price' => 0, 'default_buy_discount' => 0,
        ], $overrides));
    }

    private function myBottle(): Product
    {
        return $this->product('MY BOTTLE', 360); // 30 Lusin × 12
    }

    private function send(string $message): void
    {
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
            'device' => '6281111111111', 'sender' => '6281234567890',
            'message' => $message, 'text' => 'non-button message',
        ])->assertOk();
    }

    /**
     * @return list<string>
     */
    private function sentMessages(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send')
            ->map(fn (HttpRequest $r) => (string) $r['message'])
            ->values()->all();
    }

    private function lastReply(): string
    {
        return (string) last($this->sentMessages());
    }

    /**
     * @param  list<array{item: string, qty: float|int, unit: string}>  $items
     */
    private function bulkIntent(array $items): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['mode' => 'bulk', 'keywords' => [], 'bulk_items' => $items]),
        ]]]]);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function chatIntent(array $keywords): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['mode' => 'chat', 'keywords' => $keywords, 'bulk_items' => []]),
        ]]]]);
    }

    /**
     * @param  list<string>  $show
     * @param  list<array<string, mixed>>  $cartAdd
     */
    private function chatReply(string $reply, array $show = [], array $cartAdd = []): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['reply' => $reply, 'show_products' => $show, 'cart_add' => $cartAdd]),
        ]]]]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cart(): array
    {
        return WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail()->cart ?? [];
    }

    // ─── Format ────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{float, string, float, string}>
     */
    public static function stockFormats(): array
    {
        return [
            '360 buah, jual per Lusin' => [360, 'Lusin', 1, '30 Lusin'],
            'sisa buah' => [366, 'Lusin', 1, '30 Lusin + 6 buah'],
            'kurang dari 1 Lusin' => [10, 'Lusin', 1, '10 buah'],
            'Kodi' => [50, 'Kodi', 1, '2 Kodi + 10 buah'],
            'Gross' => [288, 'Gross', 1, '2 Gross'],
            'satuan dasar Buah' => [15, 'Buah', 1, '15 Buah'],
            'Buah pecahan' => [2.5, 'Buah', 1, '2,5 Buah'],
            'Pcs ribuan' => [1000, 'Pcs', 1, '1.000 Pcs'],
            'jual per Dus (24 buah/Dus)' => [48, 'Dus', 24, '2 Dus'],
            'stok habis' => [0, 'Lusin', 1, '0 Lusin'],
        ];
    }

    #[DataProvider('stockFormats')]
    public function test_stock_is_shown_in_sale_unit_without_extra_decimals(float $stock, string $saleUnit, float $dusToUnit, string $expected): void
    {
        $product = $this->product('PRODUK', $stock, $saleUnit, 10000, ['dus_to_unit' => $dusToUnit]);

        $this->assertSame($expected, $product->fresh()->stock_for_customer);
    }

    // ─── Tampilan ke pelanggan ─────────────────────────────────────────────

    public function test_production_case_my_bottle_shows_30_lusin_in_prompt_and_product_list(): void
    {
        $this->myBottle();
        OpenAI::fake([$this->chatIntent(['my bottle']), $this->chatReply('Berikut produk yang tersedia:')]);

        $this->send('my bottle ada?');

        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => str_contains($params['messages'][0]['content'], 'MY BOTTLE - Rp66.000/Lusin (stok: 30 Lusin)')
            && ! str_contains($params['messages'][0]['content'], '360'));

        $reply = $this->lastReply();
        $this->assertStringContainsString('- MY BOTTLE - Rp66.000/Lusin (stok: 30 Lusin)', $reply);
        $this->assertStringNotContainsString('360', $reply);
    }

    public function test_photo_caption_shows_stock_in_sale_unit(): void
    {
        $this->product('MY BOTTLE', 360, 'Lusin', 66000, ['image_path' => 'products/my-bottle.jpg']);
        OpenAI::fake([$this->chatIntent(['my bottle']), $this->chatReply('Berikut fotonya kak. MY BOTTLE ada.', ['MY BOTTLE'])]);

        $this->send('foto my bottle');

        $caption = collect($this->sentMessages())->first(fn ($m) => str_starts_with($m, '📦 *MY BOTTLE*'));
        $this->assertNotNull($caption);
        $this->assertStringContainsString('📦 Stok: 30 Lusin', $caption);
        $this->assertStringNotContainsString('360', $caption);
    }

    // ─── Validasi stok saat memesan ────────────────────────────────────────

    public function test_bulk_order_of_31_lusin_is_refused_when_stock_is_30_lusin(): void
    {
        $this->myBottle();
        OpenAI::fake([$this->bulkIntent([['item' => 'my bottle', 'qty' => 31, 'unit' => 'lusin']])]);

        $this->send('my bottle 31 lusin');

        $this->assertSame([], $this->cart());
        $this->assertStringContainsString('stok *MY BOTTLE* hanya 30 Lusin, jadi 31 Lusin belum bisa ditambahkan', $this->lastReply());
    }

    public function test_bulk_order_of_exactly_30_lusin_is_accepted(): void
    {
        $this->myBottle();
        OpenAI::fake([$this->bulkIntent([['item' => 'my bottle', 'qty' => 30, 'unit' => 'lusin']])]);

        $this->send('my bottle 30 lusin');

        $this->assertSame(30.0, (float) $this->cart()[0]['qty']);
        $this->assertSame('Lusin', $this->cart()[0]['unit']);
    }

    public function test_chat_cart_add_of_31_lusin_is_refused(): void
    {
        $this->myBottle();
        OpenAI::fake([
            $this->chatIntent(['my bottle']),
            $this->chatReply('Siap kak.', [], [['product_name' => 'MY BOTTLE', 'qty' => 31, 'unit' => 'Lusin']]),
        ]);

        $this->send('pesan my bottle 31 lusin ya');

        $this->assertSame([], $this->cart());
        $this->assertStringContainsString('hanya 30 Lusin', $this->lastReply());
    }

    public function test_chat_cart_add_on_top_of_existing_cart_is_checked_against_total(): void
    {
        $bottle = $this->myBottle();
        WhatsAppSession::create(['phone_number' => self::PHONE, 'step' => 'chat', 'messages' => [], 'cart' => [[
            'product_id' => $bottle->id, 'product_name' => 'MY BOTTLE', 'qty' => 20, 'unit' => 'Lusin', 'price' => 66000, 'subtotal' => 1320000,
        ]]]);
        OpenAI::fake([
            $this->chatIntent(['my bottle']),
            $this->chatReply('Siap kak.', [], [['product_name' => 'MY BOTTLE', 'qty' => 11, 'unit' => 'Lusin']]),
        ]);

        $this->send('tambah my bottle 11 lusin');

        $this->assertSame(20.0, (float) $this->cart()[0]['qty']); // 20 + 11 > 30 → tetap 20
        $this->assertStringContainsString('hanya 30 Lusin', $this->lastReply());
    }

    public function test_quantity_only_follow_up_of_31_lusin_is_refused(): void
    {
        $bottle = $this->myBottle();
        WhatsAppSession::create(['phone_number' => self::PHONE, 'step' => 'chat', 'messages' => [], 'cart' => [], 'last_shown_products' => [$bottle->id]]);
        OpenAI::fake([]);

        $this->send('31 lusin');

        $this->assertSame([], $this->cart());
        $this->assertStringContainsString('hanya 30 Lusin', $this->lastReply());
    }

    /**
     * @return array<string, array{float, string, bool}>
     */
    public static function otherUnitOrders(): array
    {
        return [
            '360 Buah = pas' => [360, 'buah', true],
            '361 Buah > stok' => [361, 'buah', false],
            '2 Gross = 288 Buah' => [2, 'gross', true],
            '3 Gross = 432 Buah > stok' => [3, 'gross', false],
            '18 Kodi = 360 Buah' => [18, 'kodi', true],
            '19 Kodi = 380 Buah > stok' => [19, 'kodi', false],
        ];
    }

    #[DataProvider('otherUnitOrders')]
    public function test_order_in_another_unit_is_converted_to_buah_before_stock_check(float $qty, string $unit, bool $accepted): void
    {
        $this->myBottle();
        OpenAI::fake([$this->bulkIntent([['item' => 'my bottle', 'qty' => $qty, 'unit' => $unit]])]);

        $this->send("my bottle {$qty} {$unit}");

        $this->assertSame($accepted ? 1 : 0, count($this->cart()));
        if (! $accepted) {
            $this->assertStringContainsString('hanya 30 Lusin', $this->lastReply());
        }
    }

    public function test_product_sold_in_base_unit_keeps_working(): void
    {
        $this->product('EMBER 10L', 5, 'Buah', 12000);
        OpenAI::fake([
            $this->bulkIntent([['item' => 'ember 10l', 'qty' => 6, 'unit' => 'buah']]),
            $this->bulkIntent([['item' => 'ember 10l', 'qty' => 5, 'unit' => 'buah']]),
        ]);

        $this->send('ember 10l 6 buah');
        $this->assertSame([], $this->cart());
        $this->assertStringContainsString('stok *EMBER 10L* hanya 5 Buah, jadi 6 Buah', $this->lastReply());

        $this->send('ember 10l 5 buah');
        $this->assertSame(5.0, (float) $this->cart()[0]['qty']);
    }
}
