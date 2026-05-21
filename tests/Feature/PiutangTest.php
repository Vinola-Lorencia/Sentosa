<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiutangTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pemilik']);
        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
    }

    public function test_can_view_piutang_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('piutang.index'));

        $response->assertStatus(200);
    }

    public function test_piutang_created_when_sale_belum_bayar()
    {
        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 10,
        ]);

        $this->actingAs($this->user)
            ->post(route('sales.store'), [
                'sale_date'      => now()->format('Y-m-d'),
                'payment_method' => 'Utang',
                'payment_status' => 'Belum Bayar',
                'due_date'       => now()->addDays(30)->format('Y-m-d'),
                'product_id'     => [$product->id],
                'quantity'       => [1],
                'price'          => [15000],
                'discount'       => [0],
                'unit'           => ['Lusin'],
            ]);

        $this->assertDatabaseHas('receivables', [
            'status' => 'Unpaid',
        ]);
    }

    public function test_can_bayar_piutang_sebagian()
    {
        $sale = Sale::create([
            'user_id'        => $this->user->id,
            'sale_date'      => now()->format('Y-m-d'),
            'payment_method' => 'Utang',
            'payment_status' => 'Belum Bayar',
            'total_amount'   => 100000,
        ]);

        $receivable = Receivable::create([
            'sale_id'          => $sale->sale_id,
            'amount'           => 100000,
            'paid_amount'      => 0,
            'remaining_amount' => 100000,
            'status'           => 'Unpaid',
            'due_date'         => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('piutang.bayar', $receivable->receivable_id), [
                'amount'       => 50000,
                'payment_date' => now()->format('Y-m-d'),
                'notes'        => 'Bayar sebagian',
            ]);

        $response->assertRedirect();

        $receivable->refresh();
        $this->assertEquals(50000, $receivable->paid_amount);
        $this->assertEquals(50000, $receivable->remaining_amount);
        $this->assertEquals('Unpaid', $receivable->status);
    }

    public function test_piutang_lunas_when_fully_paid()
    {
        $sale = Sale::create([
            'user_id'        => $this->user->id,
            'sale_date'      => now()->format('Y-m-d'),
            'payment_method' => 'Utang',
            'payment_status' => 'Belum Bayar',
            'total_amount'   => 100000,
        ]);

        $receivable = Receivable::create([
            'sale_id'          => $sale->sale_id,
            'amount'           => 100000,
            'paid_amount'      => 0,
            'remaining_amount' => 100000,
            'status'           => 'Unpaid',
            'due_date'         => now()->addDays(30)->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->post(route('piutang.bayar', $receivable->receivable_id), [
                'amount'       => 100000,
                'payment_date' => now()->format('Y-m-d'),
                'notes'        => 'Lunas',
            ]);

        $receivable->refresh();
        $this->assertEquals('Paid', $receivable->status);
        $this->assertEquals(0, $receivable->remaining_amount);
    }
}