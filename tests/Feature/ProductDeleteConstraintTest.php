<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeleteConstraintTest extends TestCase
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

    private function makeProduct(): Product
    {
        return Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 10,
        ]);
    }

    public function test_product_with_sale_details_cannot_be_deleted()
    {
        $product = $this->makeProduct();

        $sale = Sale::create([
            'user_id'        => $this->user->id,
            'sale_date'      => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount'   => 15000,
        ]);

        SaleDetail::create([
            'sale_id'    => $sale->sale_id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit'       => 'Buah',
            'price'      => 15000,
            'discount'   => 0,
            'subtotal'   => 15000,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas(
            'error',
            'Produk tidak dapat dihapus karena sudah tercatat dalam transaksi penjualan.'
        );

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_with_purchase_details_cannot_be_deleted()
    {
        $product  = $this->makeProduct();
        $supplier = Supplier::create(['supplier_name' => 'Supplier Test', 'phone' => '08123456789']);

        $purchase = Purchase::create([
            'supplier_id'   => $supplier->id,
            'user_id'       => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'total_amount'  => 10000,
        ]);

        PurchaseDetail::create([
            'purchase_id' => $purchase->purchase_id,
            'product_id'  => $product->id,
            'quantity'    => 1,
            'unit'        => 'Buah',
            'price'       => 10000,
            'discount'    => 0,
            'subtotal'    => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_without_transactions_can_still_be_deleted()
    {
        $product = $this->makeProduct();

        $response = $this->actingAs($this->user)
            ->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
