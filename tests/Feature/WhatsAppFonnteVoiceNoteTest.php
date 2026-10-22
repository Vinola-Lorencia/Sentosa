<?php

namespace Tests\Feature;

use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Audio;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Covers voice notes (and other non-text messages) arriving through the
 * Fonnte webhook: the audio URL is downloaded without auth, transcribed with
 * Whisper and fed into the normal conversation flow — with the
 * "[Voice Note] " marker stripped so step matching still works — while
 * photos/stickers get a fixed reply without touching GPT or the session.
 */
class WhatsAppFonnteVoiceNoteTest extends TestCase
{
    use RefreshDatabase;

    private const AUDIO_URL = 'https://files.fonnte.test/media/voice123.ogg';

    private const PHONE = 'whatsapp:+6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token']);

        putenv('WHATSAPP_PROVIDER=fonnte');
        $_ENV['WHATSAPP_PROVIDER'] = 'fonnte';
        $_SERVER['WHATSAPP_PROVIDER'] = 'fonnte';
    }

    protected function tearDown(): void
    {
        putenv('WHATSAPP_PROVIDER');
        unset($_ENV['WHATSAPP_PROVIDER'], $_SERVER['WHATSAPP_PROVIDER']);

        parent::tearDown();
    }

    private function fakeHttp(int $audioStatus = 200): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200),
            'files.fonnte.test/*' => Http::response('OggS-fake-audio-bytes', $audioStatus),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postFonnte(array $overrides = []): void
    {
        $this->postJson('/webhook/whatsapp', array_merge([
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => '',
            'text' => 'non-button message',
            'token' => 'secret-device-token',
        ], $overrides))->assertStatus(200);
    }

    private function fakeTranscription(string $text): TranscriptionResponse
    {
        return TranscriptionResponse::fake(['text' => $text]);
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

    private function assertFonnteReplySent(string $message): void
    {
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '6281234567890'
            && $request['message'] === $message);
    }

    private function assertNoTempVoiceFilesLeft(): void
    {
        $this->assertSame([], glob(storage_path('app/temp/voice_fonnte_*')) ?: []);
    }

    public function test_voice_note_is_downloaded_without_auth_transcribed_and_answered(): void
    {
        $this->fakeHttp();
        OpenAI::fake([
            $this->fakeTranscription('Ada panci gak?'),
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Ada, panci tersedia.'),
        ]);

        $this->postFonnte(['url' => self::AUDIO_URL, 'extension' => 'ogg']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::AUDIO_URL
            && $request->method() === 'GET'
            && ! $request->hasHeader('Authorization'));

        OpenAI::assertSent(Audio::class, fn (string $method, array $parameters) => $method === 'transcribe'
            && $parameters['model'] === 'whisper-1'
            && $parameters['language'] === 'id');

        $session = WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
        $lastUserMessage = collect($session->messages)->last(fn ($m) => $m['role'] === 'user');
        $this->assertSame('[Voice Note] Ada panci gak?', $lastUserMessage['content']);

        $this->assertFonnteReplySent('Ada, panci tersedia.');
        $this->assertNoTempVoiceFilesLeft();
    }

    public function test_voice_note_url_without_extension_field_is_detected_from_other_media_fields(): void
    {
        $this->fakeHttp();
        OpenAI::fake([
            $this->fakeTranscription('halo'),
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Halo juga!'),
        ]);

        $this->postFonnte(['url' => 'not a link', 'file' => self::AUDIO_URL]);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::AUDIO_URL);
        $this->assertFonnteReplySent('Halo juga!');
    }

    public function test_voice_note_download_failure_replies_with_error_and_stops(): void
    {
        $this->fakeHttp(audioStatus: 500);
        OpenAI::fake([]);

        $this->postFonnte(['url' => self::AUDIO_URL, 'extension' => 'ogg']);

        $this->assertFonnteReplySent('Maaf, kami tidak dapat memproses pesan suara Anda. Silakan kirim pesan teks.');
        OpenAI::assertNothingSent();
        $this->assertSame(0, WhatsAppSession::count());
    }

    public function test_voice_note_whisper_failure_replies_with_error_and_cleans_up_temp_file(): void
    {
        $this->fakeHttp();
        OpenAI::fake([new \RuntimeException('Whisper down')]);

        $this->postFonnte(['url' => self::AUDIO_URL, 'extension' => 'ogg']);

        $this->assertFonnteReplySent('Maaf, kami tidak dapat memproses pesan suara Anda. Silakan kirim pesan teks.');
        OpenAI::assertNotSent(Chat::class);
        $this->assertSame(0, WhatsAppSession::count());
        $this->assertNoTempVoiceFilesLeft();
    }

    public function test_photo_without_caption_gets_fixed_reply_without_gpt_or_session_changes(): void
    {
        $this->fakeHttp();
        OpenAI::fake([]);
        Log::spy();

        $cart = [['product_id' => 1, 'product_name' => 'Panci', 'qty' => 2, 'unit' => 'Buah', 'price' => 1000, 'subtotal' => 2000]];
        $clarification = [['original' => 'baskom', 'options' => [['id' => 1, 'name' => 'A']], 'qty' => 1, 'unit' => 'Buah']];
        $session = WhatsAppSession::create([
            'phone_number' => self::PHONE,
            'step' => 'need_name',
            'cart' => $cart,
            'messages' => [],
            'pending_clarification' => $clarification,
            'customer_name' => null,
            'address' => 'Jl. Lama 1',
        ]);

        $this->postFonnte(['url' => 'https://files.fonnte.test/media/photo.jpg', 'extension' => 'jpg']);

        $this->assertFonnteReplySent('Maaf, saya hanya bisa membaca pesan teks atau voice note. Silakan ketik produk yang ingin dipesan ya 🙏');
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'photo.jpg'));
        OpenAI::assertNothingSent();

        $session->refresh();
        $this->assertSame('need_name', $session->step);
        $this->assertSame($cart, $session->cart);
        $this->assertSame($clarification, $session->pending_clarification);
        $this->assertNull($session->customer_name);
        $this->assertSame('Jl. Lama 1', $session->address);

        Log::shouldHaveReceived('info')->with('Fonnte media payload', Mockery::on(
            fn (array $context) => ($context['payload']['sender'] ?? null) === '6281234567890'
                && ! array_key_exists('token', $context['payload'])
        ));
    }

    public function test_empty_message_without_any_media_gets_fixed_reply(): void
    {
        $this->fakeHttp();
        OpenAI::fake([]);

        $this->postFonnte();

        $this->assertFonnteReplySent('Maaf, saya hanya bisa membaca pesan teks atau voice note. Silakan ketik produk yang ingin dipesan ya 🙏');
        OpenAI::assertNothingSent();
        $this->assertSame(0, WhatsAppSession::count());
    }

    public function test_non_text_message_placeholder_with_audio_url_is_transcribed(): void
    {
        // Production payload: Fonnte sends the literal "non-text message"
        // in "message" for a voice note instead of leaving it empty.
        $this->fakeHttp();
        OpenAI::fake([
            $this->fakeTranscription('Ada ember?'),
            $this->fakeChatIntent('chat'),
            $this->fakeChatReply('Ada, ember tersedia.'),
        ]);

        $this->postFonnte(['message' => 'non-text message', 'url' => self::AUDIO_URL, 'extension' => 'ogg']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::AUDIO_URL);
        OpenAI::assertSent(Audio::class);

        $session = WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
        $lastUserMessage = collect($session->messages)->last(fn ($m) => $m['role'] === 'user');
        $this->assertSame('[Voice Note] Ada ember?', $lastUserMessage['content']);
        $this->assertFonnteReplySent('Ada, ember tersedia.');
    }

    public function test_non_text_message_placeholder_with_photo_url_gets_fixed_reply_without_gpt(): void
    {
        $this->fakeHttp();
        OpenAI::fake([]);

        // Case-insensitive and trimmed, like FONNTE_NON_BUTTON_TEXT.
        $this->postFonnte(['message' => '  Non-Text Message ', 'url' => 'https://files.fonnte.test/media/photo.jpg', 'extension' => 'jpg']);

        $this->assertFonnteReplySent('Maaf, saya hanya bisa membaca pesan teks atau voice note. Silakan ketik produk yang ingin dipesan ya 🙏');
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'photo.jpg'));
        OpenAI::assertNothingSent();
        $this->assertSame(0, WhatsAppSession::count());
    }

    public function test_non_text_message_placeholder_without_url_gets_fixed_reply(): void
    {
        $this->fakeHttp();
        OpenAI::fake([]);
        Log::spy();

        $this->postFonnte(['message' => 'non-text message']);

        $this->assertFonnteReplySent('Maaf, saya hanya bisa membaca pesan teks atau voice note. Silakan ketik produk yang ingin dipesan ya 🙏');
        OpenAI::assertNothingSent();
        $this->assertSame(0, WhatsAppSession::count());
        Log::shouldHaveReceived('info')->with('Fonnte media payload', Mockery::on(
            fn (array $context) => ($context['payload']['message'] ?? null) === 'non-text message'
                && ! array_key_exists('token', $context['payload'])
        ));
    }

    public function test_voice_ya_confirms_order_at_confirm_order_step(): void
    {
        $this->fakeHttp();
        // Only the transcription is faked: confirmation must be matched
        // locally, so any GPT call would fail with "No fake responses left".
        OpenAI::fake([$this->fakeTranscription('Ya.')]);

        WhatsAppSession::create([
            'phone_number' => self::PHONE,
            'step' => 'confirm_order',
            'cart' => [['product_id' => 1, 'product_name' => 'Panci', 'qty' => 2, 'unit' => 'Buah', 'price' => 1000, 'subtotal' => 2000]],
            'messages' => [],
        ]);

        $this->postFonnte(['url' => self::AUDIO_URL, 'extension' => 'ogg']);

        $this->assertSame('need_name', WhatsAppSession::where('phone_number', self::PHONE)->value('step'));
        $this->assertFonnteReplySent('Siap! Boleh minta nama Anda untuk pesanan ini?');
        OpenAI::assertNotSent(Chat::class);
    }

    public function test_voice_ambil_sendiri_is_recognized_as_pickup_at_need_address_step(): void
    {
        $this->fakeHttp();
        OpenAI::fake([$this->fakeTranscription('Ambil sendiri.')]);

        WhatsAppSession::create([
            'phone_number' => self::PHONE,
            'step' => 'need_address',
            'cart' => [['product_id' => 1, 'product_name' => 'Panci', 'qty' => 2, 'unit' => 'Buah', 'price' => 1000, 'subtotal' => 2000]],
            'messages' => [],
            'customer_name' => 'Budi',
        ]);

        $this->postFonnte(['url' => self::AUDIO_URL, 'extension' => 'ogg']);

        $session = WhatsAppSession::where('phone_number', self::PHONE)->firstOrFail();
        $this->assertSame('need_payment', $session->step);
        $this->assertSame('Ambil Sendiri di Toko', $session->address);
    }

    public function test_voice_name_is_saved_without_prefix_or_trailing_punctuation(): void
    {
        $this->fakeHttp();
        OpenAI::fake([$this->fakeTranscription('Budi Santoso.')]);

        WhatsAppSession::create([
            'phone_number' => self::PHONE,
            'step' => 'need_name',
            'cart' => [],
            'messages' => [],
        ]);

        $this->postFonnte(['url' => self::AUDIO_URL, 'extension' => 'ogg']);

        $this->assertSame('Budi Santoso', WhatsAppSession::where('phone_number', self::PHONE)->value('customer_name'));
    }
}
