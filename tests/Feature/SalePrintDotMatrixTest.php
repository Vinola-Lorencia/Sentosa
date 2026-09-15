<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePrintDotMatrixTest extends TestCase
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
            'name'     => 'Owner',
            'email'    => 'owner@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $pemilikRole->id,
        ]);

        $customer = Customer::create([
            'customer_name' => 'Pelanggan Dotmatrix',
            'phone'         => '08123456789',
            'address'       => 'Jl. Contoh No. 1',
        ]);

        $this->product = Product::create([
            'product_name'         => 'Kursi Dotmatrix',
            'sale_price'           => 50000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 10,
            'default_buy_price'    => 30000,
            'default_buy_discount' => 0,
        ]);

        $this->sale = Sale::create([
            'customer_id'    => $customer->id,
            'user_id'        => $this->pemilik->id,
            'sale_date'      => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount'   => 95000,
        ]);

        SaleDetail::create([
            'sale_id'    => $this->sale->sale_id,
            'product_id' => $this->product->id,
            'quantity'   => 2,
            'unit'       => 'Buah',
            'price'      => 50000,
            'discount'   => 5,
            'subtotal'   => 95000,
        ]);
    }

    public function test_pemilik_can_view_print_dotmatrix_page()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-dotmatrix', $this->sale->sale_id));

        $response->assertStatus(200);
    }

    public function test_print_dotmatrix_shows_correct_sale_data()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-dotmatrix', $this->sale->sale_id));

        $response->assertStatus(200);
        $response->assertSee('INV-' . str_pad($this->sale->sale_id, 3, '0', STR_PAD_LEFT));
        $response->assertSee('Pelanggan Dotmatrix');
        $response->assertSee('Kursi Dotmatrix');
        $response->assertSee('95.000'); // total_amount and subtotal both format to this
        $response->assertSee('50.000'); // unit price
        $response->assertSee('5%');     // discount
        $response->assertSee('Cash');
        $response->assertSee('Lunas');
    }

    public function test_print_dotmatrix_shows_receivable_info_when_unpaid()
    {
        $this->sale->update(['payment_status' => 'Belum Bayar']);

        \App\Models\Receivable::create([
            'sale_id'          => $this->sale->sale_id,
            'customer_id'      => $this->sale->customer_id,
            'due_date'         => now()->addDays(7)->toDateString(),
            'amount'           => 95000,
            'paid_amount'      => 20000,
            'remaining_amount' => 75000,
            'status'           => 'Unpaid',
        ]);

        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-dotmatrix', $this->sale->sale_id));

        $response->assertStatus(200);
        $response->assertSee('Belum Bayar');
        $response->assertSee('75.000'); // sisa tagihan
        $response->assertSee('20.000'); // sudah dibayar
    }

    public function test_karyawan_can_view_print_dotmatrix_page()
    {
        $karyawanRole = Role::create(['role_name' => 'Karyawan']);
        $karyawan = User::create([
            'name' => 'Budi', 'email' => 'budi@test.com',
            'password' => bcrypt('password'), 'role_id' => $karyawanRole->id,
        ]);

        $response = $this->actingAs($karyawan)
            ->get(route('sales.print-dotmatrix', $this->sale->sale_id));

        $response->assertStatus(200);
    }

    public function test_print_dotmatrix_returns_404_for_missing_sale()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.print-dotmatrix', 999999));

        $response->assertStatus(404);
    }

    public function test_show_page_has_print_dotmatrix_button()
    {
        $response = $this->actingAs($this->pemilik)
            ->get(route('sales.show', $this->sale->sale_id));

        $response->assertStatus(200);
        $response->assertSee('Print Dot Matrix');
        $response->assertSee(route('sales.print-dotmatrix', $this->sale->sale_id), false);
    }
}
