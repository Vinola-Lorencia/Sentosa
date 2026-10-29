<?php

namespace Tests\Feature\Uat;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A1 — Hak akses: halaman khusus Pemilik, penolakan Karyawan/tamu, menu Blade.
 */
class A01HakAksesTest extends OwnerUatTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function ownerOnlyPages(): array
    {
        return [
            'supplier' => ['suppliers.index'],
            'pelanggan' => ['customers.index'],
            'purchase order' => ['purchase-orders.index'],
            'pembelian' => ['purchases.index'],
            'pengiriman' => ['pengiriman.index'],
            'piutang' => ['piutang.index'],
            'dashboard' => ['dashboard'],
            'laporan penjualan' => ['laporan.penjualan'],
            'laporan pembelian' => ['laporan.pembelian'],
            'laporan piutang' => ['laporan.piutang'],
            'role' => ['roles.index'],
            'user' => ['users.index'],
        ];
    }

    #[DataProvider('ownerOnlyPages')]
    public function test_pemilik_can_open_owner_page(string $route): void
    {
        $this->actingAs($this->pemilik)->get(route($route))->assertOk();
    }

    #[DataProvider('ownerOnlyPages')]
    public function test_karyawan_is_refused_on_owner_page(string $route): void
    {
        $this->actingAs($this->karyawan)->get(route($route))->assertForbidden();
    }

    #[DataProvider('ownerOnlyPages')]
    public function test_guest_is_redirected_to_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    public function test_karyawan_is_refused_on_owner_only_write_actions(): void
    {
        $product = $this->product();
        $sale = $this->sale($product);

        $this->actingAs($this->karyawan)->delete(route('sales.destroy', $sale->sale_id))->assertForbidden();
        $this->actingAs($this->karyawan)->post(route('suppliers.store'), ['supplier_name' => 'X'])->assertForbidden();
        $this->actingAs($this->karyawan)->post(route('roles.store'), ['role_name' => 'Hacker'])->assertForbidden();
        $this->actingAs($this->karyawan)->get(route('laporan.penjualan.excel'))->assertForbidden();

        $this->assertModelExists($sale);
    }

    public function test_dashboard_shows_pesanan_baru_block_for_pemilik(): void
    {
        $this->sale($this->product(), 2, 'Buah', ['is_new' => true, 'notes' => 'Pembeli: Sari']);

        $this->actingAs($this->pemilik)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 Pesanan Baru!');
    }

    public function test_pemilik_menu_shows_owner_links(): void
    {
        $html = $this->actingAs($this->pemilik)->get(route('products.index'))->assertOk()->getContent();

        foreach (['suppliers.index', 'purchase-orders.index', 'purchases.index', 'piutang.index', 'pengiriman.index',
            'laporan.penjualan', 'laporan.pembelian', 'laporan.piutang', 'users.index', 'roles.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html, $route);
        }
        $this->assertStringNotContainsString('href="'.route('products.my').'"', $html);
    }

    public function test_karyawan_menu_hides_owner_links(): void
    {
        $html = $this->actingAs($this->karyawan)->get(route('products.index'))->assertOk()->getContent();

        foreach (['suppliers.index', 'purchase-orders.index', 'purchases.index', 'piutang.index', 'pengiriman.index',
            'laporan.penjualan', 'users.index', 'roles.index'] as $route) {
            $this->assertStringNotContainsString('href="'.route($route).'"', $html, $route);
        }
        $this->assertStringContainsString('href="'.route('sales.index').'"', $html);
        $this->assertStringContainsString('href="'.route('products.my').'"', $html);
    }

    public function test_karyawan_page_has_no_link_that_returns_403_for_karyawan(): void
    {
        // The logo in navigation.blade.php links to the dashboard for every role.
        $html = $this->actingAs($this->karyawan)->get(route('products.index'))->assertOk()->getContent();

        $this->actingAs($this->karyawan)->get(route('dashboard'))->assertForbidden();
        $this->assertStringNotContainsString('href="'.route('dashboard').'"', $html,
            'Logo Karyawan menautkan ke /dashboard yang menghasilkan 403.');
    }
}
