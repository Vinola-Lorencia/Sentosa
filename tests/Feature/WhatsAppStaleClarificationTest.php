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
 * Covers a real reported bug: a leftover, never-resolved
 * pending_clarification from an earlier test/conversation silently hijacked
 * a brand new, well-formed multi-item bulk order. handle()'s Priority 1d
 * unconditionally routed ANY incoming message into resolveClarification()
 * whenever pending_clarification was non-empty — and that resolver's
 * "position pick" shortcut matched a lone digit 1-9 ANYWHERE in the text
 * (e.g. the "2" in "KOBOKAN 03 2 Lusin, ..."), silently resolving every
 * still-pending item using its stale leftover qty/unit instead of letting
 * the new message reach extractIntent()/handleBulkOrder() at all.
 */
class WhatsAppStaleClarificationTest extends TestCase
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

    public function test_new_multi_item_bulk_order_is_not_hijacked_by_stale_pending_clarification()
    {
        $kobokan = Product::create([
            'product_name' => 'KOBOKAN 03',
            'sale_price' => 15000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 770,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
        ]);

        // Deliberately also one of the stale pending clarification's
        // options below (by id AND name) — in the real bug report, the new
        // order's own items ("BASKOM JERMAN 86 1 Lusin", etc.) textually
        // overlapped with the leftover "baskom" clarification's option
        // names, so resolveClarification()'s keyword-matching pass alone
        // (independent of the position-pick regex) could still wrongly
        // "resolve" the stale item using its stale qty/unit — this is what
        // actually requires skipping clarification-resolution entirely for
        // a message that looks like a new bulk order, not just tightening
        // the position-pick regex.
        $baskomJerman = Product::create([
            'product_name' => 'BASKOM JERMAN 86',
            'sale_price' => 16000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 140,
            'default_buy_price' => 11000,
            'default_buy_discount' => 0,
        ]);

        // Simulates an earlier, never-resolved "baskom" clarification test —
        // stale qty/unit (5 Buah) left dangling in the session, unrelated to
        // the new order below.
        $session = WhatsAppSession::create([
            'phone_number' => 'whatsapp:+6287720981167',
            'step' => 'chat',
            'cart' => [],
            'messages' => [],
            'pending_clarification' => [
                [
                    'original' => 'baskom',
                    'options' => [
                        ['id' => $baskomJerman->id, 'name' => 'BASKOM JERMAN 86'],
                        ['id' => 9002, 'name' => 'BASKOM 088'],
                    ],
                    'qty' => 5,
                    'unit' => 'Buah',
                ],
            ],
        ]);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'mode' => 'bulk',
                            'keywords' => [],
                            'bulk_items' => [
                                ['item' => 'KOBOKAN 03', 'qty' => 2, 'unit' => 'Lusin'],
                                ['item' => 'BASKOM JERMAN 86', 'qty' => 1, 'unit' => 'Lusin'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $response = $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6287720981167',
            'message' => 'KOBOKAN 03 2 Lusin, BASKOM JERMAN 86 1 Lusin',
        ]);

        $response->assertStatus(200);

        $session->refresh();

        // The new order's actual 2 items, each with the NEW message's own
        // qty — not the stale "baskom" clarification's leftover 5 Buah.
        $this->assertCount(2, $session->cart);

        $byProductId = collect($session->cart)->keyBy('product_id');
        $this->assertTrue($byProductId->has($kobokan->id));
        $this->assertTrue($byProductId->has($baskomJerman->id));
        $this->assertEquals(2.0, (float) $byProductId[$kobokan->id]['qty']);
        $this->assertEquals(1.0, (float) $byProductId[$baskomJerman->id]['qty']);
    }

    public function test_cancel_clears_pending_clarification_as_well_as_cart()
    {
        $session = WhatsAppSession::create([
            'phone_number' => 'whatsapp:+6281234500000',
            'step' => 'chat',
            'cart' => [
                ['product_id' => 1, 'product_name' => 'X', 'qty' => 1, 'unit' => 'Buah', 'price' => 1000, 'subtotal' => 1000],
            ],
            'messages' => [],
            'pending_clarification' => [
                ['original' => 'baskom', 'options' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']], 'qty' => 1, 'unit' => 'Buah'],
            ],
        ]);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['mode' => 'cancel', 'keywords' => [], 'bulk_items' => []]),
                    ],
                ]],
            ]),
        ]);

        $response = $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234500000',
            'message' => 'batal semua pesanan saya tolong dibatalkan secepatnya',
        ]);

        $response->assertStatus(200);

        $session->refresh();

        $this->assertEmpty($session->cart);
        $this->assertNull($session->pending_clarification);
    }

    public function test_stray_digit_in_a_long_unrelated_message_does_not_trigger_position_pick()
    {
        // Must be REAL products — the number-pick branch looks the chosen
        // option's id up via Product::find() and silently no-ops (via an
        // unconditional `continue`) if it doesn't resolve, which would mask
        // whether the length guard below is actually doing anything.
        $baskomMerah = Product::create([
            'product_name' => 'BASKOM MERAH',
            'sale_price' => 15000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 50,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
        ]);

        $baskomBiru = Product::create([
            'product_name' => 'BASKOM BIRU',
            'sale_price' => 15500,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 50,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
        ]);

        $session = WhatsAppSession::create([
            'phone_number' => 'whatsapp:+6281234511111',
            'step' => 'chat',
            'cart' => [],
            'messages' => [],
            'pending_clarification' => [
                [
                    'original' => 'baskom',
                    'options' => [
                        ['id' => $baskomMerah->id, 'name' => 'BASKOM MERAH'],
                        ['id' => $baskomBiru->id, 'name' => 'BASKOM BIRU'],
                    ],
                    'qty' => 2,
                    'unit' => 'Lusin',
                ],
            ],
        ]);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['mode' => 'chat', 'keywords' => [], 'bulk_items' => []]),
                    ],
                ]],
            ]),
            CreateResponse::fake([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['reply' => 'Baik, dicatat.', 'show_products' => [], 'cart_add' => []]),
                    ],
                ]],
            ]),
        ]);

        // No comma-separated item list (not bulk-shaped), long (>20 chars),
        // and ends with a lone "2" that the old unbounded regex would have
        // read as "pilih opsi ke-2" (BASKOM BIRU) despite sharing no actual
        // keyword overlap with this message at all.
        $response = $this->postJson('/webhook/whatsapp', [
            'device' => '6281111111111',
            'sender' => '6281234511111',
            'message' => 'tolong konfirmasi semua pesanan saya ya 2',
        ]);

        $response->assertStatus(200);

        $session->refresh();

        // Still pending — not wrongly auto-resolved to BASKOM BIRU.
        $this->assertNotNull($session->pending_clarification);
        $this->assertEmpty($session->cart);
    }
}
