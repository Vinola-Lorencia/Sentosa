<?php

namespace Tests\Feature\Uat;

use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;

/**
 * A5 — Supplier CRUD. A6 — Purchase Order (harga, status).
 * A7 — Pembelian dari PO menambah stok. A8 — Stok = hitungan manual.
 */
class A05SupplierPembelianStokTest extends OwnerUatTestCase
{
    private function supplier(): Supplier
    {
        return Supplier::create(['supplier_name' => 'CV Plastik Jaya', 'phone' => '0811111', 'address' => 'Medan', 'email' => 'cv@jaya.test']);
    }

    public function test_a5_supplier_create_update_delete(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('suppliers.store'), ['supplier_name' => 'CV Plastik Jaya', 'phone' => '0811111', 'email' => 'cv@jaya.test'])
            ->assertRedirect(route('suppliers.index'));
        $supplier = Supplier::firstWhere('supplier_name', 'CV Plastik Jaya');
        $this->assertNotNull($supplier);

        $this->actingAs($this->pemilik)
            ->put(route('suppliers.update', $supplier), ['supplier_name' => 'CV Plastik Jaya Abadi', 'phone' => '0822222'])
            ->assertRedirect(route('suppliers.index'));
        $this->assertSame('CV Plastik Jaya Abadi', $supplier->fresh()->supplier_name);

        $this->actingAs($this->pemilik)->get(route('suppliers.index'))->assertSee('CV Plastik Jaya Abadi');

        $this->actingAs($this->pemilik)->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertModelMissing($supplier);
    }

    public function test_a5_supplier_with_recorded_purchase_cannot_be_deleted_gracefully(): void
    {
        $supplier = $this->supplier();
        Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $this->pemilik->id, 'purchase_date' => '2026-10-01', 'total_amount' => 1000]);

        $response = $this->actingAs($this->pemilik)->delete(route('suppliers.destroy', $supplier));

        // Harapan: ditolak dengan pesan, bukan error server.
        $this->assertNotSame(500, $response->status(), 'Hapus supplier yang punya pembelian menghasilkan error 500.');
        $this->assertModelExists($supplier);
    }

    public function test_a6_purchase_order_converts_purchase_qty_and_computes_price(): void
    {
        $supplier = $this->supplier();
        // 1 Goni = 2 Lusin, harga beli per Lusin
        $product = $this->product('EMBER 20L', ['sale_unit' => 'Lusin', 'purchase_unit' => 'Goni', 'dus_to_unit' => 2, 'stock' => 0]);

        $this->actingAs($this->pemilik)->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id, 'po_date' => '2026-10-10', 'status' => 'Pending',
            'product_id' => [$product->id], 'purchase_qty' => [3], 'price' => [50000], 'discount' => [10],
        ])->assertRedirect(route('purchase-orders.index'));

        $po = PurchaseOrder::with('details')->firstOrFail();
        $this->assertSame('Pending', $po->status);
        $detail = $po->details->first();
        $this->assertEquals(3, $detail->purchase_qty);
        $this->assertSame('Goni', $detail->purchase_unit);
        $this->assertEquals(6, $detail->quantity);    // 3 Goni × 2 Lusin
        $this->assertSame('Lusin', $detail->unit);
        $this->assertEquals(270000, $detail->subtotal); // 6 × 50.000 × 90%

        $this->actingAs($this->pemilik)->get(route('purchase-orders.show', $po->po_id))
            ->assertOk()->assertSee('EMBER 20L');

        // PO belum menambah stok.
        $this->assertEquals(0, $product->fresh()->stock);
    }

    public function test_a7_purchase_from_po_adds_converted_stock_and_completes_po(): void
    {
        $supplier = $this->supplier();
        $product = $this->product('EMBER 20L', ['sale_unit' => 'Lusin', 'purchase_unit' => 'Goni', 'dus_to_unit' => 2, 'stock' => 10]);
        $po = PurchaseOrder::create(['supplier_id' => $supplier->id, 'po_date' => '2026-10-10', 'status' => 'Ordered']);

        $this->actingAs($this->pemilik)->post(route('purchases.store'), [
            'po_id' => $po->po_id, 'purchase_date' => '2026-10-12',
            'product_id' => [$product->id, $product->id], 'quantity' => [6, 1], 'unit' => ['Lusin', 'Goni'],
            'price' => [50000, 100000], 'discount' => [0, 0],
        ])->assertRedirect(route('purchases.index'));

        // 10 + 6 Lusin (72) + 1 Goni (2 Lusin = 24) = 106 Buah
        $this->assertEquals(106, $product->fresh()->stock);
        $this->assertSame('Completed', $po->fresh()->status);

        $purchase = Purchase::firstOrFail();
        $this->assertEquals(400000, $purchase->total_amount);
        $this->assertSame($supplier->id, $purchase->supplier_id);
    }

    public function test_a8_stock_matches_manual_count_after_sale_purchase_edit_and_delete(): void
    {
        $product = $this->product('GAYUNG', ['sale_unit' => 'Lusin', 'sale_price' => 24000, 'stock' => 100]);
        $po = PurchaseOrder::create(['supplier_id' => $this->supplier()->id, 'po_date' => '2026-10-10', 'status' => 'Ordered']);

        // Penjualan 2 Lusin → 100 − 24 = 76
        $this->actingAs($this->pemilik)->post(route('sales.store'), [
            'sale_date' => '2026-10-12', 'payment_method' => 'Cash', 'payment_status' => 'Lunas',
            'product_id' => [$product->id], 'quantity' => [2], 'unit' => ['Lusin'], 'price' => [24000], 'discount' => [0],
        ])->assertRedirect(route('sales.index'));
        $this->assertEquals(76, $product->fresh()->stock);

        // Pembelian 1 Gross → 76 + 144 = 220
        $this->actingAs($this->pemilik)->post(route('purchases.store'), [
            'po_id' => $po->po_id, 'purchase_date' => '2026-10-12',
            'product_id' => [$product->id], 'quantity' => [1], 'unit' => ['Gross'], 'price' => [200000], 'discount' => [0],
        ]);
        $this->assertEquals(220, $product->fresh()->stock);

        // Ubah penjualan jadi 5 Buah → 220 + 24 − 5 = 239
        $sale = Sale::firstOrFail();
        $this->actingAs($this->pemilik)->put(route('sales.update', $sale->sale_id), [
            'sale_date' => '2026-10-12', 'payment_method' => 'Cash', 'payment_status' => 'Lunas',
            'product_id' => [$product->id], 'quantity' => [5], 'unit' => ['Buah'], 'price' => [2000], 'discount' => [0],
        ])->assertRedirect(route('sales.show', $sale->sale_id));
        $this->assertEquals(239, $product->fresh()->stock);

        // Hapus penjualan → stok kembali 244
        $this->actingAs($this->pemilik)->delete(route('sales.destroy', $sale->sale_id));
        $this->assertEquals(244, $product->fresh()->stock);
    }

    public function test_a8_sale_exceeding_stock_is_refused_and_stock_unchanged(): void
    {
        $product = $this->product('GAYUNG', ['stock' => 10]);

        $this->actingAs($this->pemilik)->post(route('sales.store'), [
            'sale_date' => '2026-10-12', 'payment_method' => 'Cash', 'payment_status' => 'Lunas',
            'product_id' => [$product->id], 'quantity' => [1], 'unit' => ['Lusin'], 'price' => [120000], 'discount' => [0],
        ])->assertSessionHas('error');

        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }
}
