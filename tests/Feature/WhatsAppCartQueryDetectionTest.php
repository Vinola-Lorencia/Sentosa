<?php

namespace Tests\Feature;

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
 * Covers isLikelyCartQuery(): short price questions containing "berapa"
 * (e.g. "kobokan 03 berapa") must reach product search, while explicit
 * cart/total questions are still answered from the cart without GPT.
 */
class WhatsAppCartQueryDetectionTest extends TestCase
{
    use RefreshDatabase;

    private const EMPTY_CART_REPLY = 'Keranjang masih kosong. Silakan pilih produk terlebih dahulu.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fonnte.token' => 'test-fonnte-token']);

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

    private function postFonnte(string $message): void
    {
        $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => $message,
            'text' => 'non-button message',
        ])->assertStatus(200);
    }

    private function assertReplySent(string $message): void
    {
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.fonnte.com/send'
            && $request['message'] === $message);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function productQuestions(): array
    {
        return [
            'harga + berapa' => ['harga baskom 88 berapa?'],
            'typo arga + berapa' => ['arga baskom 88 berapa ya?'],
            'product code + berapa' => ['kobokan 03 berapa'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cartQuestions(): array
    {
        return [
            'total belanja' => ['total belanja saya berapa?'],
            'berapa total' => ['berapa total?'],
            'lihat keranjang' => ['lihat keranjang'],
            // Cart phrases are checked before product words like "ada".
            'isi keranjang + ada' => ['isi keranjang ada apa aja'],
            // Plain substring "cart" outranks "harga" too — accepted trade-off.
            'cart + harga' => ['harga cart ember berapa'],
        ];
    }

    #[DataProvider('productQuestions')]
    public function test_price_question_is_not_treated_as_cart_query(string $message): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['mode' => 'chat', 'keywords' => ['baskom'], 'bulk_items' => []]),
            ]]]]),
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['reply' => 'Harganya Rp10.000/buah.', 'show_products' => [], 'cart_add' => []]),
            ]]]]),
        ]);

        $this->postFonnte($message);

        OpenAI::assertSent(Chat::class, 2);
        $this->assertReplySent('Harganya Rp10.000/buah.');
        Http::assertNotSent(fn (HttpRequest $request) => $request['message'] === self::EMPTY_CART_REPLY);
    }

    #[DataProvider('cartQuestions')]
    public function test_explicit_cart_question_is_answered_from_cart_without_gpt(string $message): void
    {
        OpenAI::fake([]);

        $this->postFonnte($message);

        $this->assertReplySent(self::EMPTY_CART_REPLY);
        OpenAI::assertNothingSent();
    }

    public function test_explicit_total_phrase_wins_over_product_words(): void
    {
        OpenAI::fake([]);
        WhatsAppSession::create([
            'phone_number' => 'whatsapp:+6281234567890',
            'step' => 'chat',
            'cart' => [['product_id' => 1, 'product_name' => 'Panci', 'qty' => 2, 'unit' => 'Buah', 'price' => 1000, 'subtotal' => 2000]],
            'messages' => [],
        ]);

        $this->postFonnte('harga semuanya berapa ya, totalnya?');

        OpenAI::assertNothingSent();
        Http::assertSent(fn (HttpRequest $request) => str_contains((string) $request['message'], 'Panci'));
    }
}
