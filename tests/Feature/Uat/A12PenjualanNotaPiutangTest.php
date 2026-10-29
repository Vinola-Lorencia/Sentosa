<?php

namespace Tests\Feature\Uat;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Receivable;
use App\Models\Sale;

/**
 * A12 — Penjualan langsung. A13 — Invoice/nota (HTML, dot matrix, teks).
 * A14 — Pengiriman & surat jalan. A15 — Piutang & riwayat pembayaran.
 */
class A12PenjualanNotaPiutangTest extends OwnerUatTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'customer_name' => 'Toko Maju', 'phone' => '081377777777',
            'address' => 'Jl. Bengkalis II No. 91/93, Gang Buntu, Kel. Pulo Brayan, Medan Timur',
        ]);
    }

    /**
     * Two-line sale through the real form: 2 Lusin × 120.000 −10% and 5 Buah × 10.000.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function directSale(array $overrides = []): Sale
    {
        $ember = $this->product('EMBER 20L', ['sale_unit' => 'Lusin', 'sale_price' => 120000, 'stock' => 240]);
        $toples = $this->product('TOPLES KACA BULAT TUTUP SEAL 500ML', ['stock' => 50]);

        $this->actingAs($this->pemilik)->post(route('sales.store'), array_merge([
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-12',
            'payment_method' => 'Cash', 'payment_status' => 'Lunas', 'notes' => 'Antar sore',
            'product_id' => [$ember->id, $toples->id], 'quantity' => [2, 5], 'unit' => ['Lusin', 'Buah'],
            'price' => [120000, 10000], 'discount' => [10, 0],
        ], $overrides))->assertRedirect(route('sales.index'));

        return Sale::with('details')->latest('sale_id')->firstOrFail();
    }

    public function test_a12_direct_sale_total_details_and_stock(): void
    {
        $sale = $this->directSale();

        // 2 × 120.000 × 90% + 5 × 10.000 = 216.000 + 50.000
        $this->assertEquals(266000, $sale->total_amount);
        $this->assertCount(2, $sale->details);
        $this->assertSame('Lunas', $sale->payment_status);
        $this->assertSame('2026-10-12', (string) $sale->paid_date);
        $this->assertFalse((bool) $sale->is_new);
        $this->assertSame(0, Receivable::count());
        $this->assertSame($this->pemilik->id, $sale->user_id);
    }

    public function test_a12_belum_bayar_sale_with_due_date_creates_receivable(): void
    {
        $sale = $this->directSale(['payment_status' => 'Belum Bayar', 'payment_method' => 'Utang', 'due_date' => '2026-11-12']);

        $receivable = Receivable::firstOrFail();
        $this->assertSame($sale->sale_id, $receivable->sale_id);
        $this->assertEquals(266000, $receivable->amount);
        $this->assertEquals(266000, $receivable->remaining_amount);
        $this->assertSame('Unpaid', $receivable->status);
    }

    public function test_a12_belum_bayar_sale_without_due_date_is_saved(): void
    {
        // Form: "Jatuh Tempo" tidak wajib (validasi: nullable), receivables.due_date NOT NULL.
        $ember = $this->product('EMBER 20L', ['stock' => 240]);

        $this->actingAs($this->pemilik)->post(route('sales.store'), [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-12',
            'payment_method' => 'Utang', 'payment_status' => 'Belum Bayar',
            'product_id' => [$ember->id], 'quantity' => [1], 'unit' => ['Buah'], 'price' => [10000], 'discount' => [0],
        ]);

        $this->assertSame(1, Sale::count(), 'Penjualan Belum Bayar tanpa jatuh tempo gagal disimpan: '.session('error'));
        $this->assertSame(1, Receivable::count());
    }

    public function test_a13_invoice_page_shows_number_items_and_total(): void
    {
        $sale = $this->directSale();
        $invoice = 'INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT);

        $this->actingAs($this->pemilik)->get(route('sales.show', $sale->sale_id))
            ->assertOk()
            ->assertSee($invoice)->assertSee('Toko Maju')
            ->assertSee('EMBER 20L')->assertSee('TOPLES KACA BULAT TUTUP SEAL 500ML')
            ->assertSee('Rp 266.000');

        $this->actingAs($this->karyawan)->get(route('sales.print-dotmatrix', $sale->sale_id))
            ->assertOk()
            ->assertSee("Nota {$invoice}")->assertSee('EMBER 20L')->assertSee('Rp 266.000');
    }

    public function test_a13_text_nota_has_number_items_total_and_fits_70_columns(): void
    {
        $sale = $this->directSale();

        $response = $this->actingAs($this->pemilik)->get(route('sales.print-text', $sale->sale_id))->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $text = $response->getContent();
        $this->assertStringContainsString('No. Nota  : INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT), $text);
        $this->assertStringContainsString('Tanggal   : 12/10/2026', $text);
        $this->assertStringContainsString('EMBER 20L', $text);
        $this->assertStringContainsString('TOPLES KACA BULAT TUTUP S', $text); // dipotong 25 karakter
        $this->assertMatchesRegularExpression('/TOTAL KESELURUHAN\s+Rp 266\.000/', $text);

        $tooLong = array_filter(explode("\n", $text), fn (string $line) => mb_strlen($line) > 70);
        $this->assertSame([], array_values($tooLong), 'Baris nota teks melebihi 70 kolom');
    }

    public function test_a13_whatsapp_invoice_number_matches_number_shown_in_admin(): void
    {
        ['sale' => $sale, 'reply' => $reply] = $this->whatsAppOrder($this->product(), 1);

        $adminNumber = 'INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT);
        $this->actingAs($this->pemilik)->get(route('sales.show', $sale->sale_id))->assertSee($adminNumber);

        $this->assertStringContainsString("No. Invoice: *{$adminNumber}*", $reply,
            'Nomor invoice di WhatsApp berbeda dengan di admin/nota');
    }

    public function test_a14_delivery_from_sale_and_surat_jalan(): void
    {
        $sale = $this->directSale();

        $this->actingAs($this->pemilik)->post(route('pengiriman.store'), [
            'sale_id' => $sale->sale_id, 'delivery_date' => '2026-10-13', 'type' => 'Dikirim', 'status' => 'Pending',
            'recipient_name' => 'Pak Maju', 'recipient_phone' => '081377777777', 'address' => 'Jl. Bengkalis II',
            'expedition' => 'Kurir Toko', 'shipping_cost' => 15000, 'colly' => 3,
        ]);
        $delivery = Delivery::firstOrFail();

        $this->actingAs($this->pemilik)->get(route('pengiriman.show', $delivery->delivery_id))
            ->assertOk()
            ->assertSee('SURAT JALAN')
            ->assertSee('Ref: INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT))
            ->assertSee('Pak Maju')->assertSee('3 colly')
            ->assertSee('EMBER 20L')->assertSee('TOPLES KACA BULAT TUTUP SEAL 500ML');

        $this->actingAs($this->pemilik)->put(route('pengiriman.update', $delivery->delivery_id), [
            'sale_id' => $sale->sale_id, 'delivery_date' => '2026-10-13', 'type' => 'Dikirim', 'status' => 'Dikirim',
            'recipient_name' => 'Pak Maju', 'colly' => 3,
        ]);
        $this->assertSame('Dikirim', $delivery->fresh()->status);
    }

    public function test_a15_receivable_partial_then_full_payment_with_history(): void
    {
        $sale = $this->directSale(['payment_status' => 'Belum Bayar', 'payment_method' => 'Utang', 'due_date' => '2026-11-12']);
        $receivable = Receivable::firstOrFail();

        $this->actingAs($this->pemilik)->post(route('piutang.bayar', $receivable->receivable_id), ['amount' => 100000, 'payment_date' => '2026-10-20', 'notes' => 'DP']);
        $receivable->refresh();
        $this->assertEquals(100000, $receivable->paid_amount);
        $this->assertEquals(166000, $receivable->remaining_amount);
        $this->assertSame('Unpaid', $receivable->status);

        // Lebih bayar ditolak
        $this->actingAs($this->pemilik)
            ->post(route('piutang.bayar', $receivable->receivable_id), ['amount' => 200000, 'payment_date' => '2026-10-21'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($this->pemilik)->post(route('piutang.bayar', $receivable->receivable_id), ['amount' => 166000, 'payment_date' => '2026-10-25']);
        $receivable->refresh();
        $this->assertEquals(0, $receivable->remaining_amount);
        $this->assertSame('Paid', $receivable->status);
        $this->assertSame('Lunas', $sale->fresh()->payment_status);
        $this->assertSame('2026-10-25', (string) $sale->fresh()->paid_date);

        $this->actingAs($this->pemilik)->get(route('piutang.show', $receivable->receivable_id))
            ->assertOk()->assertSee('DP')->assertSee('100.000')->assertSee('166.000');
    }
}
