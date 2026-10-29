<?php

namespace Tests\Feature\Uat;

use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A19 — Ekspor PDF/Excel. A20 — Role dan user.
 */
class A19EksporRoleUserTest extends OwnerUatTestCase
{
    /**
     * @return list<list<mixed>>
     */
    private function excelRows(string $url): array
    {
        $response = $this->actingAs($this->pemilik)->get($url)->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();
    }

    private function seedReportData(): void
    {
        $customer = Customer::create(['customer_name' => 'Toko Maju', 'phone' => '0813']);
        $product = $this->product('GAYUNG', ['sale_price' => 10000]);
        $this->sale($product, 3, 'Buah', ['sale_date' => '2026-10-05', 'customer_id' => $customer->id]);
        $unpaid = $this->sale($product, 2, 'Buah', ['sale_date' => '2026-10-06', 'payment_status' => 'Belum Bayar', 'payment_method' => 'Utang']);
        $this->sale($product, 9, 'Buah', ['sale_date' => '2026-11-02']); // di luar periode
        Receivable::create(['sale_id' => $unpaid->sale_id, 'due_date' => '2026-11-06', 'amount' => 20000, 'paid_amount' => 0, 'remaining_amount' => 20000, 'status' => 'Unpaid']);
        Purchase::create(['supplier_id' => Supplier::create(['supplier_name' => 'CV Plastik Jaya'])->id, 'user_id' => $this->pemilik->id, 'purchase_date' => '2026-10-07', 'total_amount' => 45000]);
    }

    public function test_a19_sales_excel_contains_period_rows_only(): void
    {
        $this->seedReportData();

        $rows = $this->excelRows(route('laporan.penjualan.excel', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']));

        $this->assertSame(['No Invoice', 'Tanggal', 'Customer', 'Metode Bayar', 'Status Bayar', 'Total'], $rows[0]);
        $this->assertCount(3, $rows); // header + 2 penjualan Oktober
        $this->assertSame(['INV-001', '05/10/2026', 'Toko Maju', 'Cash', 'Lunas'], array_slice($rows[1], 0, 5));
        $this->assertEquals(30000, $rows[1][5]);
        $this->assertSame('Walk-in', $rows[2][2]);
        $this->assertEquals(20000, $rows[2][5]);
    }

    public function test_a19_purchase_and_receivable_excel_are_not_empty(): void
    {
        $this->seedReportData();

        $purchases = $this->excelRows(route('laporan.pembelian.excel', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']));
        $this->assertGreaterThanOrEqual(2, count($purchases));
        $this->assertContains('CV Plastik Jaya', $purchases[1]);

        $receivables = $this->excelRows(route('laporan.piutang.excel'));
        $this->assertGreaterThanOrEqual(2, count($receivables));
        $this->assertContains('INV-002', $receivables[1]);
    }

    public function test_a19_pdf_exports_are_valid_pdf_files(): void
    {
        $this->seedReportData();

        foreach ([
            route('laporan.penjualan.pdf', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']),
            route('laporan.pembelian.pdf', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']),
            route('laporan.piutang.pdf'),
        ] as $url) {
            $response = $this->actingAs($this->pemilik)->get($url)->assertOk();
            $pdf = $response->getContent();

            $this->assertStringStartsWith('%PDF-', $pdf, $url);
            $this->assertGreaterThan(1000, strlen($pdf), $url);
            $this->assertStringContainsString('.pdf', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_a20_role_create_update_delete_unused(): void
    {
        $this->actingAs($this->pemilik)->post(route('roles.store'), ['role_name' => 'Kasir'])->assertRedirect(route('roles.index'));
        $role = Role::firstWhere('role_name', 'Kasir');

        $this->actingAs($this->pemilik)->post(route('roles.store'), ['role_name' => 'Kasir'])->assertSessionHasErrors('role_name');

        $this->actingAs($this->pemilik)->put(route('roles.update', $role), ['role_name' => 'Kasir Senior'])->assertRedirect(route('roles.index'));
        $this->assertSame('Kasir Senior', $role->fresh()->role_name);

        $this->actingAs($this->pemilik)->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));
        $this->assertModelMissing($role);
    }

    public function test_a20_user_create_update_delete(): void
    {
        $role = Role::firstWhere('role_name', 'Karyawan');

        $this->actingAs($this->pemilik)->post(route('users.store'), [
            'name' => 'Rina', 'email' => 'rina@uat.test', 'role_id' => $role->id,
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('users.index'));
        $user = User::firstWhere('email', 'rina@uat.test');
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        $this->actingAs($this->pemilik)->post(route('users.store'), [
            'name' => 'Rina 2', 'email' => 'rina@uat.test', 'role_id' => $role->id,
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->pemilik)->put(route('users.update', $user), [
            'name' => 'Rina Ayu', 'email' => 'rina@uat.test', 'role_id' => $role->id,
        ])->assertRedirect(route('users.index'));
        $this->assertSame('Rina Ayu', $user->fresh()->name);
        $this->assertTrue(Hash::check('rahasia123', $user->fresh()->password), 'Password tetap bila dikosongkan');

        $this->actingAs($this->pemilik)->delete(route('users.destroy', $user))->assertRedirect(route('users.index'));
        $this->assertModelMissing($user);
    }

    public function test_a20_deleting_role_still_used_by_a_user_keeps_the_user(): void
    {
        $role = Role::create(['role_name' => 'Kasir']);
        $kasir = $this->userWithRole('Kasir', 'Kasir Satu', 'kasir@uat.test');

        $this->actingAs($this->pemilik)->delete(route('roles.destroy', $role));

        $this->assertModelExists($kasir); // dugaan: user ikut terhapus (users.role_id onDelete cascade)
        $this->assertModelExists($role);
    }

    public function test_a20_deleting_role_whose_user_has_sales_fails_gracefully(): void
    {
        $role = Role::firstWhere('role_name', 'Karyawan');
        $this->sale($this->product(), 1, 'Buah', ['user_id' => $this->karyawan->id]);

        $response = $this->actingAs($this->pemilik)->delete(route('roles.destroy', $role));

        $this->assertNotSame(500, $response->status(), 'Hapus role yang user-nya punya transaksi menghasilkan error 500.');
        $this->assertModelExists($this->karyawan);
    }

    public function test_a20_deleting_user_with_sales_fails_gracefully(): void
    {
        $this->sale($this->product(), 1, 'Buah', ['user_id' => $this->karyawan->id]);

        $response = $this->actingAs($this->pemilik)->delete(route('users.destroy', $this->karyawan));

        $this->assertNotSame(500, $response->status(), 'Hapus user yang punya transaksi menghasilkan error 500.');
        $this->assertModelExists($this->karyawan);
    }
}
