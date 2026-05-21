<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
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

    public function test_can_view_purchase_orders_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchase-orders.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_create_purchase_order_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchase-orders.create'));

        $response->assertStatus(200);
    }

    public function test_can_create_purchase_order()
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Supplier Test',
            'phone'         => '08123456789',
        ]);

        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('purchase-orders.store'), [
                'supplier_id'      => $supplier->id,
                'po_date'          => now()->format('Y-m-d'),
                'status'           => 'Pending',
                'notes'            => 'Test notes',
                'product_id'       => [$product->id],
                'purchase_qty'     => [2],
                'price'            => [10000],
                'discount'         => [0],
            ]);

        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status'      => 'Pending',
        ]);
    }

    public function test_can_update_purchase_order()
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Supplier Test',
            'phone'         => '08123456789',
        ]);

        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_date'     => now()->format('Y-m-d'),
            'status'      => 'Pending',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('purchase-orders.update', $po->po_id), [
                'supplier_id'  => $supplier->id,
                'po_date'      => now()->format('Y-m-d'),
                'status'       => 'Ordered',
                'notes'        => 'Updated notes',
                'product_id'   => [$product->id],
                'purchase_qty' => [3],
                'price'        => [10000],
                'discount'     => [0],
            ]);

        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertDatabaseHas('purchase_orders', [
            'po_id'  => $po->po_id,
            'status' => 'Ordered',
        ]);
    }

    public function test_can_delete_purchase_order()
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Supplier Test',
            'phone'         => '08123456789',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_date'     => now()->format('Y-m-d'),
            'status'      => 'Pending',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('purchase-orders.destroy', $po->po_id));

        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertDatabaseMissing('purchase_orders', ['po_id' => $po->po_id]);
    }
}