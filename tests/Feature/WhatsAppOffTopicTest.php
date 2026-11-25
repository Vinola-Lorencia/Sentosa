<?php

namespace Tests\Feature;

use App\Http\Controllers\WhatsAppController;
use App\Models\Product;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * The chatbot only helps with Toko Sentosa matters. Off-topic messages
 * ("siapa penemu gravitasi") get a fixed reply without a second GPT call,
 * and the chat prompt carries a topic limit as a second safety net.
 */
class WhatsAppOffTopicTest extends TestCase
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

    private function product(string $name = 'MY BOTTLE'): Product
    {
        return Product::create([
            'product_name' => $name, 'sale_price' => 66000, 'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 360,
            'default_buy_price' => 0, 'default_buy_discount' => 0,
        ]);
    }

    private function send(string $message): void
    {
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
            'device' => '6281111111111', 'sender' => '6281234567890',
            'message' => $message, 'text' => 'non-button message',
        ])->assertOk();
    }

    private function lastReply(): string
    {
        return (string) collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send')
            ->map(fn (HttpRequest $r) => (string) $r['message'])
            ->last();
    }

    /**
     * @param  list<string>  $keywords
     */
    private function intent(string $mode, array $keywords = []): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['mode' => $mode, 'keywords' => $keywords, 'bulk_items' => []]),
        ]]]]);
    }

    private function chatReply(string $reply): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['message' => [
            'content' => json_encode(['reply' => $reply, 'show_products' => [], 'cart_add' => []]),
        ]]]]);
    }

    public function test_off_topic_message_gets_fixed_reply_without_second_gpt_call(): void
    {
        $this->product();
        OpenAI::fake([
            $this->intent('off_topic'),
            // Would be used if the controller (wrongly) asked GPT for a reply.
            $this->chatReply('Penemu gravitasi adalah Isaac Newton.'),
        ]);

        $this->send('kamu tau ga siapa penemu gravitasi');

        $this->assertSame(WhatsAppController::OFF_TOPIC_REPLY, $this->lastReply());
        $this->assertStringNotContainsString('Newton', $this->lastReply());
        OpenAI::assertSent(Chat::class, 1);
    }

    public function test_off_topic_message_keeps_existing_cart_and_step(): void
    {
        $bottle = $this->product();
        $cart = [[
            'product_id' => $bottle->id, 'product_name' => 'MY BOTTLE', 'qty' => 2, 'unit' => 'Lusin', 'price' => 66000, 'subtotal' => 132000,
        ]];
        WhatsAppSession::create(['phone_number' => self::PHONE, 'step' => 'chat', 'messages' => [], 'cart' => $cart]);
        OpenAI::fake([$this->intent('off_topic')]);

        $this->send('buatkan puisi tentang hujan dong');

        $session = WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
        $this->assertSame(WhatsAppController::OFF_TOPIC_REPLY, $this->lastReply());
        $this->assertEquals($cart, $session->cart);
        $this->assertSame('chat', $session->step);
    }

    public function test_extract_intent_prompt_offers_off_topic_mode(): void
    {
        OpenAI::fake([$this->intent('off_topic')]);

        $this->send('siapa presiden pertama amerika');

        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => str_contains($params['messages'][0]['content'], 'Kamu adalah ekstraktor intent')
            && str_contains($params['messages'][0]['content'], '"off_topic"'));
    }

    public function test_chat_reply_prompt_has_topic_limit(): void
    {
        $this->product();
        OpenAI::fake([$this->intent('chat', ['my bottle']), $this->chatReply('Ada kak.')]);

        $this->send('my bottle ada?');

        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => str_contains($params['messages'][0]['content'], 'asisten penjualan Toko Sentosa')
            && str_contains($params['messages'][0]['content'], 'BATASAN TOPIK'));
    }
}
