<?php

namespace Tests\Feature\Uat;

use App\Models\Receivable;
use App\Models\Sale;

/**
 * A9–A11 — Pesanan toko online dan WhatsApp di dashboard, tombol Proses,
 * dan dugaan stok terpotong dobel. A15 (sebagian) — apakah pesanan
 * online/WhatsApp Belum Bayar membuat piutang.
 */
class A09PesananBaruTest extends OwnerUatTestCase
{
    public function test_a9_toko_online_order_appears_complete_on_dashboard(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100, 'sale_price' => 10000]);

        $sale = $this->tokoOnlineCheckout($product, 10);

        $this->assertTrue((bool) $sale->is_new);
        $this->assertSame('Belum Bayar', $sale->payment_status);
        $this->assertEquals(100000, $sale->total_amount);

        $invoice = 'INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT);
        $this->actingAs($this->pemilik)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 Pesanan Baru!')
            ->assertSee($invoice)
            ->assertSee('Rp 100.000')
            ->assertSee('Pembeli: Sari Pembeli | Alamat: Jl. Pembeli 1')
            ->assertSee(route('sales.tandai-diproses', $sale->sale_id));
    }

    public function test_a9_whatsapp_order_appears_complete_on_dashboard(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100, 'sale_price' => 10000]);

        $sale = $this->whatsAppOrder($product, 10)['sale'];

        $this->assertTrue((bool) $sale->is_new);
        $this->assertEquals(100000, $sale->total_amount);

        $this->actingAs($this->pemilik)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 Pesanan Baru!')
            ->assertSee('INV-'.str_pad((string) $sale->sale_id, 3, '0', STR_PAD_LEFT))
            ->assertSee('Andi WA')
            ->assertSee('Pesanan via WhatsApp');
    }

    /**
     * WAJIB: stok 100, pesan 10 Buah di toko online, lalu Pemilik klik
     * Proses. 90 = benar, 80 = terpotong dobel.
     */
    public function test_a10_toko_online_order_stock_is_deducted_only_once(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100]);

        $sale = $this->tokoOnlineCheckout($product, 10);
        $afterCheckout = (float) $product->fresh()->stock;

        $this->actingAs($this->pemilik)
            ->patch(route('sales.tandai-diproses', $sale->sale_id))
            ->assertRedirect(route('dashboard'));
        $afterProses = (float) $product->fresh()->stock;

        $this->assertFalse((bool) $sale->fresh()->is_new);
        $this->assertSame(90.0, $afterCheckout, 'Stok setelah checkout');
        $this->assertSame(90.0, $afterProses, "Stok setelah checkout = {$afterCheckout}, setelah Proses = {$afterProses} (terpotong dobel)");
    }

    public function test_a10_whatsapp_order_stock_is_deducted_once_on_proses(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100]);

        $sale = $this->whatsAppOrder($product, 10)['sale'];
        $this->assertEquals(100, $product->fresh()->stock, 'Pesanan WhatsApp belum memotong stok');

        $this->actingAs($this->pemilik)->patch(route('sales.tandai-diproses', $sale->sale_id));

        $this->assertEquals(90, $product->fresh()->stock);
        $this->assertFalse((bool) $sale->fresh()->is_new);
    }

    public function test_a11_proses_clicked_twice_does_not_deduct_again(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100]);
        $sale = $this->whatsAppOrder($product, 10)['sale'];

        $this->actingAs($this->pemilik)->patch(route('sales.tandai-diproses', $sale->sale_id));
        $this->actingAs($this->pemilik)->patch(route('sales.tandai-diproses', $sale->sale_id));

        $this->assertEquals(90, $product->fresh()->stock, 'Klik Proses kedua kali (mis. dobel klik / tab lama) memotong stok lagi');
    }

    public function test_a11_proses_with_insufficient_stock_is_refused_and_order_stays_new(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100]);
        $sale = $this->whatsAppOrder($product, 10)['sale'];
        $product->update(['stock' => 5]);

        $this->actingAs($this->pemilik)
            ->patch(route('sales.tandai-diproses', $sale->sale_id))
            ->assertSessionHas('error');

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertTrue((bool) $sale->fresh()->is_new);
    }

    public function test_a11_processed_order_leaves_pesanan_baru_list(): void
    {
        $sale = $this->whatsAppOrder($this->product(), 1)['sale'];

        $this->actingAs($this->pemilik)->patch(route('sales.tandai-diproses', $sale->sale_id));

        $this->actingAs($this->pemilik)->get(route('dashboard'))->assertDontSee('Pesanan Baru!');
    }

    /**
     * A15 — perilaku nyata dicatat (bukan klaim bug): pesanan online dan
     * WhatsApp berstatus "Belum Bayar" tetapi tidak membuat baris piutang,
     * sedangkan penjualan langsung "Belum Bayar" membuatnya.
     */
    public function test_a15_online_and_whatsapp_belum_bayar_orders_do_not_create_receivable(): void
    {
        $product = $this->product('TOPLES 1L', ['stock' => 100]);

        $online = $this->tokoOnlineCheckout($product, 2, 'Buah', 'Utang');
        $wa = $this->whatsAppOrder($product, 2)['sale'];

        $this->assertSame('Belum Bayar', $online->payment_status);
        $this->assertSame('Belum Bayar', $wa->payment_status);
        $this->assertSame(0, Receivable::count());

        $this->assertEquals(0, $this->actingAs($this->pemilik)->get(route('dashboard'))->viewData('totalPiutang'));

        // Diproses pun tetap tidak membuat piutang.
        $this->actingAs($this->pemilik)->patch(route('sales.tandai-diproses', $online->sale_id));
        $this->assertSame(0, Receivable::count());
        $this->assertSame('Belum Bayar', Sale::find($online->sale_id)->payment_status);
    }
}
