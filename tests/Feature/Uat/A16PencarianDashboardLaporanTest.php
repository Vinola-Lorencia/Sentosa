<?php

namespace Tests\Feature\Uat;

use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Supplier;
use Illuminate\Support\Carbon;

/**
 * A16 — Pencarian produk. A17 — Dashboard. A18 — Laporan per periode + WIB.
 */
class A16PencarianDashboardLaporanTest extends OwnerUatTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a16_admin_product_search_is_case_insensitive_and_filters_brand(): void
    {
        $this->product('TOPLES JUMBO 25KG', ['brand' => 'Sealware']);
        $this->product('Toples Mini', ['brand' => 'GM']);
        $this->product('EMBER 20L', ['brand' => 'GM']);

        $this->actingAs($this->pemilik)->get(route('products.index', ['search' => 'toples']))
            ->assertOk()->assertSee('TOPLES JUMBO 25KG')->assertSee('Toples Mini')->assertDontSee('EMBER 20L');

        $this->actingAs($this->karyawan)->get(route('products.index', ['search' => 'TOPLES', 'brand' => 'seal']))
            ->assertOk()->assertSee('TOPLES JUMBO 25KG')->assertDontSee('Toples Mini');
    }

    public function test_a16_storefront_search_hides_out_of_stock_products(): void
    {
        $this->product('TOPLES ADA', ['stock' => 5]);
        $this->product('TOPLES HABIS', ['stock' => 0]);
        $pembeli = $this->userWithRole('Pembeli', 'Sari', 'sari@uat.test');

        $this->actingAs($pembeli)->get(route('toko.index', ['search' => 'toples']))
            ->assertOk()->assertSee('TOPLES ADA')->assertDontSee('TOPLES HABIS');
    }

    public function test_a17_dashboard_numbers_match_manual_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 05:00:00', 'UTC')); // 12:00 WIB
        $product = $this->product('GAYUNG', ['stock' => 100, 'sale_price' => 10000]);
        $this->product('SENDOK', ['stock' => 9]);
        $this->product('PIRING', ['stock' => 0]);
        $this->product('GELAS', ['stock' => 10]); // tepat 10 → tidak menipis

        $this->sale($product, 3, 'Buah', ['sale_date' => '2026-10-01']);   // 30.000
        $this->sale($product, 5, 'Buah', ['sale_date' => '2026-10-31']);   // 50.000
        $this->sale($product, 7, 'Buah', ['sale_date' => '2026-09-30']);   // bulan lalu
        $unpaid = $this->sale($product, 2, 'Buah', ['sale_date' => '2026-10-10', 'payment_status' => 'Belum Bayar', 'is_new' => true]); // 20.000
        Receivable::create(['sale_id' => $unpaid->sale_id, 'due_date' => '2026-11-10', 'amount' => 20000, 'paid_amount' => 5000, 'remaining_amount' => 15000, 'status' => 'Unpaid']);
        $paid = $this->sale($product, 1, 'Buah', ['sale_date' => '2026-10-11']); // 10.000
        Receivable::create(['sale_id' => $paid->sale_id, 'due_date' => '2026-11-10', 'amount' => 10000, 'paid_amount' => 10000, 'remaining_amount' => 0, 'status' => 'Paid']);
        Purchase::create(['supplier_id' => Supplier::create(['supplier_name' => 'CV A'])->id, 'user_id' => $this->pemilik->id, 'purchase_date' => '2026-10-05', 'total_amount' => 70000]);

        $response = $this->actingAs($this->pemilik)->get(route('dashboard'))->assertOk();

        $this->assertEquals(110000, $response->viewData('totalPenjualanBulanIni')); // 30+50+20+10 rb
        $this->assertEquals(70000, $response->viewData('totalPembelianBulanIni'));
        $this->assertEquals(15000, $response->viewData('totalPiutang'));
        $this->assertSame(2, $response->viewData('stokMenipis'));
        $this->assertCount(1, $response->viewData('pesananBaru'));

        $grafik = collect($response->viewData('grafikPenjualan'))->pluck('total', 'bulan');
        $this->assertEquals(110000, $grafik['Oct 2026']);
        $this->assertEquals(70000, $grafik['Sep 2026']);
    }

    public function test_a18_sales_report_period_is_inclusive_and_totals_match(): void
    {
        $product = $this->product('GAYUNG', ['sale_price' => 10000]);
        $this->sale($product, 1, 'Buah', ['sale_date' => '2026-09-30']);
        $this->sale($product, 2, 'Buah', ['sale_date' => '2026-10-01']);
        $this->sale($product, 3, 'Buah', ['sale_date' => '2026-10-15']);
        $this->sale($product, 4, 'Buah', ['sale_date' => '2026-10-31']);
        $this->sale($product, 5, 'Buah', ['sale_date' => '2026-11-01']);

        $response = $this->actingAs($this->pemilik)
            ->get(route('laporan.penjualan', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertOk();

        $this->assertCount(3, $response->viewData('sales'));
        $this->assertEquals(90000, $response->viewData('total'));
        $this->assertEquals(90000, $response->viewData('walkInTotal'));
        // laba = (10.000 − 6.000 HPP) × 9 buah
        $this->assertEquals(36000, $response->viewData('labaTotal'));
    }

    public function test_a18_purchase_and_receivable_reports(): void
    {
        $supplier = Supplier::create(['supplier_name' => 'CV A']);
        Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $this->pemilik->id, 'purchase_date' => '2026-10-01', 'total_amount' => 40000]);
        Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $this->pemilik->id, 'purchase_date' => '2026-11-01', 'total_amount' => 99000]);

        $this->actingAs($this->pemilik)
            ->get(route('laporan.pembelian', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertOk()->assertViewHas('total', fn ($total) => (float) $total === 40000.0);

        $sale = $this->sale($this->product(), 1, 'Buah', ['payment_status' => 'Belum Bayar']);
        Receivable::create(['sale_id' => $sale->sale_id, 'due_date' => '2026-11-10', 'amount' => 10000, 'paid_amount' => 0, 'remaining_amount' => 10000, 'status' => 'Unpaid']);

        $this->actingAs($this->pemilik)->get(route('laporan.piutang', ['status' => 'Unpaid']))
            ->assertOk()->assertViewHas('piutang', fn ($rows) => $rows->count() === 1);
        $this->actingAs($this->pemilik)->get(route('laporan.piutang', ['status' => 'Paid']))
            ->assertOk()->assertViewHas('piutang', fn ($rows) => $rows->count() === 0);
    }

    public function test_a18_storefront_order_at_0630_wib_on_1_november_is_dated_1_november(): void
    {
        // 2026-10-31 23:30 UTC = 2026-11-01 06:30 WIB
        Carbon::setTestNow(Carbon::parse('2026-10-31 23:30:00', 'UTC'));

        $sale = $this->tokoOnlineCheckout($this->product(), 1);

        $this->assertSame('2026-11-01', (string) $sale->sale_date, 'Tanggal pesanan memakai tanggal UTC, bukan WIB');
    }

    public function test_a18_dashboard_this_month_follows_wib(): void
    {
        // 1 Nov 05:00 WIB (= 31 Okt 22:00 UTC): penjualan yang dicatat staf hari ini.
        Carbon::setTestNow(Carbon::parse('2026-10-31 22:00:00', 'UTC'));
        $product = $this->product();
        $this->sale($product, 3, 'Buah', ['sale_date' => '2026-11-01']); // 30.000
        $this->sale($product, 1, 'Buah', ['sale_date' => '2026-10-20']); // bulan lalu (WIB)

        $response = $this->actingAs($this->pemilik)->get(route('dashboard'));

        $this->assertEquals(30000, $response->viewData('totalPenjualanBulanIni'), '"Bulan ini" di dashboard masih Oktober (UTC)');
    }

    public function test_a18_default_report_period_follows_wib(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 22:00:00', 'UTC')); // 1 Nov 05:00 WIB

        $response = $this->actingAs($this->pemilik)->get(route('laporan.penjualan'));

        $this->assertSame('2026-11-01', $response->viewData('startDate'), 'Periode default laporan masih Oktober (UTC)');
        $this->assertSame('2026-11-30', $response->viewData('endDate'));
    }
}
