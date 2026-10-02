<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePrintTextTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private Sale $sale;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $pemilikRole = Role::create(['role_name' => 'Pemilik']);
        $this->pemilik = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role_id' => $pemilikRole->id,
        ]);

        $customer = Customer::create([
            'customer_name' => 'Pelanggan Teks',
            'phone' => '08123456789',
            'address' => 'Jl. Contoh No. 1',
        ]);

        $this->product = Product::create([
            'product_name' => 'Kursi Teks',
            'sale_price' => 50000,
            'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 10,
            'default_buy_price' => 30000,
            'default_buy_discount' => 0,
        ]);

        $this->sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $this->pemilik->id,
            'sale_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount' => 95000,
        ]);

        SaleDetail::create([
            'sale_id' => $this->sale->sale_id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit' => 'Buah',
            'price' => 50000,
            'discount' => 5,
            'subtotal' => 95000,
        ]);
    }

    public function test_pemilik_can_view_print_text_page()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $response->assertStatus(200);
    }

    public function test_print_text_returns_plain_text_content_type()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_print_text_is_not_html()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $content = $response->getContent();
        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('<table', $content);
        $this->assertStringNotContainsString('<!DOCTYPE', $content);
    }

    public function test_print_text_shows_correct_sale_data()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $response->assertStatus(200);
        $response->assertSee('INV-'.str_pad($this->sale->sale_id, 3, '0', STR_PAD_LEFT), false);
        $response->assertSee('Pelanggan Teks', false);
        $response->assertSee('Kursi Teks', false);
        $response->assertSee('95.000', false); // total_amount and subtotal both format to this
        $response->assertSee('50.000', false); // unit price
        $response->assertSee('5%', false);      // discount
        $response->assertSee('Cash', false);
        $response->assertSee('Lunas', false);
    }

    public function test_print_text_lines_do_not_exceed_70_columns()
    {
        // 70, not the printer's nominal 80 — leaves a safety margin for LX
        // dot matrix units whose physical print margin isn't precisely 80
        // columns, so content doesn't bleed past the paper edge/perforation.
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $lines = explode("\n", $response->getContent());

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(70, strlen($line), "Line exceeds 70 columns: '{$line}'");
        }
    }

    public function test_print_text_shows_receivable_info_when_unpaid()
    {
        $this->sale->update(['payment_status' => 'Belum Bayar']);

        Receivable::create([
            'sale_id' => $this->sale->sale_id,
            'customer_id' => $this->sale->customer_id,
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 95000,
            'paid_amount' => 20000,
            'remaining_amount' => 75000,
            'status' => 'Unpaid',
        ]);

        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $response->assertStatus(200);
        $response->assertSee('Belum Bayar', false);
        $response->assertSee('75.000', false); // sisa tagihan
        $response->assertSee('20.000', false); // sudah dibayar
        $response->assertSee('Jatuh Tempo', false);
    }

    public function test_karyawan_can_view_print_text_page()
    {
        $karyawanRole = Role::create(['role_name' => 'Karyawan']);
        $karyawan = User::create([
            'name' => 'Budi', 'email' => 'budi@test.com',
            'password' => bcrypt('password'), 'role_id' => $karyawanRole->id,
        ]);

        $response = $this->actingAs($karyawan)
            ->get(route('sales.print-text', $this->sale->sale_id));

        $response->assertStatus(200);
    }

    public function test_print_text_returns_404_for_missing_sale()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', 999999));

        $response->assertStatus(404);
    }

    public function test_print_text_fits_20_items_on_one_page_with_aligned_columns()
    {
        $sale = Sale::create([
            'customer_id' => $this->sale->customer_id,
            'user_id' => $this->pemilik->id,
            'sale_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount' => 20 * 50000,
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $product = Product::create([
                'product_name' => "Produk Ke-{$i}",
                'sale_price' => 50000,
                'sale_unit' => 'Buah',
                'purchase_unit' => 'Dus',
                'dus_to_unit' => 1,
                'stock' => 10,
                'default_buy_price' => 30000,
                'default_buy_discount' => 0,
            ]);

            SaleDetail::create([
                'sale_id' => $sale->sale_id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit' => 'Buah',
                'price' => 50000,
                'discount' => 0,
                'subtotal' => 50000,
            ]);
        }

        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-text', $sale->sale_id));

        $response->assertStatus(200);

        $content = $response->getContent();
        $lines = explode("\n", rtrim($content, "\n"));

        // Still fits the 70-column dot matrix width even after compaction.
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(70, strlen($line), "Line exceeds 70 columns: '{$line}'");
        }

        // Fixed header/info/table/footer overhead (no notes, no receivable,
        // customer has an address) + 20 item rows. A regression here means
        // someone re-added blank lines the compaction removed.
        $this->assertCount(38, $lines, 'Unexpected total line count for a 20-item nota: '.count($lines));

        // Comfortably under a standard 11" continuous-form page (~66 lines
        // at 6 lines/inch) — the whole point of the compaction.
        $this->assertLessThanOrEqual(45, count($lines));

        $this->assertStringContainsString('Produk Ke-1 ', $content);
        $this->assertStringContainsString('Produk Ke-20', $content);

        // Column alignment: the fixed-width item format means the header
        // row and every data row must be exactly the same length.
        $headerLine = collect($lines)->first(
            fn ($l) => str_contains($l, 'Nama Produk') && str_contains($l, 'Subtotal')
        );
        $itemLines = collect($lines)->filter(
            fn ($l) => (bool) preg_match('/^\d{1,2}\s+Produk Ke-\d{1,2}\s/', $l)
        );

        $this->assertNotNull($headerLine);
        $this->assertCount(20, $itemLines);

        foreach ($itemLines as $itemLine) {
            $this->assertSame(strlen($headerLine), strlen($itemLine), "Item row misaligned with header: '{$itemLine}'");
        }
    }

    public function test_show_page_has_print_text_button()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.show', $this->sale->sale_id));

        $response->assertStatus(200);
        $response->assertSee('Print Teks (LX)');
        $response->assertSee(route('sales.print-text', $this->sale->sale_id), false);
    }
}
