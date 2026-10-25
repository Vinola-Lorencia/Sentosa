<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Covers generateChatReply()'s database-built product list: when GPT only
 * answers with an intro ("Berikut adalah daftar kobokan yang tersedia:")
 * and names none of the matched products, the list is appended from the DB.
 */
class WhatsAppChatProductListFallbackTest extends TestCase
{
    use RefreshDatabase;

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

    private function createProduct(string $name, int $price = 15000, int $stock = 770): Product
    {
        return Product::create([
            'product_name' => $name,
            'sale_price' => $price,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => $stock,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
        ]);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function fakeGpt(array $keywords, string $reply): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['mode' => 'chat', 'keywords' => $keywords, 'bulk_items' => []]),
            ]]]]),
            CreateResponse::fake(['choices' => [['message' => [
                'content' => json_encode(['reply' => $reply, 'show_products' => [], 'cart_add' => []]),
            ]]]]),
        ]);
    }

    private function sendMessage(string $message): string
    {
        $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => $message,
            'text' => 'non-button message',
        ])->assertStatus(200);

        $sent = Http::recorded(fn (HttpRequest $request) => $request->url() === 'https://api.fonnte.com/send');
        $this->assertNotEmpty($sent);

        return (string) $sent[0][0]['message'];
    }

    public function test_intro_only_reply_gets_product_list_from_database(): void
    {
        $this->createProduct('KOBOKAN 03', 15000, 770);
        $this->createProduct('KOBOKAN 013', 1250000, 5);
        $this->createProduct('EMBER 10L');
        $this->fakeGpt(['kobokan'], 'Berikut adalah daftar kobokan yang tersedia:');

        $reply = $this->sendMessage('kobokan');

        $this->assertSame(
            "Berikut adalah daftar kobokan yang tersedia:\n\n"
            ."- KOBOKAN 03 - Rp15.000/Lusin (stok: 770)\n"
            .'- KOBOKAN 013 - Rp1.250.000/Lusin (stok: 5)',
            $reply
        );

        // The appended list is what the customer saw, so it's what history keeps.
        $session = WhatsAppSession::firstOrFail();
        $this->assertSame($reply, collect($session->messages)->last()['content']);
    }

    public function test_reply_already_naming_a_product_is_not_duplicated(): void
    {
        $this->createProduct('KOBOKAN 03');
        $this->createProduct('KOBOKAN 013');
        $gptReply = "Ada:\n- Kobokan 03 - Rp15.000/Lusin";
        $this->fakeGpt(['kobokan'], $gptReply);

        $this->assertSame($gptReply, $this->sendMessage('kobokan'));
    }

    public function test_more_than_ten_products_lists_first_ten_plus_hint(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createProduct(sprintf('BASKOM %02d', $i));
        }
        $this->fakeGpt(['baskom'], 'Berikut daftar baskom:');

        $reply = $this->sendMessage('baskom');
        $lines = explode("\n", $reply);

        $this->assertSame('Berikut daftar baskom:', $lines[0]);
        $this->assertSame('', $lines[1]);
        $productLines = array_values(array_filter($lines, fn ($line) => str_starts_with($line, '- BASKOM')));
        $this->assertCount(10, $productLines);
        $this->assertSame('- BASKOM 01 - Rp15.000/Lusin (stok: 770)', $productLines[0]);
        $this->assertStringNotContainsString('BASKOM 11', $reply);
        $this->assertSame('Ketik nama produk untuk info lebih detail.', end($lines));
    }

    public function test_no_list_appended_when_keywords_match_nothing_and_catalog_fallback_is_used(): void
    {
        // searchByKeywords() falls back to the whole catalog when nothing
        // matches "ember" — that catalog must not be appended.
        $this->createProduct('KOBOKAN 03');
        $this->fakeGpt(['ember'], 'Maaf, ember sedang tidak tersedia.');

        $this->assertSame('Maaf, ember sedang tidak tersedia.', $this->sendMessage('ada ember?'));
    }

    public function test_no_list_appended_without_keywords(): void
    {
        $this->createProduct('KOBOKAN 03');
        $this->fakeGpt([], 'Halo! Ada yang bisa kami bantu?');

        $this->assertSame('Halo! Ada yang bisa kami bantu?', $this->sendMessage('halo'));
    }
}
