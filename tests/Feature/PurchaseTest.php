<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Product $product;
    private PurchaseOrder $po;

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

        $this->supplier = Supplier::create([
            'supplier_name' => 'Supplier Test',
            'phone'         => '08123456789',
        ]);

        $this->product = Product::create([
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

        $this->po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_date'     => now()->format('Y-m-d'),
            'status'      => 'Ordered',
        ]);

        PurchaseOrderDetail::create([
            'po_id'         => $this->po->po_id,
            'product_id'    => $this->product->id,
            'purchase_qty'  => 2,
            'purchase_unit' => 'Dus',
            'quantity'      => 24,
            'unit'          => 'Lusin',
            'price'         => 10000,
            'discount'      => 0,
            'subtotal'      => 240000,
        ]);
    }

    public function test_can_view_purchases_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchases.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_create_purchase_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchases.create'));

        $response->assertStatus(200);
    }

    public function test_can_store_purchase_and_stock_increases()
    {
        $response = $this->actingAs($this->user)
            ->post(route('purchases.store'), [
                'po_id'        => $this->po->po_id,
                'purchase_date'=> now()->format('Y-m-d'),
                'notes'        => null,
                'product_id'   => [$this->product->id],
                'quantity'     => [24],
                'price'        => [10000],
                'discount'     => [0],
                'unit'         => ['Lusin'],
            ]);

        $response->assertRedirect(route('purchases.index'));

        // Stok bertambah
        $this->assertEquals(24, $this->product->fresh()->stock);

        // PO status jadi Completed
        $this->assertEquals('Completed', $this->po->fresh()->status);
    }

    public function test_can_delete_purchase_and_stock_decreases()
    {
        // Buat purchase dulu
        $this->actingAs($this->user)
            ->post(route('purchases.store'), [
                'po_id'         => $this->po->po_id,
                'purchase_date' => now()->format('Y-m-d'),
                'notes'         => null,
                'product_id'    => [$this->product->id],
                'quantity'      => [24],
                'price'         => [10000],
                'discount'      => [0],
                'unit'          => ['Lusin'],
            ]);

        $purchase = Purchase::latest('purchase_id')->first();

        // Stok sudah 24
        $this->assertEquals(24, $this->product->fresh()->stock);

        // Hapus purchase
        $this->actingAs($this->user)
            ->delete(route('purchases.destroy', $purchase->purchase_id));

        // Stok kembali ke 0
        $this->assertEquals(0, $this->product->fresh()->stock);
    }

    public function test_can_update_purchase()
    {
        // Buat purchase dulu
        $purchase = Purchase::create([
            'po_id'         => $this->po->po_id,
            'supplier_id'   => $this->supplier->id,
            'user_id'       => $this->user->id,
            'purchase_date' => now()->format('Y-m-d'),
            'total_amount'  => 240000,
        ]);

        PurchaseDetail::create([
            'purchase_id' => $purchase->purchase_id,
            'product_id'  => $this->product->id,
            'quantity'    => 24,
            'unit'        => 'Lusin',
            'price'       => 10000,
            'discount'    => 0,
            'subtotal'    => 240000,
        ]);

        // Update qty diterima
        $response = $this->actingAs($this->user)
            ->put(route('purchases.update', $purchase->purchase_id), [
                'purchase_date' => now()->format('Y-m-d'),
                'notes'         => 'Updated',
                'product_id'    => [$this->product->id],
                'quantity'      => [12], // qty berubah
                'price'         => [10000],
                'discount'      => [0],
                'unit'          => ['Lusin'],
            ]);

        $response->assertRedirect(route('purchases.index'));
        $this->assertDatabaseHas('purchases', [
            'purchase_id' => $purchase->purchase_id,
            'notes'       => 'Updated',
        ]);
    }

    public function test_guest_cannot_access_purchases()
    {
        $response = $this->get(route('purchases.index'));
        $response->assertRedirect(route('login'));
    }
}