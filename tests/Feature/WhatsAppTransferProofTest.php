<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * A photo sent while the customer has an unpaid bank-transfer order is
 * treated as a transfer receipt: acknowledged, noted on the sale (status
 * untouched), optionally forwarded to the owner — never sent to GPT and
 * never downloaded. Any other photo/sticker keeps the old generic reply.
 */
class WhatsAppTransferProofTest extends TestCase
{
    use RefreshDatabase;

    private const SENDER = '6281234567890';

    private const PHONE = 'whatsapp:+6281234567890';

    private const PHOTO_URL = 'https://files.fonnte.test/media/bukti1.jpg';

    private const PROOF_REPLY = 'Bukti transfer diterima. Admin akan memeriksa dan mengonfirmasi pesanan Anda. Terima kasih 🙏';

    private const UNSUPPORTED_REPLY = 'Maaf, saya hanya bisa membaca pesan teks atau voice note. Silakan ketik produk yang ingin dipesan ya 🙏';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fonnte.token' => 'test-fonnte-token',
            'services.whatsapp.webhook_secret' => 'test-webhook-secret',
            'services.shop.owner_whatsapp' => '',
        ]);

        putenv('WHATSAPP_PROVIDER=fonnte');
        $_ENV['WHATSAPP_PROVIDER'] = 'fonnte';
        $_SERVER['WHATSAPP_PROVIDER'] = 'fonnte';

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200),
            'files.fonnte.test/*' => Http::response('jpeg-bytes', 200),
        ]);

        // GPT must never be called for a photo.
        OpenAI::fake([]);

        // 07:05 UTC = 14:05 WIB.
        Carbon::setTestNow(Carbon::parse('2026-10-28 07:05:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        putenv('WHATSAPP_PROVIDER');
        unset($_ENV['WHATSAPP_PROVIDER'], $_SERVER['WHATSAPP_PROVIDER']);

        parent::tearDown();
    }

    private function sale(string $paymentMethod, string $paymentStatus = 'Belum Bayar', string $phone = self::PHONE, ?string $notes = 'Pesanan via WhatsApp (whatsapp:+6281234567890)'): Sale
    {
        $customer = Customer::create(['customer_name' => 'Budi', 'phone' => $phone, 'address' => 'Jl. Bengkalis II No. 91']);

        return Sale::create([
            'customer_id' => $customer->id,
            'user_id' => User::factory()->create()->id,
            'sale_date' => '2026-10-28',
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'total_amount' => 30000,
            'is_new' => true,
            'notes' => $notes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function sendPhoto(string $url = self::PHOTO_URL, array $extra = ['extension' => 'jpg']): void
    {
        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', array_merge([
            'device' => '6281111111111',
            'sender' => self::SENDER,
            'message' => 'non-text message',
            'text' => 'non-button message',
            'url' => $url,
        ], $extra))->assertStatus(200);
    }

    /**
     * @return list<array{target: string, message: string}>
     */
    private function sentTo(string $target): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send' && $r['target'] === $target)
            ->map(fn (HttpRequest $r) => ['target' => (string) $r['target'], 'message' => (string) $r['message']])
            ->values()
            ->all();
    }

    public function test_photo_with_pending_transfer_order_is_acknowledged_and_noted_without_changing_status(): void
    {
        $sale = $this->sale('Transfer Bank');

        $this->sendPhoto();

        $this->assertSame([['target' => self::SENDER, 'message' => self::PROOF_REPLY]], $this->sentTo(self::SENDER));

        $sale->refresh();
        $this->assertSame(
            "Pesanan via WhatsApp (whatsapp:+6281234567890)\nBukti transfer dikirim 28/10/2026 14:05 WIB",
            $sale->notes
        );
        $this->assertSame('Belum Bayar', $sale->payment_status);

        // Not downloaded, no GPT, no chat session touched.
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'files.fonnte.test'));
        OpenAI::assertNothingSent();
        $this->assertSame(0, WhatsAppSession::count());
    }

    public function test_toko_transfer_order_with_locally_formatted_phone_is_matched(): void
    {
        // Toko/admin sales use "Transfer"; staff-typed phones look like "08…".
        $sale = $this->sale('Transfer', phone: '081234567890', notes: null);

        $this->sendPhoto();

        $this->assertSame('Bukti transfer dikirim 28/10/2026 14:05 WIB', $sale->fresh()->notes);
        $this->assertSame(self::PROOF_REPLY, $this->sentTo(self::SENDER)[0]['message']);
    }

    public function test_photo_without_pending_order_gets_old_reply(): void
    {
        $this->sale('Transfer Bank', 'Lunas');

        $this->sendPhoto();

        $this->assertSame(self::UNSUPPORTED_REPLY, $this->sentTo(self::SENDER)[0]['message']);
        $this->assertStringNotContainsString('Bukti transfer', (string) Sale::first()->notes);
        OpenAI::assertNothingSent();
    }

    public function test_photo_with_bayar_di_toko_order_gets_old_reply(): void
    {
        $sale = $this->sale('Bayar di Toko');

        $this->sendPhoto();

        $this->assertSame(self::UNSUPPORTED_REPLY, $this->sentTo(self::SENDER)[0]['message']);
        $this->assertStringNotContainsString('Bukti transfer', (string) $sale->fresh()->notes);
    }

    public function test_sticker_keeps_old_reply_even_with_pending_transfer_order(): void
    {
        $sale = $this->sale('Transfer Bank');

        $this->sendPhoto('https://files.fonnte.test/media/sticker.webp', ['extension' => 'webp']);

        $this->assertSame(self::UNSUPPORTED_REPLY, $this->sentTo(self::SENDER)[0]['message']);
        $this->assertStringNotContainsString('Bukti transfer', (string) $sale->fresh()->notes);
    }

    public function test_fonnte_type_image_without_extension_is_recognized(): void
    {
        $sale = $this->sale('Transfer Bank');

        $this->sendPhoto('https://files.fonnte.test/media/abc123', ['type' => 'image']);

        $this->assertStringContainsString('Bukti transfer dikirim', (string) $sale->fresh()->notes);
    }

    public function test_same_image_sent_twice_is_noted_only_once(): void
    {
        $sale = $this->sale('Transfer Bank');

        $this->sendPhoto();
        $this->sendPhoto();

        $this->assertSame(1, substr_count((string) $sale->fresh()->notes, 'Bukti transfer dikirim'));
        $this->assertCount(1, $this->sentTo(self::SENDER));
    }

    public function test_owner_is_notified_once_when_owner_number_is_configured(): void
    {
        config(['services.shop.owner_whatsapp' => '6289900000000']);
        $sale = $this->sale('Transfer Bank');

        $this->sendPhoto();

        $invoice = 'INV-'.str_pad((string) $sale->sale_id, 6, '0', STR_PAD_LEFT);
        $this->assertSame(
            [['target' => '6289900000000', 'message' => "Bukti transfer masuk dari 6281234567890 untuk invoice {$invoice}."]],
            $this->sentTo('6289900000000')
        );
    }

    public function test_owner_is_not_notified_when_owner_number_is_empty(): void
    {
        $this->sale('Transfer Bank');

        $this->sendPhoto();

        Http::assertSentCount(1); // only the reply to the customer
    }
}
