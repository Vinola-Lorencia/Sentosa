<?php

namespace Tests\Feature\Uat;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * Shared setup for the Pemilik UAT (kuesioner staf A1–A20): small data,
 * SQLite memory DB, every outside service faked.
 */
abstract class OwnerUatTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $pemilik;

    protected User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://supabase.test',
            'services.supabase.secret_key' => 'test-secret',
            'services.supabase.storage_bucket' => 'produk',
            'services.fonnte.token' => 'test-fonnte-token',
            'services.whatsapp.webhook_secret' => 'test-webhook-secret',
            'services.shop.owner_whatsapp' => '',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'supabase.test/*' => Http::response(['Key' => 'ok'], 200),
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);
        OpenAI::fake([]);

        // Pemilik first so it gets id 1 — WhatsAppController::createOrder()
        // hardcodes user_id 1.
        $this->pemilik = $this->userWithRole('Pemilik', 'Pemilik Toko', 'pemilik@uat.test');
        $this->karyawan = $this->userWithRole('Karyawan', 'Budi Karyawan', 'karyawan@uat.test');
    }

    protected function userWithRole(string $roleName, string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role_id' => Role::firstOrCreate(['role_name' => $roleName])->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function product(string $name = 'TOPLES 1L', array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => $name,
            'brand' => 'GM',
            'default_buy_price' => 6000,
            'default_buy_discount' => 0,
            'sale_price' => 10000,
            'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 100,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function sale(Product $product, float $qty = 1, string $unit = 'Buah', array $overrides = []): Sale
    {
        $subtotal = $qty * (float) $product->sale_price;

        $sale = Sale::create(array_merge([
            'customer_id' => null,
            'user_id' => $this->pemilik->id,
            'sale_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount' => $subtotal,
            'is_new' => false,
        ], $overrides));

        SaleDetail::create([
            'sale_id' => $sale->sale_id, 'product_id' => $product->id, 'quantity' => $qty,
            'unit' => $unit, 'price' => $product->sale_price, 'discount' => 0, 'subtotal' => $subtotal,
        ]);

        return $sale;
    }

    /**
     * Checks out one cart line through the real storefront flow.
     */
    protected function tokoOnlineCheckout(Product $product, float $qty, string $unit = 'Buah', string $paymentMethod = 'Transfer'): Sale
    {
        $pembeli = $this->userWithRole('Pembeli', 'Sari Pembeli', 'sari@uat.test');
        Customer::create(['customer_name' => 'Sari Pembeli', 'phone' => '081200000001', 'address' => 'Jl. Pembeli 1', 'user_id' => $pembeli->id]);

        $this->actingAs($pembeli)
            ->post(route('toko.keranjang.tambah'), ['product_id' => $product->id, 'quantity' => $qty, 'unit' => $unit])
            ->assertSessionHas('success');

        $this->actingAs($pembeli)
            ->post(route('toko.checkout.proses'), ['nama' => 'Sari Pembeli', 'alamat' => 'Jl. Pembeli 1', 'payment_method' => $paymentMethod])
            ->assertRedirect(route('toko.pesanan'));

        $this->assertSame(0, CartItem::count());

        return Sale::latest('sale_id')->firstOrFail();
    }

    /**
     * Places an order through the real WhatsApp chatbot final step
     * (need_payment → "1"/"2"), with Fonnte and OpenAI faked.
     *
     * @return array{sale: Sale, reply: string}
     */
    protected function whatsAppOrder(Product $product, float $qty, string $paymentChoice = '2'): array
    {
        WhatsAppSession::create([
            'phone_number' => 'whatsapp:+6281234567890',
            'step' => 'need_payment',
            'customer_name' => 'Andi WA',
            'address' => 'Jl. WhatsApp 2',
            'messages' => [],
            'cart' => [[
                'product_id' => $product->id, 'product_name' => $product->product_name, 'qty' => $qty,
                'unit' => $product->sale_unit, 'price' => (float) $product->sale_price,
                'subtotal' => $qty * (float) $product->sale_price,
            ]],
        ]);

        $this->postJson('/webhook/whatsapp?key=test-webhook-secret', [
            'device' => '6281111111111', 'sender' => '6281234567890',
            'message' => $paymentChoice, 'text' => 'non-button message',
        ])->assertOk();

        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn ($r) => $r->url() === 'https://api.fonnte.com/send');

        return ['sale' => Sale::latest('sale_id')->firstOrFail(), 'reply' => (string) $sent->last()['message']];
    }
}
