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
 * Casual filler words ("aj", "ya", "dong", "yg", ...) are never product
 * names: "20 lusin aj" after a stock refusal orders the product just
 * refused, instead of "aj ada beberapa pilihan: GANTUNGAN BAJU ...".
 */
class WhatsAppFillerWordsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'whatsapp:+6281234567890';

    private Product $kotak;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token', 'services.whatsapp.webhook_secret' => 'test-webhook-secret']);

        putenv('WHATSAPP_PROVIDER=fonnte');
        $_ENV['WHATSAPP_PROVIDER'] = 'fonnte';
        $_SERVER['WHATSAPP_PROVIDER'] = 'fonnte';

        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200)]);

        $this->kotak = $this->product('KOTAK TRISULA 900', 360); // 30 Lusin
        // Names containing "aj" — what the bogus "aj" search used to hit.
        $this->product('GANTUNGAN BAJU ANGSA 128', 240);
        $this->product('KALO BAJU OVAL II', 240);
    }

    protected function tearDown(): void
    {
        putenv('WHATSAPP_PROVIDER');
        unset($_ENV['WHATSAPP_PROVIDER'], $_SERVER['WHATSAPP_PROVIDER']);

        parent::tearDown();
    }

    private function product(string $name, float $stock): Product
    {
        return Product::create([
            'product_name' => $name, 'sale_price' => 50000, 'sale_unit' => 'Lusin',
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

    private function currentSession(): WhatsAppSession
    {
        return WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
    }

    private function kotakQty(): ?float
    {
        $line = collect($this->currentSession()->cart ?? [])->firstWhere('product_id', $this->kotak->id);

        return $line ? (float) $line['qty'] : null;
    }

    /**
     * "mau kotak trisula 900 40 lusin" → refused (stock 30 Lusin).
     */
    private function refuse40Lusin(): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['mode' => 'chat', 'keywords' => ['kotak trisula 900'], 'bulk_items' => []]),
            ]]]]),
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['reply' => 'Siap kak, sudah ditambahkan.', 'show_products' => [],
                    'cart_add' => [['product_name' => 'Kotak trisula 900', 'qty' => 40, 'unit' => 'lusin']]]),
            ]]]]),
        ]);

        $reply = $this->send('mau kotak trisula 900 40 lusin');
        $this->assertStringContainsString('Mau pesan 30 Lusin saja?', $reply);
        $this->assertNull($this->kotakQty());
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function bulkIntent(array $items, array $keywords = []): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['mode' => 'bulk', 'keywords' => $keywords, 'bulk_items' => $items]),
        ]]]]);
    }

    public function test_production_case_20_lusin_aj_after_refusal_orders_the_refused_product(): void
    {
        $this->refuse40Lusin();
        OpenAI::fake([]);

        $reply = $this->send('20 lusin aj');

        $this->assertSame(20.0, $this->kotakQty());
        $this->assertStringContainsString('KOTAK TRISULA 900 × 20 Lusin', $reply);
        $this->assertStringNotContainsString('aj* ada beberapa pilihan', $reply);
        $this->assertStringNotContainsString('BAJU', $reply);
        $this->assertNull($this->currentSession()->pending_clarification);
        OpenAI::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function quantityWithFillers(): array
    {
        return [
            '20 lusin aj' => ['20 lusin aj'],
            '20 lusin ya' => ['20 lusin ya'],
            'yg 20 lusin dong' => ['yg 20 lusin dong'],
            'aj 20 lusin' => ['aj 20 lusin'],
            '20 lsn aja kak' => ['20 lsn aja kak'],
            'tolong 20 lusin sj' => ['tolong 20 lusin sj'],
            'Mau 20 Lusin Yaa!' => ['Mau 20 Lusin Yaa!'],
        ];
    }

    #[DataProvider('quantityWithFillers')]
    public function test_quantity_with_fillers_after_refusal_goes_to_the_last_product(string $message): void
    {
        $this->refuse40Lusin();
        OpenAI::fake([]);

        $this->send($message);

        $this->assertSame(20.0, $this->kotakQty());
        OpenAI::assertNothingSent(); // tidak ada pencarian produk / GPT
    }

    public function test_quantity_with_fillers_is_still_stock_checked(): void
    {
        $this->refuse40Lusin();
        OpenAI::fake([]);

        $reply = $this->send('35 lusin aja');

        $this->assertNull($this->kotakQty());
        $this->assertStringContainsString('hanya 30 Lusin, jadi 35 Lusin', $reply);
    }

    public function test_gpt_extracting_filler_as_item_goes_to_last_product_not_a_search(): void
    {
        $this->refuse40Lusin();
        // A phrasing the quantity-only parser doesn't catch reaches GPT, which
        // returns the filler itself as the item.
        OpenAI::fake([$this->bulkIntent([['item' => 'aj', 'qty' => 20, 'unit' => 'lusin']])]);

        $reply = $this->send('yaudah 20 lusin aj kalo gitu');

        $this->assertSame(20.0, $this->kotakQty());
        $this->assertStringNotContainsString('ada beberapa pilihan', $reply);
        $this->assertNull($this->currentSession()->pending_clarification);
    }

    public function test_filler_only_quantity_without_last_product_asks_for_the_product_name(): void
    {
        WhatsAppSession::create(['phone_number' => self::PHONE, 'step' => 'chat', 'messages' => [], 'cart' => []]);
        OpenAI::fake([]);

        $reply = $this->send('20 lusin aj');

        $this->assertStringContainsString('Produk apa yang dimaksud untuk 20 Lusin?', $reply);
        $this->assertStringNotContainsString('BAJU', $reply);
        $this->assertSame([], $this->currentSession()->cart);
        $this->assertNull($this->currentSession()->pending_clarification);
        OpenAI::assertNothingSent();
    }

    public function test_gpt_filler_item_without_last_product_asks_for_the_product_name(): void
    {
        WhatsAppSession::create(['phone_number' => self::PHONE, 'step' => 'chat', 'messages' => [], 'cart' => []]);
        OpenAI::fake([$this->bulkIntent([['item' => 'aj', 'qty' => 20, 'unit' => 'lusin']])]);

        $reply = $this->send('yaudah 20 lusin aj kalo gitu');

        $this->assertStringContainsString('Produk apa yang dimaksud untuk 20 Lusin?', $reply);
        $this->assertNull($this->currentSession()->pending_clarification);
    }

    public function test_filler_keywords_do_not_pull_unrelated_products_into_the_prompt(): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['mode' => 'chat', 'keywords' => ['aj', 'kotak trisula'], 'bulk_items' => []]),
            ]]]]),
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['reply' => 'Ada kak.', 'show_products' => [], 'cart_add' => []]),
            ]]]]),
        ]);

        $this->send('kotak trisula ada aj?');

        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => str_contains($params['messages'][0]['content'], 'KOTAK TRISULA 900 - Rp')
            && ! str_contains($params['messages'][0]['content'], 'BAJU'));
    }

    public function test_fillers_around_a_bulk_item_name_are_stripped(): void
    {
        OpenAI::fake([$this->bulkIntent([['item' => 'tolong kotak trisula 900 aja', 'qty' => 2, 'unit' => 'lusin']])]);

        $this->send('tolong kotak trisula 900 aja 2 lusin, sama itu');

        $this->assertSame(2.0, $this->kotakQty());
    }

    public function test_product_name_with_a_filler_word_in_the_middle_is_kept(): void
    {
        $panci = $this->product('PANCI BOS 24', 120);
        OpenAI::fake([$this->bulkIntent([['item' => 'panci bos 24', 'qty' => 2, 'unit' => 'lusin']])]);

        $this->send('panci bos 24 2 lusin, kotak trisula 900 1 lusin');

        $this->assertSame(2.0, (float) collect($this->currentSession()->cart)->firstWhere('product_id', $panci->id)['qty']);
    }

    public function test_clarification_reply_with_fillers_still_picks_by_number(): void
    {
        WhatsAppSession::create(['phone_number' => self::PHONE, 'step' => 'chat', 'messages' => [], 'cart' => [],
            'last_shown_products' => [$this->kotak->id, Product::where('product_name', 'KALO BAJU OVAL II')->value('id')]]);
        OpenAI::fake([]);

        $this->assertStringContainsString('Produk yang mana untuk 2 Lusin?', $this->send('2 lusin aja'));
        $this->send('1 aja kak');

        $this->assertSame(2.0, $this->kotakQty());
    }
}
