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
 * "Lihat produk lalu pesan": after the bot shows a product, a reply with
 * only a quantity ("pesan 2 lusin", "2 lusin") orders that product through
 * the normal bulk flow. Order verbs are never treated as product names.
 */
class WhatsAppQuantityFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private const SENDER = '6281234567890';

    private const PHONE = 'whatsapp:+6281234567890';

    private const TOPLES = 'TOPLES JUMBO SEALWARE 25 KG';

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
        putenv('WHATSAPP_PROVIDER');
        unset($_ENV['WHATSAPP_PROVIDER'], $_SERVER['WHATSAPP_PROVIDER']);

        parent::tearDown();
    }

    private function product(string $name, float $price = 318000, string $saleUnit = 'Lusin', float $stock = 50): Product
    {
        return Product::create([
            'product_name' => $name,
            'sale_price' => $price,
            'sale_unit' => $saleUnit,
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => $stock,
            'default_buy_price' => 0,
            'default_buy_discount' => 0,
        ]);
    }

    private function send(string $message): void
    {
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
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

    private function chatReply(string $reply): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['reply' => $reply, 'show_products' => [], 'cart_add' => []]),
        ]]]]);
    }

    private function lastReply(): string
    {
        $sent = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send');

        return (string) $sent->last()['message'];
    }

    private function currentSession(): WhatsAppSession
    {
        return WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
    }

    /**
     * @param  list<int>|null  $lastShown
     * @param  array<string, mixed>  $extra
     */
    private function seedSession(?array $lastShown, string $step = 'chat', array $extra = []): void
    {
        WhatsAppSession::create(array_merge([
            'phone_number' => self::PHONE,
            'step' => $step,
            'cart' => [],
            'messages' => [],
            'last_shown_products' => $lastShown,
        ], $extra));
    }

    public function test_production_scenario_show_one_product_then_pesan_2_lusin_adds_it_to_cart(): void
    {
        $toples = $this->product(self::TOPLES);
        $this->product('EMBER 10L', 12000, 'Buah', 100);

        OpenAI::fake([
            $this->intent('chat', ['toples jumbo']),
            $this->chatReply('Berikut adalah produk yang Anda cari: - TOPLES JUMBO SEALWARE 25 KG - Rp318.000/Lusin (stok: 50.00).'),
        ]);

        $this->send('toples jumbo ada?');
        $this->assertSame([$toples->id], $this->currentSession()->last_shown_products);

        $this->send('pesan 2 lusin');

        $line = collect($this->currentSession()->cart)->firstWhere('product_id', $toples->id);
        $this->assertNotNull($line);
        $this->assertSame(2.0, (float) $line['qty']);
        $this->assertSame('Lusin', $line['unit']);
        $this->assertSame(636000.0, (float) $line['subtotal']);

        $reply = $this->lastReply();
        $this->assertStringContainsString('✅ TOPLES JUMBO SEALWARE 25 KG × 2 Lusin — Rp636.000', $reply);
        $this->assertStringNotContainsString('tidak ditemukan', $reply);

        // "pesan 2 lusin" never reaches GPT — only the 2 calls of the first message.
        OpenAI::assertSent(Chat::class, 2);
    }

    public function test_stage_two_prompt_explains_how_to_order_and_forbids_made_up_commands(): void
    {
        $this->product(self::TOPLES);
        OpenAI::fake([$this->intent('chat', ['toples']), $this->chatReply('Ada kak.')]);

        $this->send('ada toples?');

        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => str_contains($params['messages'][0]['content'], 'Cara memesan yang benar')
            && str_contains($params['messages'][0]['content'], "JANGAN pernah membuat perintah atau format lain seperti \"ketik 'pesan'\""));
    }

    /**
     * @return array<string, array{string, float, string}>
     */
    public static function quantityOnlyMessages(): array
    {
        return [
            '2 lusin' => ['2 lusin', 2.0, 'Lusin'],
            'mau 3 buah' => ['mau 3 buah', 0.25, 'Lusin'],
            'ambil 1,5 lusin aja' => ['ambil 1,5 lusin aja', 1.5, 'Lusin'],
        ];
    }

    #[DataProvider('quantityOnlyMessages')]
    public function test_quantity_only_reply_orders_the_single_last_shown_product(string $message, float $expectedQty, string $expectedUnit): void
    {
        $toples = $this->product(self::TOPLES);
        $this->seedSession([$toples->id]);
        OpenAI::fake([]);

        $this->send($message);

        $line = collect($this->currentSession()->cart)->firstWhere('product_id', $toples->id);
        $this->assertSame($expectedQty, round((float) $line['qty'], 4));
        $this->assertSame($expectedUnit, $line['unit']);
        OpenAI::assertNothingSent();
    }

    public function test_two_last_shown_products_ask_which_one_and_a_number_reply_picks_it(): void
    {
        $toples = $this->product(self::TOPLES);
        $ember = $this->product('EMBER 10L', 12000, 'Lusin', 240);
        $this->seedSession([$toples->id, $ember->id]);
        OpenAI::fake([]);

        $this->send('pesan 2 lusin');

        $reply = $this->lastReply();
        $this->assertStringContainsString('Produk yang mana', $reply);
        $this->assertStringContainsString('1. TOPLES JUMBO SEALWARE 25 KG', $reply);
        $this->assertStringContainsString('2. EMBER 10L', $reply);
        $this->assertSame([], $this->currentSession()->cart);

        $this->send('2');

        $session = $this->currentSession();
        $this->assertNull($session->pending_clarification);
        $line = collect($session->cart)->firstWhere('product_id', $ember->id);
        $this->assertSame(2.0, (float) $line['qty']);
        OpenAI::assertNothingSent();
    }

    public function test_quantity_without_any_last_shown_product_explains_how_to_order(): void
    {
        $this->product(self::TOPLES);
        $this->seedSession(null);
        OpenAI::fake([]);

        $this->send('pesan 2 lusin');

        $reply = $this->lastReply();
        $this->assertStringContainsString('Untuk memesan, ketik nama produk beserta jumlah dan satuannya', $reply);
        $this->assertStringNotContainsString('tidak ditemukan', $reply);
        $this->assertSame([], $this->currentSession()->cart);
        OpenAI::assertNothingSent();
    }

    public function test_order_verb_extracted_as_bulk_item_is_never_searched_as_a_product(): void
    {
        $this->product(self::TOPLES);
        // GPT (wrongly) returns the verb itself as the item.
        OpenAI::fake([$this->intent('bulk', ['pesan'], [['item' => 'pesan', 'qty' => 2, 'unit' => 'lusin']])]);

        $this->send('pesan dong 2 lusin');

        $reply = $this->lastReply();
        $this->assertStringNotContainsString('❌ pesan', $reply);
        $this->assertStringContainsString('Untuk memesan', $reply);
    }

    public function test_order_verb_prefix_is_stripped_from_a_bulk_item_name(): void
    {
        $toples = $this->product(self::TOPLES);
        OpenAI::fake([$this->intent('bulk', [], [['item' => 'pesan toples jumbo sealware 25 kg', 'qty' => 2, 'unit' => 'lusin']])]);

        $this->send('pesan toples jumbo sealware 25 kg 2 lusin');

        $this->assertSame($toples->id, $this->currentSession()->cart[0]['product_id']);
    }

    public function test_quantity_exceeding_stock_of_last_shown_product_is_still_refused(): void
    {
        $toples = $this->product(self::TOPLES, stock: 10); // 10 buah < 2 lusin
        $this->seedSession([$toples->id]);
        OpenAI::fake([]);

        $this->send('pesan 2 lusin');

        $this->assertSame([], $this->currentSession()->cart);
        $this->assertStringContainsString('stok *TOPLES JUMBO SEALWARE 25 KG* hanya 0 Lusin + 10 buah (10 buah total)', $this->lastReply());
    }

    public function test_name_only_inside_a_longer_listed_name_is_not_counted_as_shown(): void
    {
        $short = $this->product('TOPLES JUMBO');
        $long = $this->product(self::TOPLES);
        OpenAI::fake([
            $this->intent('chat', ['toples jumbo']),
            $this->chatReply('Ada kak: TOPLES JUMBO SEALWARE 25 KG - Rp318.000/Lusin.'),
        ]);

        $this->send('toples jumbo sealware ada?');

        $this->assertSame([$long->id], $this->currentSession()->last_shown_products);
        $this->assertNotContains($short->id, $this->currentSession()->last_shown_products);
    }

    public function test_last_shown_products_are_cleared_on_cancel(): void
    {
        $toples = $this->product(self::TOPLES);
        $this->seedSession([$toples->id]);
        OpenAI::fake([$this->intent('cancel')]);

        $this->send('batal');

        $this->assertNull($this->currentSession()->last_shown_products);
    }

    public function test_last_shown_products_are_cleared_when_order_is_created(): void
    {
        User::factory()->create(); // id 1 — createOrder() hardcodes user_id 1
        $toples = $this->product(self::TOPLES);
        $this->seedSession([$toples->id], 'need_payment', [
            'customer_name' => 'Budi',
            'address' => 'Ambil Sendiri di Toko',
            'cart' => [[
                'product_id' => $toples->id, 'product_name' => self::TOPLES, 'qty' => 2,
                'unit' => 'Lusin', 'price' => 318000, 'subtotal' => 636000,
            ]],
        ]);
        OpenAI::fake([]);

        $this->send('2');

        $this->assertNull($this->currentSession()->last_shown_products);
        $this->assertStringContainsString('Pesanan Berhasil Dibuat', $this->lastReply());
    }
}
