<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListPendingOnlineOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pembeli']);
        $this->pembeli = User::create([
            'name' => 'Akun Pembeli',
            'email' => 'pembeli@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $this->product = Product::create([
            'product_name' => 'Toples Kaca',
            'brand' => 'Brand Test',
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
            'sale_price' => 15000,
            'sale_unit' => 'Lusin',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 12,
            'stock' => 36,
        ]);
    }

    public function test_lists_only_unprocessed_storefront_orders_with_items_and_total(): void
    {
        // 03:15 UTC = 10:15 WIB
        Carbon::setTestNow('2026-11-20 03:15:00');
        $pending = $this->order(true, 'Pembeli: Sari Wulandari | Alamat: Jl. Mawar 1', 2, 'Lusin');
        $this->order(false, 'Pembeli: Sudah Diproses', 1, 'Lusin');
        $this->order(true, 'Pesanan via WhatsApp (081200000001)', 5, 'Buah');
        Carbon::setTestNow();

        $this->artisan('orders:list-pending-online')
            ->expectsOutputToContain("Pesanan #{$pending->sale_id} | 20/11/2026 10:15 WIB | Sari Wulandari")
            ->expectsTable(
                ['Produk', 'Jumlah', 'Satuan', 'Stok saat ini'],
                [['Toples Kaca', '2', 'Lusin', $this->product->fresh()->stok_display]],
            )
            ->expectsOutputToContain('Total pesanan: 1')
            ->doesntExpectOutputToContain('Sudah Diproses')
            ->assertSuccessful();
    }

    public function test_command_is_read_only_and_does_not_change_stock(): void
    {
        $sale = $this->order(true, 'Pembeli: Sari Wulandari', 2, 'Lusin');
        $productBefore = $this->product->fresh()->getAttributes();
        $saleBefore = $sale->fresh()->getAttributes();

        $selects = 0;
        $writes = [];
        DB::listen(function ($query) use (&$selects, &$writes): void {
            if (preg_match('/^\s*select\b/i', $query->sql)) {
                $selects++;
            } else {
                $writes[] = $query->sql;
            }
        });

        $this->artisan('orders:list-pending-online')->assertSuccessful();

        $this->assertGreaterThan(0, $selects, 'Listener tidak menangkap query command');
        $this->assertSame([], $writes, 'Command menjalankan query selain SELECT');
        $this->assertEquals(36, $this->product->fresh()->stock);
        $this->assertSame($productBefore, $this->product->fresh()->getAttributes());
        $this->assertSame($saleBefore, $sale->fresh()->getAttributes());
    }

    private function order(bool $isNew, string $notes, float $quantity, string $unit): Sale
    {
        $sale = Sale::create([
            'user_id' => $this->pembeli->id,
            'sale_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Belum Bayar',
            'total_amount' => 30000,
            'is_new' => $isNew,
            'notes' => $notes,
        ]);

        SaleDetail::create([
            'sale_id' => $sale->sale_id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit' => $unit,
            'price' => 15000,
            'discount' => 0,
            'subtotal' => 15000 * $quantity,
        ]);

        return $sale;
    }
}
