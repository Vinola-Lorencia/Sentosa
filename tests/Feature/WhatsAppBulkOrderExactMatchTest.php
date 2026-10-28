<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Covers a bulk-order UX bug: typing a product name that is an EXACT
 * (case-insensitive) match for one product must add it straight to the
 * cart, even when that same name also LIKE-matches other, differently
 * named products — searchProduct() must prefer the exact match outright
 * instead of treating it as ambiguous and asking for clarification.
 */
class WhatsAppBulkOrderExactMatchTest extends TestCase
{
    use RefreshDatabase;

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

    private function fakeBulkIntent(string $item, float $qty, string $unit): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'mode' => 'bulk',
                        'keywords' => [],
                        'bulk_items' => [
                            ['item' => $item, 'qty' => $qty, 'unit' => $unit],
                        ],
                    ]),
                ],
            ]],
        ]);
    }

    public function test_exact_name_match_adds_to_cart_without_asking_for_clarification()
    {
        $exactProduct = Product::create([
            'product_name' => 'KOBOKAN 03',
            'sale_price' => 15000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 770,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
        ]);

        // Also LIKE-matches "%kobokan 03%" — without the exact-match-first
        // fix, Pass 1 of searchProduct() would return BOTH rows and trigger
        // a clarification prompt even though the customer's text is an
        // unambiguous exact match for $exactProduct.
        Product::create([
            'product_name' => 'KOBOKAN 03 JUMBO',
            'sale_price' => 18000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 200,
            'default_buy_price' => 12000,
            'default_buy_discount' => 0,
        ]);

        OpenAI::fake([
            $this->fakeBulkIntent('KOBOKAN 03', 3, 'Lusin'),
        ]);

        $response = $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
            'device' => '6281111111111',
            'sender' => '6281234567890',
            'message' => 'KOBOKAN 03 3 Lusin',
        ]);

        $response->assertStatus(200);

        $session = WhatsAppSession::where('phone_number', 'whatsapp:+6281234567890')->firstOrFail();

        $this->assertNull($session->pending_clarification);
        $this->assertCount(1, $session->cart);
        $this->assertSame($exactProduct->id, $session->cart[0]['product_id']);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.fonnte.com/send') {
                return false;
            }

            $message = $request['message'];

            return str_contains($message, 'KOBOKAN 03')
                && ! str_contains($message, 'ada beberapa pilihan')
                && ! str_contains($message, '❓');
        });
    }

    public function test_genuinely_ambiguous_name_still_asks_for_clarification()
    {
        // No product named exactly "baskom" — both only LIKE-match it, so
        // this must still go through the normal clarification flow.
        Product::create([
            'product_name' => 'BASKOM 68',
            'sale_price' => 15000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 194,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
        ]);

        Product::create([
            'product_name' => 'BASKOM JERMAN 86',
            'sale_price' => 16000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 140,
            'default_buy_price' => 11000,
            'default_buy_discount' => 0,
        ]);

        OpenAI::fake([
            $this->fakeBulkIntent('baskom', 2, 'Lusin'),
        ]);

        $response = $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
            'device' => '6281111111111',
            'sender' => '6281234567891',
            'message' => 'baskom 2 Lusin',
        ]);

        $response->assertStatus(200);

        $session = WhatsAppSession::where('phone_number', 'whatsapp:+6281234567891')->firstOrFail();

        $this->assertNotNull($session->pending_clarification);
        $this->assertEmpty($session->cart);
    }
}
