<?php

namespace Tests\Feature;

use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Covers the Fonnte provider (replacing Twilio): incoming webhook payload
 * parsing — sender/message/text fields normalized into the same
 * 'whatsapp:+62xxx' shape every provider's parser produces — and outgoing
 * replies posted to Fonnte's /send endpoint with the correct
 * target/message/Authorization format.
 */
class WhatsAppFonnteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token']);

        // WHATSAPP_PROVIDER is read via env() directly in the controller
        // (matching the existing Twilio/Meta switch), so it must be forced
        // here rather than via config() to make the test independent of
        // whatever the real .env happens to hold.
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

    private function fakeChatIntent(string $mode = 'chat'): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'message' => [
                    'content' => json_encode(['mode' => $mode, 'keywords' => [], 'bulk_items' => []]),
                ],
            ]],
        ]);
    }

    private function fakeChatReply(string $reply): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'message' => [
                    'content' => json_encode(['reply' => $reply, 'show_products' => [], 'cart_add' => []]),
                ],
            ]],
        ]);
    }

    public function test_incoming_fonnte_message_creates_session_with_normalized_phone()
    {
        OpenAI::fake([
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Halo! Ada yang bisa kami bantu?'),
        ]);

        $response = $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => 'halo',
            'name' => 'Budi',
        ]);

        $response->assertStatus(200);

        $session = WhatsAppSession::first();
        $this->assertNotNull($session);
        $this->assertEquals('whatsapp:+6281234567890', $session->phone_number);
    }

    public function test_outgoing_reply_is_sent_to_fonnte_send_endpoint_with_correct_format()
    {
        OpenAI::fake([
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Halo! Ada yang bisa kami bantu?'),
        ]);

        $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => 'halo',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request['target'] === '6281234567890'
                && $request['message'] === 'Halo! Ada yang bisa kami bantu?'
                && $request->hasHeader('Authorization', 'test-fonnte-token');
        });
    }

    public function test_incoming_fonnte_button_reply_prefers_text_field_over_message_field()
    {
        // Fonnte puts a button/list reply's actual chosen label in "text";
        // "message" for that same event carries the button id, not the label.
        OpenAI::fake([
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Baik, dicatat.'),
        ]);

        $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => 'BTN_ID_1',
            'text' => 'Transfer Bank',
        ]);

        $session = WhatsAppSession::first();
        $lastUserMessage = collect($session->messages ?? [])
            ->last(fn ($m) => $m['role'] === 'user');

        $this->assertNotNull($lastUserMessage);
        $this->assertEquals('Transfer Bank', $lastUserMessage['content']);
    }

    public function test_incoming_fonnte_ordinary_text_message_uses_message_field_not_placeholder()
    {
        // Fonnte always sends "text" on every webhook call, even for a plain
        // text message — but it's just the literal placeholder
        // "non-button message" in that case (buttons are deprecated
        // platform-wide), never the customer's actual words. The real
        // content is always in "message".
        OpenAI::fake([
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Halo! Ada yang bisa kami bantu?'),
        ]);

        $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => 'ada panci gak?',
            'text' => 'non-button message',
        ]);

        $session = WhatsAppSession::first();
        $lastUserMessage = collect($session->messages ?? [])
            ->last(fn ($m) => $m['role'] === 'user');

        $this->assertNotNull($lastUserMessage);
        $this->assertEquals('ada panci gak?', $lastUserMessage['content']);
    }

    public function test_chat_reply_is_logged_with_body_truncated_reply_and_product_count()
    {
        Log::spy();
        $longReply = str_repeat('a', 1500);
        OpenAI::fake([
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply($longReply),
        ]);

        $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => 'ada panci gak?',
        ]);

        Log::shouldHaveReceived('info')->with('Chat reply generated', \Mockery::on(
            fn (array $context) => $context['body'] === 'ada panci gak?'
                && $context['reply'] === str_repeat('a', 1000)
                && $context['products_in_prompt'] === 0
        ));
    }

    public function test_missing_sender_is_ignored_without_creating_a_session()
    {
        $response = $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'message' => 'halo',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, WhatsAppSession::count());
        Http::assertNothingSent();
    }
}
