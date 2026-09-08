<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseStockConversionTest extends TestCase
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

    public function test_receiving_1_5_lusin_adds_18_buah_to_stock()
    {
        $product = Product::create([
            'product_name'         => 'GENTONG 6688 HITAM',
            'brand'                => 'GM',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 120000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Goni',
            'dus_to_unit'          => 1.5,
            'stock'                => 0,
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'Supplier Test',
            'phone'         => '08123456789',
            'address'       => 'Jl. Test',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_date'     => now()->format('Y-m-d'),
            'status'      => 'Ordered',
        ]);

        $this->actingAs($this->user)->post(route('purchases.store'), [
            'po_id'         => $po->po_id,
            'purchase_date' => now()->format('Y-m-d'),
            'product_id'    => [$product->id],
            'quantity'      => [1.5],
            'unit'          => ['Lusin'],
            'price'         => [120000],
            'discount'      => [0],
        ]);

        $this->assertEquals(18.0, (float) $product->fresh()->stock);
    }

    public function test_deleting_purchase_restores_stock_correctly_in_buah()
    {
        $product = Product::create([
            'product_name'         => 'GENTONG 6688 HITAM',
            'brand'                => 'GM',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 120000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Goni',
            'dus_to_unit'          => 1.5,
            'stock'                => 0,
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'Supplier Test',
            'phone'         => '08123456789',
            'address'       => 'Jl. Test',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_date'     => now()->format('Y-m-d'),
            'status'      => 'Ordered',
        ]);

        $this->actingAs($this->user)->post(route('purchases.store'), [
            'po_id'         => $po->po_id,
            'purchase_date' => now()->format('Y-m-d'),
            'product_id'    => [$product->id],
            'quantity'      => [1.5],
            'unit'          => ['Lusin'],
            'price'         => [120000],
            'discount'      => [0],
        ]);

        $this->assertEquals(18.0, (float) $product->fresh()->stock);

        $purchase = \App\Models\Purchase::latest('purchase_id')->first();
        $this->actingAs($this->user)->delete(route('purchases.destroy', $purchase->purchase_id));

        $this->assertEquals(0.0, (float) $product->fresh()->stock);
    }
}
