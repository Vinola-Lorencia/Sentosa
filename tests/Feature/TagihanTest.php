<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagihanTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pembeli']);
        $this->pembeli = User::create([
            'name'     => 'Gultom',
            'email'    => 'gultom@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
    }

    public function test_pembeli_without_linked_customer_sees_helpful_message()
    {
        $response = $this->actingAs($this->pembeli)->get(route('toko.tagihan'));

        $response->assertStatus(200);
        $response->assertSee('belum terhubung dengan data pelanggan');
    }

    public function test_pembeli_sees_own_invoices_and_receivable_status()
    {
        $customer = Customer::create([
            'customer_name' => 'Gultom',
            'phone'         => '08123456789',
            'user_id'       => $this->pembeli->id,
        ]);

        $lunas = Sale::create([
            'customer_id'    => $customer->id,
            'user_id'        => $this->pembeli->id,
            'sale_date'      => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount'   => 100000,
        ]);

        $belumLunas = Sale::create([
            'customer_id'    => $customer->id,
            'user_id'        => $this->pembeli->id,
            'sale_date'      => now()->toDateString(),
            'payment_method' => 'Utang',
            'payment_status' => 'Belum Bayar',
            'total_amount'   => 50000,
        ]);

        Receivable::create([
            'sale_id'          => $belumLunas->sale_id,
            'customer_id'      => $customer->id,
            'due_date'         => now()->addDays(7)->toDateString(),
            'amount'           => 50000,
            'paid_amount'      => 20000,
            'remaining_amount' => 30000,
            'status'           => 'Unpaid',
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.tagihan'));

        $response->assertStatus(200);
        $response->assertSee('INV-' . str_pad($lunas->sale_id, 3, '0', STR_PAD_LEFT));
        $response->assertSee('INV-' . str_pad($belumLunas->sale_id, 3, '0', STR_PAD_LEFT));
        $response->assertSee('Lunas');
        $response->assertSee('Belum Lunas');
        $response->assertSee('30.000'); // sisa tagihan
        $response->assertDontSee('Jatuh tempo');
    }

    public function test_riwayat_pembayaran_shows_every_installment()
    {
        $customer = Customer::create([
            'customer_name' => 'Gultom', 'user_id' => $this->pembeli->id,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id, 'user_id' => $this->pembeli->id,
            'sale_date' => now()->toDateString(), 'payment_method' => 'Utang',
            'payment_status' => 'Belum Bayar', 'total_amount' => 100000,
        ]);

        $receivable = Receivable::create([
            'sale_id' => $sale->sale_id, 'customer_id' => $customer->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 100000, 'paid_amount' => 70000, 'remaining_amount' => 30000,
            'status' => 'Unpaid',
        ]);

        ReceivablePayment::create([
            'receivable_id' => $receivable->receivable_id,
            'amount'        => 50000,
            'payment_date'  => now()->subDays(5)->toDateString(),
        ]);
        ReceivablePayment::create([
            'receivable_id' => $receivable->receivable_id,
            'amount'        => 20000,
            'payment_date'  => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.tagihan'));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Pembayaran');
        $response->assertSee(now()->subDays(5)->format('d F Y'));
        $response->assertSee(now()->subDays(2)->format('d F Y'));
        $response->assertSeeInOrder(['50.000', '20.000']);
    }

    public function test_riwayat_pembayaran_hidden_when_no_payments_made_yet()
    {
        $customer = Customer::create([
            'customer_name' => 'Gultom', 'user_id' => $this->pembeli->id,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id, 'user_id' => $this->pembeli->id,
            'sale_date' => now()->toDateString(), 'payment_method' => 'Utang',
            'payment_status' => 'Belum Bayar', 'total_amount' => 100000,
        ]);

        Receivable::create([
            'sale_id' => $sale->sale_id, 'customer_id' => $customer->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 100000, 'paid_amount' => 0, 'remaining_amount' => 100000,
            'status' => 'Unpaid',
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.tagihan'));

        $response->assertStatus(200);
        $response->assertDontSee('Riwayat Pembayaran');
    }

    public function test_pembeli_cannot_see_other_customers_invoices()
    {
        $myCustomer = Customer::create([
            'customer_name' => 'Gultom', 'user_id' => $this->pembeli->id,
        ]);
        $otherCustomer = Customer::create(['customer_name' => 'Orang Lain']);

        $mySale = Sale::create([
            'customer_id' => $myCustomer->id, 'user_id' => $this->pembeli->id,
            'sale_date' => now()->toDateString(), 'payment_method' => 'Cash',
            'payment_status' => 'Lunas', 'total_amount' => 10000,
        ]);
        $otherSale = Sale::create([
            'customer_id' => $otherCustomer->id, 'user_id' => $this->pembeli->id,
            'sale_date' => now()->toDateString(), 'payment_method' => 'Cash',
            'payment_status' => 'Lunas', 'total_amount' => 999999,
        ]);

        $response = $this->actingAs($this->pembeli)->get(route('toko.tagihan'));

        $response->assertSee('INV-' . str_pad($mySale->sale_id, 3, '0', STR_PAD_LEFT));
        $response->assertDontSee('INV-' . str_pad($otherSale->sale_id, 3, '0', STR_PAD_LEFT));
    }

    public function test_pemilik_cannot_access_tagihan()
    {
        $role = Role::create(['role_name' => 'Pemilik']);
        $pemilik = User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('password'), 'role_id' => $role->id,
        ]);

        $response = $this->actingAs($pemilik)->get(route('toko.tagihan'));

        $response->assertStatus(403);
    }
}
